#!/usr/bin/env python3
"""Build the same production PHAR and PHP micro payload on each native target."""
import argparse
import hashlib
import json
import os
from pathlib import Path
import platform
import re
import shutil
import subprocess
import sys
import tarfile
import urllib.request

ROOT = Path(__file__).resolve().parent.parent
CONFIG = json.loads((ROOT / "build/config.json").read_text())


def run(args, cwd=ROOT, env=None):
    print("+ " + " ".join(map(str, args)), flush=True)
    subprocess.run(list(map(str, args)), cwd=cwd, env=env, check=True)


def composer(args, cwd, env):
    # Windows Composer is a batch launcher; call its PHP payload directly.
    executable = shutil.which("composer")
    if not executable:
        raise RuntimeError("Composer is required on the build host")
    if os.name == "nt":
        phar = Path(executable).with_name("composer.phar")
        if not phar.is_file():
            raise RuntimeError("composer.phar must be beside the Composer launcher")
        return run(["php", phar, *args], cwd, env)
    return run([executable, *args], cwd, env)


def identity(source_sha, version):
    sha = source_sha or subprocess.check_output(["git", "rev-parse", "HEAD"], cwd=ROOT, text=True).strip()
    if not re.fullmatch(r"[0-9a-f]{40}", sha):
        raise ValueError("Source SHA must contain 40 lowercase hexadecimal characters")
    if version and not re.fullmatch(r"v?\d+\.\d+\.\d+(?:-[0-9A-Za-z.-]+)?", version):
        raise ValueError("Release version must be a version tag, e.g. 0.1.0 or v0.1.0")
    return {"version": version or "dev-" + sha, "source_sha": sha,
            "php_version": CONFIG["php_version"], "static_php_cli_ref": CONFIG["static_php_cli_ref"],
            "micro_ref": CONFIG["micro_ref"], "extensions": CONFIG["extensions"],
            "composer_lock_sha256": hashlib.sha256((ROOT / "composer.lock").read_bytes()).hexdigest()}


def stage(work, info, env):
    app = work / "app"
    if app.exists():
        shutil.rmtree(app)
    app.mkdir(parents=True)
    paths = list(CONFIG["application_paths"])
    paths += [p for p in CONFIG["optional_resource_paths"] if (ROOT / p).exists()]
    for relative in paths:
        source, dest = ROOT / relative, app / relative
        dest.parent.mkdir(parents=True, exist_ok=True)
        if source.is_dir():
            shutil.copytree(source, dest, symlinks=True)
        else:
            shutil.copy2(source, dest)
    (app / "build-info.json").write_text(json.dumps(info, indent=2) + "\n", encoding="utf-8")
    composer(["install", "--no-dev", "--no-interaction", "--no-progress",
              "--no-plugins", "--no-scripts", "--classmap-authoritative"], app, env)
    composer(["check-platform-reqs", "--no-dev"], app, env)
    return app


def toolchain(work, env):
    ref = CONFIG["static_php_cli_ref"]
    spc = work / ("static-php-cli-" + ref)
    if not spc.exists():
        archive = work / "static-php-cli.tar.gz"
        urllib.request.urlretrieve("https://codeload.github.com/crazywhalecc/static-php-cli/tar.gz/" + ref, archive)
        unpack = work / "unpack"
        unpack.mkdir(exist_ok=True)
        with tarfile.open(archive) as tar:
            for member in tar.getmembers():
                destination = (unpack / member.name).resolve()
                if not (member.isfile() or member.isdir()) or not destination.is_relative_to(unpack.resolve()):
                    raise RuntimeError("Unsafe member in toolchain archive")
            # Debian's Python 3.11 lacks extraction filters; all members were validated above.
            tar.extractall(unpack)
        (unpack / ("static-php-cli-" + ref)).rename(spc)
        shutil.rmtree(unpack)
        archive.unlink()
    source = json.loads((spc / "config/source.json").read_text())
    source["micro"]["rev"] = CONFIG["micro_ref"]
    (spc / "config/source.json").write_text(json.dumps(source, indent=4) + "\n")
    composer(["install", "--no-dev", "--no-interaction", "--no-progress", "--classmap-authoritative"], spc, env)
    return spc


