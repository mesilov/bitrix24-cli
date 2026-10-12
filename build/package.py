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

from runtime import ROOT, CONFIG, run, composer, toolchain, build_runtime, extensions_for
from source import validate_sha, verify_checkout
from compress import provenance, compress_micro, pack_macos, export_notices, write_report


def identity(source_sha, version, target):
    # Docker consumes the SHA validated before submitting its context; native
    # builders validate the actual Git checkout before creating any staging.
    sha = verify_checkout(ROOT, source_sha) if (ROOT / '.git').exists() else validate_sha(source_sha or '')
    if version and not re.fullmatch(r"v?\d+\.\d+\.\d+(?:-[0-9A-Za-z.-]+)?", version):
        raise ValueError("Release version must be a version tag, e.g. 0.1.0 or v0.1.0")
    return {"version": version or "dev-" + sha, "source_sha": sha,
            "php_version": CONFIG["php_version"], "static_php_cli_ref": CONFIG["static_php_cli_ref"],
            "micro_ref": CONFIG["micro_ref"], "extensions": extensions_for(target),
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
    info = identity(args.source_sha, args.version, args.target) if not args.runtime_only else None
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
    run(["php", "-d", "phar.readonly=0", ROOT / "build/tests/phar-stub-check.php"], env=env)
    env["COMPOSER_ROOT_VERSION"] = info["version"] if args.version else "dev-dev"
    info["executable_compression"] = provenance(args.target)
    info["phar_compression"] = "gzip"
    info["payload_strategy"] = "temporary-phar" if os.name == "nt" else "in-place"
    env["B24CLI_PHAR_COMPRESSION"] = info["phar_compression"]
    env["B24CLI_PAYLOAD_STRATEGY"] = info["payload_strategy"]
    app = stage(work, info, env)
    for name, mode in [("b24cli.phar", "application"), ("runtime-probe.phar", "probe")]:
        run(["php", "-d", "phar.readonly=0", ROOT / "build/package-phar.php", app, dist / name, mode], env=env)
    run(["php", "-d", "phar.readonly=0", ROOT / "build/tests/temporary-phar-check.php"], env=env)
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
    raw = dist / "uncompressed"
    raw.mkdir(exist_ok=True)
    shutil.copy2(dist / "build-info.json", raw / "build-info.json")
    compressed_runtime = (compress_micro(args.target, work, spc / "buildroot/bin/micro.sfx")
                          if args.target != "macos-arm64" else None)
    for name in ["b24cli", "runtime-probe"]:
        payload = dist / (name + ".phar")
        original, final = raw / (name + suffix), dist / (name + suffix)
        run(["php", "bin/spc", "micro:combine", payload, "-O", original], spc, env)
        original.chmod(0o755)
        if args.target == "macos-arm64":
            from macos import finalize
            finalize(original, payload)
            shutil.copy2(original, final)
            pack_macos(work, final, env)
        else:
            run(["php", "bin/spc", "micro:combine", payload, "--with-micro", compressed_runtime,
                 "-O", final], spc, env)
            if not final.read_bytes().endswith(payload.read_bytes()):
                raise RuntimeError("Compressed executable changed the original PHAR bytes")
        final.chmod(0o755)
    export_notices(args.target, work, dist / "compression-licenses")
    write_report(dist, args.target)
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
