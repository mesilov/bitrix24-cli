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

from runtime import ROOT, CONFIG, run, composer, toolchain, build_runtime


def identity(source_sha, version):
    sha = source_sha or subprocess.check_output(["git", "rev-parse", "HEAD"], cwd=ROOT, text=True).strip()
    if not re.fullmatch(r"[0-9a-f]{40}", sha):
        raise ValueError("Source SHA must contain 40 lowercase hexadecimal characters")
    if version and not re.fullmatch(r"v?\d+\.\d+\.\d+(?:-[0-9A-Za-z.-]+)?", version):
        raise ValueError("Release version must be a version tag, e.g. 0.1.0 or v0.1.0")
    return {"version": version or "dev-" + sha, "source_sha": sha,
            "php_version": CONFIG["php_version"], "static_php_cli_ref": CONFIG["static_php_cli_ref"],
            "micro_ref": CONFIG["micro_ref"], "extensions": CONFIG["extensions"],
            "toolchain_patches": {name: hashlib.sha256((ROOT / name).read_bytes()).hexdigest()
                                  for name in CONFIG["toolchain_patches"]},
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
    # The pinned Windows micro runtime fails when reading gzip-compressed PHAR entries.
    # The release ZIP still compresses the payload; keep the embedded PHAR plain there.
    info["phar_compression"] = "none" if os.name == "nt" else "gzip"
    env["B24CLI_PHAR_COMPRESSION"] = info["phar_compression"]
    app = stage(work, info, env)
    for name, mode in [("b24cli.phar", "application"), ("runtime-probe.phar", "probe")]:
        run(["php", "-d", "phar.readonly=0", ROOT / "build/package-phar.php", app, dist / name, mode], env=env)
    # Detect payload corruption separately from the native micro runtime.
    run(["php", dist / "b24cli.phar", "--version", "--no-ansi"], env=env)
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
    if args.target == "linux-amd64":
        # SPC's library dumper does not include the musl/GCC compiler runtime.
        shutil.copy2(spc / "source/musl-1.2.5/COPYRIGHT", dest / "musl-COPYRIGHT.txt")
        for name in ["GCC-COPYING3.txt", "GCC-COPYING.RUNTIME.txt"]:
            shutil.copy2(ROOT / "build/licenses" / name, dest / name)
    shutil.copy2(spc / "downloads/.lock.json", dist / "runtime-sources.json")


if __name__ == "__main__":
    try:
        main()
    except (RuntimeError, ValueError, subprocess.CalledProcessError) as error:
        print(str(error), file=sys.stderr)
        sys.exit(1)