def build_runtime(spc, env, target):
    if target == "linux-amd64":
        env["SPC_TOOLCHAIN"] = "SPC\\toolchain\\MuslToolchain"
        env["SPC_TARGET"] = "x86_64-linux-musl"
    runtime_key = hashlib.sha256((ROOT / "build/config.json").read_bytes()
                                 + Path(__file__).read_bytes() + target.encode()).hexdigest()
    marker = spc / "b24cli-runtime-key"
    micro = spc / "buildroot/bin/micro.sfx"
    if not micro.exists() or not marker.exists() or marker.read_text() != runtime_key:
        run(["php", "bin/spc", "download", "--for-extensions=" + ",".join(CONFIG["extensions"]),
             "--with-php=" + CONFIG["php_version"], "--without-suggestions"], spc, env)
        run(["php", "bin/spc", "doctor", "--auto-fix"], spc, env)
        run(["php", "bin/spc", "build", ",".join(CONFIG["extensions"]), "--build-micro", "--with-micro-fake-cli"], spc, env)
        marker.write_text(runtime_key)


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument("target", choices=["phar", "linux-amd64", "macos-arm64", "windows-amd64"])
    parser.add_argument("--source-sha", default=os.environ.get("B24CLI_SOURCE_SHA"))
    parser.add_argument("--version", default=os.environ.get("B24CLI_RELEASE_VERSION"))
    parser.add_argument("--runtime-only", action="store_true")
    args = parser.parse_args()
    info = identity(args.source_sha, args.version) if not args.runtime_only else None
    if args.target != "phar":
        expected = {"linux-amd64": ("Linux", "x86_64"), "macos-arm64": ("Darwin", "arm64"),
                    "windows-amd64": ("Windows", "AMD64")}[args.target]
        if (platform.system(), platform.machine()) != expected:
            raise RuntimeError("Native target mismatch: " + str(expected))
    if not shutil.which("php"):
        raise RuntimeError("PHP is required on the build host")
    work = ROOT / "runtime" / ("package-" + args.target)
    dist = ROOT / "dist" / args.target
    work.mkdir(parents=True, exist_ok=True)
    dist.mkdir(parents=True, exist_ok=True)
    env = os.environ.copy()
    env["COMPOSER_CACHE_DIR"] = str(work / "composer-cache")
    if args.runtime_only:
        if args.target == "phar":
            raise ValueError("PHAR does not have a compiled runtime")
        build_runtime(toolchain(work, env), env, args.target)
        return
    env["COMPOSER_ROOT_VERSION"] = info["version"] if args.version else "dev-dev"
    app = stage(work, info, env)
    for name, mode in [("b24cli.phar", "application"), ("runtime-probe.phar", "probe")]:
        run(["php", "-d", "phar.readonly=0", ROOT / "build/package-phar.php", app, dist / name, mode], env=env)
    (dist / "build-info.json").write_text(json.dumps(info, indent=2) + "\n")
    shutil.copy2(ROOT / "build/THIRD-PARTY-NOTICES.md", dist / "THIRD-PARTY-NOTICES.md")
    shutil.copy2(ROOT / "build/YIIPRESS-LICENSE.txt", dist / "YIIPRESS-LICENSE.txt")
    if args.target == "phar":
        return
    spc = toolchain(work, env)
    build_runtime(spc, env, args.target)
    suffix = ".exe" if os.name == "nt" else ""
    for name in ["b24cli", "runtime-probe"]:
        run(["php", "bin/spc", "micro:combine", dist / (name + ".phar"), "-O", dist / (name + suffix)], spc, env)
    licenses = spc / "buildroot/license"
    if not licenses.exists():
        licenses = spc / "buildroot/licenses"
    if not licenses.is_dir():
        raise RuntimeError("Runtime license dump missing; refusing to distribute without notices")
    dest = dist / "runtime-licenses"
    if dest.exists():
        shutil.rmtree(dest)
    shutil.copytree(licenses, dest)
    shutil.copy2(spc / "source/php-src/sapi/micro/LICENSE", dest / "phpmicro-LICENSE.txt")
    shutil.copy2(spc / "download/.lock.json", dist / "runtime-sources.json")


if __name__ == "__main__":
    try:
        main()
    except (RuntimeError, ValueError, subprocess.CalledProcessError) as error:
        print(str(error), file=sys.stderr)
        sys.exit(1)
