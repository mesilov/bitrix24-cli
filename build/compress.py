#!/usr/bin/env python3
"""Compress native executables without changing the cached PHP runtime."""
import argparse
import hashlib
import json
import os
from pathlib import Path
import re
import shutil
import subprocess
import tarfile
import urllib.request
import zipfile

ROOT = Path(__file__).resolve().parent.parent
CONFIG = json.loads((ROOT / "build/compression.json").read_text())


def digest(path):
    return hashlib.sha256(Path(path).read_bytes()).hexdigest()


def provenance(target):
    if target == "phar":
        return {"tool": "none"}
    if target == "macos-arm64":
        config = CONFIG["wrappe"]
        return {"tool": "wrappe", "version": config["version"], "strategy": "whole-executable",
                "compression": config["compression"], "rust_toolchain": config["rust_toolchain"],
                "crate_sha256": config["crate_sha256"]}
    config = CONFIG["upx"]
    return {"tool": "upx", "version": config["version"], "strategy": "micro-before-phar",
            "level": config["level"], "archive_sha256": config["targets"][target]["sha256"]}


def checked_download(url, destination, expected):
    destination = Path(destination)
    destination.parent.mkdir(parents=True, exist_ok=True)
    if not destination.exists():
        temporary = destination.with_suffix(destination.suffix + ".download")
        try:
            urllib.request.urlretrieve(url, temporary)
            if digest(temporary) != expected:
                raise RuntimeError("Compressor archive SHA256 mismatch")
            temporary.replace(destination)
        finally:
            temporary.unlink(missing_ok=True)
    if digest(destination) != expected:
        raise RuntimeError("Cached compressor archive SHA256 mismatch")
    return destination


def check_version(tool, expected, env=None):
    result = subprocess.check_output([str(tool), "--version"], text=True, env=env)
    name = "wrappe" if Path(tool).name.startswith("wrappe") else "upx"
    if not re.search(r"(?im)^" + name + r"\s+" + re.escape(expected) + r"(?:\s|$)", result):
        raise RuntimeError("Unexpected compressor version: " + result.strip())


def install_upx(target, work):
    config = CONFIG["upx"]
    source = config["targets"][target]
    root = Path(work) / "packers"
    archive = checked_download(source["url"], root / Path(source["url"]).name, source["sha256"])
    executable = "upx.exe" if target.startswith("windows") else "upx"
    tool = root / executable
    notices = root / "upx-notices"
    notices.mkdir(exist_ok=True)
    if archive.suffix == ".zip":
        with zipfile.ZipFile(archive) as packed:
            members = [(entry.filename, packed.read(entry)) for entry in packed.infolist() if not entry.is_dir()]
    else:
        with tarfile.open(archive) as packed:
            members = [(entry.name, packed.extractfile(entry).read()) for entry in packed.getmembers() if entry.isfile()]
    binaries = [data for name, data in members if Path(name).name == executable]
    if len(binaries) != 1:
        raise RuntimeError("UPX archive does not contain exactly one executable")
    tool.write_bytes(binaries[0])
    tool.chmod(0o755)
    for name, data in members:
        if Path(name).name.upper().startswith(("LICENSE", "COPYING")):
            (notices / Path(name).name).write_bytes(data)
    if not any(notices.iterdir()):
        raise RuntimeError("UPX license notices are missing")
    check_version(tool, config["version"])
    return tool


def install_wrappe(work, env):
    config = CONFIG["wrappe"]
    root = Path(work) / "packers"
    checked_download("https://crates.io/api/v1/crates/wrappe/" + config["version"] + "/download",
                     root / "wrappe.crate", config["crate_sha256"])
    tool = root / "bin/wrappe"
    cargo_env = env.copy()
    cargo_env["CARGO_HOME"] = str(root / "cargo-home")
    if not tool.is_file():
        if not shutil.which("cargo"):
            raise RuntimeError("Rust/cargo is required to build the macOS compressor")
        subprocess.run(["cargo", "+" + config["rust_toolchain"], "install", "--locked",
                        "--version", config["version"], "--root", str(root), "wrappe"],
                       env=cargo_env, check=True)
    check_version(tool, config["version"], cargo_env)
    return tool


def require_reduction(original, compressed):
    if not Path(compressed).is_file() or not 0 < Path(compressed).stat().st_size < Path(original).stat().st_size:
        raise RuntimeError("Compression did not produce a smaller executable")


