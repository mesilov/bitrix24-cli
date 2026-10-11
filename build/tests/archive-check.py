#!/usr/bin/env python3
"""Check exact release contents, checksums, permissions and negative fixtures."""
import hashlib
import io
from pathlib import Path
import sys
import tarfile
import zipfile


def matches(data, checksum):
    return hashlib.sha256(data).hexdigest() == checksum


root = Path(sys.argv[1])
lines = (root / "SHA256SUMS").read_text().splitlines()
expected = {"b24cli-linux-amd64.tar.gz", "b24cli-macos-arm64.tar.gz", "b24cli-windows-amd64.zip"}
assert {line.split("  ", 1)[1] for line in lines} == expected, "Incomplete release set"
for line in lines:
    checksum, name = line.split("  ", 1)
    data = (root / name).read_bytes()
    assert matches(data, checksum), "Checksum mismatch: " + name
    assert not matches(data + b"corruption-fixture", checksum), "Corrupted archive passed verification"
    if name.endswith(".zip"):
        with zipfile.ZipFile(io.BytesIO(data)) as archive:
            names = archive.namelist()
            assert archive.testzip() is None
            assert "b24cli.exe" in names
    else:
        with tarfile.open(fileobj=io.BytesIO(data), mode="r:gz") as archive:
            names = archive.getnames()
            assert archive.getmember("b24cli").mode == 0o755
    assert "build-info.json" in names and "YIIPRESS-LICENSE.txt" in names
    assert any(item.startswith("runtime-licenses/") for item in names)
    assert not any("runtime-probe" in item or item.endswith(".phar") or item.startswith("vendor/") for item in names)
print("Verified three archives, executable permissions, notices and corruption rejection")
