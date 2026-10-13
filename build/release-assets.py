#!/usr/bin/env python3
"""Pack verified platform outputs and calculate checksums of final archives."""
import argparse
import hashlib
from pathlib import Path
import tarfile
import zipfile

TARGETS = ["linux-amd64", "macos-arm64", "windows-amd64"]


def pack(source, target, destination):
    root = source / target
    if not root.is_dir():
        root = source / ("b24cli-" + target)
    executable = "b24cli.exe" if target.startswith("windows") else "b24cli"
    required = [executable, "build-info.json", "runtime-sources.json", "THIRD-PARTY-NOTICES.md",
                "BITRIX24-CLI-LICENSE.txt", "YIIPRESS-LICENSE.txt", "runtime-licenses", "compression-info.json", "compression-licenses"]
    for name in required:
        if not (root / name).exists():
            raise FileNotFoundError(root / name)
    files = sorted(path for name in required for path in
                   ((root / name).rglob("*") if (root / name).is_dir() else [root / name]) if path.is_file())
    if target.startswith("windows"):
        output = destination / f"b24cli-{target}.zip"
        with zipfile.ZipFile(output, "w", zipfile.ZIP_DEFLATED) as archive:
            for path in files:
                archive.write(path, path.relative_to(root))
    else:
        output = destination / f"b24cli-{target}.tar.gz"
        with tarfile.open(output, "w:gz") as archive:
            for path in files:
                info = archive.gettarinfo(str(path), str(path.relative_to(root)))
                info.mode = 0o755 if path.name == executable else 0o644
                info.uid = info.gid = 0
                info.uname = info.gname = ""
                with path.open("rb") as stream:
                    archive.addfile(info, stream)
    return output


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument("source", type=Path)
    parser.add_argument("destination", type=Path)
    parser.add_argument("--target", choices=TARGETS, action="append")
    args = parser.parse_args()
    args.destination.mkdir(parents=True, exist_ok=True)
    archives = [pack(args.source, target, args.destination) for target in (args.target or TARGETS)]
    checksum = "".join(hashlib.sha256(path.read_bytes()).hexdigest() + "  " + path.name + "\n" for path in archives)
    (args.destination / "SHA256SUMS").write_text(checksum)
    print(checksum, end="")


if __name__ == "__main__":
    main()