def compress_micro(target, work, runtime):
    tool = install_upx(target, work)
    result = Path(work) / "packers/micro.sfx"
    shutil.copy2(runtime, result)
    subprocess.run([str(tool), CONFIG["upx"]["level"], str(result)], check=True)
    subprocess.run([str(tool), "--test", str(result)], check=True)
    require_reduction(runtime, result)
    return result


def pack_macos(work, binary, env):
    tool = install_wrappe(work, env)
    binary = Path(binary)
    root = Path(work) / ("wrappe-input-" + binary.name)
    if root.exists():
        shutil.rmtree(root)
    root.mkdir()
    shutil.copy2(binary, root / binary.name)
    packed = binary.with_name(binary.name + ".packed")
    packed.unlink(missing_ok=True)
    subprocess.run([str(tool), "--compression", str(CONFIG["wrappe"]["compression"]),
                    "--show-information", "none", str(root), binary.name, str(packed)], env=env, check=True)
    packed.chmod(0o755)
    subprocess.run(["codesign", "--force", "--sign", "-", "--identifier", "b24cli", str(packed)], check=True)
    subprocess.run(["codesign", "--verify", "--strict", str(packed)], check=True)
    require_reduction(binary, packed)
    packed.replace(binary)


def export_notices(target, work, destination):
    destination = Path(destination)
    destination.mkdir(exist_ok=True)
    if target == "macos-arm64":
        shutil.copy2(ROOT / "build/licenses/wrappe-LICENSE.txt", destination / "wrappe-LICENSE.txt")
        # Preserve the copyright/license files from the exact locked crates used
        # to build the embedded runner (also includes packer-only dependencies).
        registry = Path(work) / "packers/cargo-home/registry/src"
        count = 0
        for crate in registry.glob("*/*"):
            for source in crate.rglob("*"):
                if source.is_file() and source.name.upper().startswith(("LICENSE", "COPYING", "NOTICE")):
                    dest = destination / "wrappe-dependencies" / crate.name / source.relative_to(crate)
                    dest.parent.mkdir(parents=True, exist_ok=True)
                    shutil.copy2(source, dest)
                    count += 1
        if not count:
            raise RuntimeError("Locked wrappe dependency notices are missing")
    else:
        for source in (Path(work) / "packers/upx-notices").iterdir():
            shutil.copy2(source, destination / ("upx-" + source.name))


def write_report(dist, target):
    dist = Path(dist)
    report = {"target": target, "compressor": provenance(target), "executables": {}}
    for name in ["b24cli", "runtime-probe"]:
        name += ".exe" if target.startswith("windows") else ""
        original, final = dist / "uncompressed" / name, dist / name
        require_reduction(original, final)
        report["executables"][name] = {
            "original_bytes": original.stat().st_size, "compressed_bytes": final.stat().st_size,
            "original_sha256": digest(original), "compressed_sha256": digest(final)}
    (dist / "compression-info.json").write_text(json.dumps(report, indent=2) + "\n")
    print(json.dumps(report, indent=2))
    return report


def verify_report(dist):
    dist = Path(dist)
    report = json.loads((dist / "compression-info.json").read_text())
    if report["compressor"] != provenance(report["target"]):
        raise RuntimeError("Compression provenance differs from the pinned configuration")
    info = json.loads((dist / "build-info.json").read_text())
    if info["executable_compression"] != report["compressor"]:
        raise RuntimeError("Embedded build identity and compression report differ")
    expected = {"b24cli", "runtime-probe"}
    if report["target"].startswith("windows"):
        expected = {name + ".exe" for name in expected}
    if set(report["executables"]) != expected:
        raise RuntimeError("Compression report executable set differs")
    for name, item in report["executables"].items():
        original, final = dist / "uncompressed" / name, dist / name
        require_reduction(original, final)
        for prefix, path in [("original", original), ("compressed", final)]:
            if path.stat().st_size != item[prefix + "_bytes"] or digest(path) != item[prefix + "_sha256"]:
                raise RuntimeError("Compression size/SHA256 differs from actual bytes: " + name)
    print("Verified compressor provenance, exact hashes and size reduction")
    return report


if __name__ == "__main__":
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("dist", type=Path)
    verify_report(parser.parse_args().dist)
