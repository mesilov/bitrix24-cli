#!/usr/bin/env python3
"""Regression coverage for compressor failures, payload preservation and reports."""
import json
import importlib.util
import hashlib
import tarfile
import zipfile
from pathlib import Path
import subprocess
import sys
import tempfile
import unittest
from unittest.mock import patch

sys.path.insert(0, str(Path(__file__).resolve().parents[1]))
import compress as compression


class CompressionChecks(unittest.TestCase):
    def setUp(self):
        self.directory = tempfile.TemporaryDirectory()
        self.root = Path(self.directory.name)

    def tearDown(self):
        self.directory.cleanup()

    def test_valid_cached_archive_is_reused_without_network(self):
        archive = self.root / "upx.xz"
        archive.write_bytes(b"verified tool archive")
        with patch("compress.urllib.request.urlretrieve", side_effect=AssertionError("network")):
            self.assertEqual(compression.checked_download("fixture", archive, compression.digest(archive)), archive)

    def test_wrong_cached_digest_is_rejected(self):
        archive = self.root / "upx.xz"
        archive.write_bytes(b"changed")
        with self.assertRaisesRegex(RuntimeError, "SHA256"):
            compression.checked_download("fixture", archive, "0" * 64)

    def test_wrong_download_digest_is_rejected_without_install(self):
        archive = self.root / "upx.xz"
        def download(url, dest):
            Path(dest).write_bytes(b"substituted tool")
        with patch("compress.urllib.request.urlretrieve", side_effect=download):
            with self.assertRaisesRegex(RuntimeError, "SHA256"):
                compression.checked_download("fixture", archive, "0" * 64)
        self.assertFalse(archive.exists())
        self.assertFalse(archive.with_suffix(".xz.download").exists())

    def test_unexpected_tool_version_is_rejected(self):
        with patch("compress.subprocess.check_output", return_value="upx 5.1.0\n"):
            with self.assertRaisesRegex(RuntimeError, "version"):
                compression.check_version("upx", "5.2.1")

    def test_expected_tool_version_is_accepted(self):
        for name, version in [("upx", "5.2.1"), ("wrappe", "1.0.4")]:
            with patch("compress.subprocess.check_output", return_value=name + " " + version + "\n"):
                compression.check_version(name, version)

    def test_no_output_empty_or_no_reduction_is_rejected(self):
        original = self.root / "original"
        final = self.root / "final"
        original.write_bytes(b"original executable")
        for content in [None, b"", original.read_bytes(), original.read_bytes() * 2]:
            if content is not None:
                final.write_bytes(content)
            with self.assertRaisesRegex(RuntimeError, "smaller"):
                compression.require_reduction(original, final)

    def test_upx_failure_preserves_original_runtime(self):
        original = self.root / "original"
        original.write_bytes(b"original runtime")
        (self.root / "packers").mkdir()
        with patch("compress.install_upx", return_value=self.root / "upx"), \
             patch("compress.subprocess.run", side_effect=subprocess.CalledProcessError(1, "upx")):
            with self.assertRaises(subprocess.CalledProcessError):
                compression.compress_micro("linux-amd64", self.root, original)
        self.assertEqual(original.read_bytes(), b"original runtime")

    def test_micro_is_packed_and_tested_on_a_copy(self):
        original = self.root / "original"
        original.write_bytes(b"runtime" * 100)
        (self.root / "packers").mkdir()
        commands = []
        def run(args, **kwargs):
            commands.append(args)
            if "--best" in args:
                Path(args[-1]).write_bytes(b"packed runtime")
        with patch("compress.install_upx", return_value=self.root / "upx"), \
             patch("compress.subprocess.run", side_effect=run):
            result = compression.compress_micro("linux-amd64", self.root, original)
        self.assertEqual(original.read_bytes(), b"runtime" * 100)
        self.assertEqual(result.read_bytes(), b"packed runtime")
        self.assertEqual([args[1] for args in commands], ["--best", "--test"])

    def fixture_report(self):
        (self.root / "uncompressed").mkdir()
        for name in ["b24cli", "runtime-probe"]:
            (self.root / "uncompressed" / name).write_bytes(b"original " * 100)
            (self.root / name).write_bytes(b"packed")
        info = {"executable_compression": compression.provenance("linux-amd64")}
        (self.root / "build-info.json").write_text(json.dumps(info))
        return compression.write_report(self.root, "linux-amd64")

    def test_exact_report_passes_and_changed_final_bytes_fail(self):
        self.fixture_report()
        compression.verify_report(self.root)
        (self.root / "b24cli").write_bytes(b"change")
        with self.assertRaisesRegex(RuntimeError, "SHA256"):
            compression.verify_report(self.root)

    def test_changed_original_bytes_fail(self):
        self.fixture_report()
        (self.root / "uncompressed/b24cli").write_bytes(b"changed " * 100)
        with self.assertRaisesRegex(RuntimeError, "SHA256"):
            compression.verify_report(self.root)

    def test_unpinned_compressor_report_is_rejected(self):
        report = self.fixture_report()
        report["compressor"]["version"] = "unverified"
        (self.root / "compression-info.json").write_text(json.dumps(report))
        with self.assertRaisesRegex(RuntimeError, "pinned"):
            compression.verify_report(self.root)

    def test_macos_packer_signs_outer_and_keeps_inner_bytes(self):
        binary = self.root / "b24cli"
        original = b"signed original" * 100
        binary.write_bytes(original)
        calls = []
        def run(args, **kwargs):
            calls.append(args)
            if "--compression" in args:
                self.assertEqual((self.root / "wrappe-input-b24cli/b24cli").read_bytes(), original)
                Path(args[-1]).write_bytes(b"compressed signed launcher")
        with patch("compress.install_wrappe", return_value=self.root / "wrappe"), \
             patch("compress.subprocess.run", side_effect=run):
            compression.pack_macos(self.root, binary, {})
        self.assertEqual(binary.read_bytes(), b"compressed signed launcher")
        self.assertEqual(calls[0][calls[0].index("--show-information") + 1], "none")
        self.assertIn("--sign", calls[1])
        self.assertIn("--verify", calls[2])
        self.assertFalse(any("--cleanup" in args for args in calls))

    def test_release_archives_contain_final_bytes_and_not_diagnostic_originals(self):
        spec = importlib.util.spec_from_file_location("release_assets", Path(__file__).resolve().parents[1] / "release-assets.py")
        module = importlib.util.module_from_spec(spec)
        spec.loader.exec_module(module)
        output = self.root / "assets"
        output.mkdir()
        for target in module.TARGETS:
            root = self.root / target
            root.mkdir()
            executable = "b24cli.exe" if target.startswith("windows") else "b24cli"
            (root / executable).write_bytes(b"compressed executable")
            for name in ["build-info.json", "runtime-sources.json", "THIRD-PARTY-NOTICES.md", "YIIPRESS-LICENSE.txt"]:
                (root / name).write_text("{}")
            for name in ["runtime-licenses", "compression-licenses", "uncompressed"]:
                (root / name).mkdir()
                (root / name / "fixture.txt").write_text("notice or diagnostic")
            report = {"executables": {executable: {"compressed_bytes": len(b"compressed executable"),
                      "original_bytes": 1000, "compressed_sha256": hashlib.sha256(b"compressed executable").hexdigest()}}}
            (root / "compression-info.json").write_text(json.dumps(report))
            (root / "runtime-probe.phar").write_bytes(b"diagnostic payload")
            archive = module.pack(self.root, target, output)
            if archive.suffix == ".zip":
                with zipfile.ZipFile(archive) as z:
                    names, final = z.namelist(), z.read(executable)
            else:
                with tarfile.open(archive) as t:
                    names, final = t.getnames(), t.extractfile(executable).read()
            self.assertEqual(final, b"compressed executable")
            self.assertIn("compression-info.json", names)
            self.assertTrue(any(name.startswith("compression-licenses/") for name in names))
            self.assertFalse(any(name.startswith("uncompressed/") or name.endswith(".phar") for name in names))

    def test_macos_packer_failure_does_not_replace_original(self):
        binary = self.root / "b24cli"
        binary.write_bytes(b"signed original")
        with patch("compress.install_wrappe", return_value=self.root / "wrappe"), \
             patch("compress.subprocess.run", side_effect=subprocess.CalledProcessError(1, "wrappe")):
            with self.assertRaises(subprocess.CalledProcessError):
                compression.pack_macos(self.root, binary, {})
        self.assertEqual(binary.read_bytes(), b"signed original")


if __name__ == "__main__":
    unittest.main()
