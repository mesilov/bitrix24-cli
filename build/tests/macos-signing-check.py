#!/usr/bin/env python3
"""Use Apple's linker/codesign and real payload execution to guard finalization."""
from pathlib import Path
import platform
import shutil
import struct
import subprocess
import sys
import tempfile
import unittest

sys.path.insert(0, str(Path(__file__).resolve().parents[1]))
from macos import finalize, layout, verify


@unittest.skipUnless(platform.system() == 'Darwin' and platform.machine() == 'arm64',
                     'Native macOS arm64 fixture requires Apple linker and codesign')
class MacOSSigningChecks(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.fixture = tempfile.TemporaryDirectory(prefix='b24cli-macho-fixture-')
        cls.addClassCleanup(cls.fixture.cleanup)
        cls.root = Path(cls.fixture.name)
        source = cls.root / 'fixture.c'
        source.write_text(r'''
#include <mach-o/getsect.h>
#include <mach-o/ldsyms.h>
#include <stdint.h>
#include <stdio.h>
__attribute__((used, section("__DATA,__micro_sfxsize")))
static const unsigned char bounds[16] = {0};
int main(int argc, char **argv) {
    unsigned long size = 0;
    const unsigned char *p = (const unsigned char *)getsectiondata(&_mh_execute_header, "__DATA", "__micro_sfxsize", &size);
    if (!p || size != 16) return 2;
    uint64_t start = 0, end = 0;
    for (int i = 0; i < 8; i++) { start = (start << 8) | p[i]; end = (end << 8) | p[i + 8]; }
    if (!start && !end) { puts("runtime placeholder"); return 0; }
    FILE *f = fopen(argv[0], "rb");
    if (!f || end <= start || fseek(f, start, SEEK_SET)) return 3;
    for (uint64_t i = start; i < end; i++) { int c = fgetc(f); if (c == EOF) return 4; putchar(c); }
    return fclose(f);
}
''')
        cls.runtime = cls.root / 'micro.sfx'
        subprocess.run(['clang', '-O2', str(source), '-o', str(cls.runtime)], check=True)
        cls.payload = cls.root / 'payload.phar'
        cls.payload.write_bytes('Привет, bounded PHAR fixture\n'.encode())

    def setUp(self):
        self.directory = tempfile.TemporaryDirectory(prefix='b24cli-signed-')
        self.addCleanup(self.directory.cleanup)
        self.binary = Path(self.directory.name) / 'b24cli'
        shutil.copy2(self.runtime, self.binary)
        with self.binary.open('ab') as output:
            output.write(self.payload.read_bytes())

    def test_runtime_placeholder_and_signed_payload_execute(self):
        self.assertEqual(subprocess.check_output([str(self.runtime)]), b'runtime placeholder\n')
        self.assertNotEqual(subprocess.run(['codesign', '--verify', '--strict', str(self.binary)],
                                          capture_output=True).returncode, 0)
        finalize(self.binary, self.payload)
        self.assertEqual(subprocess.check_output([str(self.binary)]), self.payload.read_bytes())
        verify(self.binary, self.payload)
        self.assertEqual(self.binary.stat().st_mode & 0o777, 0o755)

    def test_signed_payload_tampering_fails_strict_validation(self):
        finalize(self.binary, self.payload)
        data = bytearray(self.binary.read_bytes())
        metadata = layout(data)[3]
        start, _ = struct.unpack_from('>QQ', data, metadata)
        data[start] ^= 1
        self.binary.write_bytes(data)
        result = subprocess.run(['codesign', '--verify', '--strict', str(self.binary)], capture_output=True)
        self.assertNotEqual(result.returncode, 0)
        with self.assertRaisesRegex(ValueError, 'payload bounds or bytes'):
            verify(self.binary, self.payload)

    def test_mismatched_payload_is_rejected_without_modifying_binary(self):
        before = self.binary.read_bytes()
        wrong = Path(self.directory.name) / 'wrong.phar'
        wrong.write_bytes(b'wrong PHAR')
        with self.assertRaisesRegex(ValueError, 'exactly the original PHAR'):
            finalize(self.binary, wrong)
        self.assertEqual(self.binary.read_bytes(), before)

    def test_invalid_layouts_are_rejected_without_modification(self):
        original = self.binary.read_bytes()
        commands, _, _, metadata = layout(original)
        fixtures = [original[:16], b'\x00' * 4 + original[4:]]
        for location, value in [(20, len(original)), (commands[0][0] + 4, 0)]:
            data = bytearray(original)
            struct.pack_into('<I', data, location, value)
            fixtures.append(data)
        data = bytearray(original)
        data[metadata] = 1
        fixtures.append(data)
        data = bytearray(original)
        name_offset = data.index(b'__micro_sfxsize', 32, 32 + struct.unpack_from('<I', data, 20)[0])
        data[name_offset:name_offset + 16] = b'__absent_bounds'.ljust(16, b'\x00')
        fixtures.append(data)
        for data in fixtures:
            with self.subTest(size=len(data)):
                self.binary.write_bytes(data)
                with self.assertRaises(ValueError):
                    finalize(self.binary, self.payload)
                self.assertEqual(self.binary.read_bytes(), data)

    def test_data_appended_after_final_signature_is_rejected(self):
        finalize(self.binary, self.payload)
        with self.binary.open('ab') as output:
            output.write(b'unsigned trailing data')
        with self.assertRaisesRegex(ValueError, 'Unsigned data follows'):
            verify(self.binary, self.payload)


if __name__ == '__main__':
    unittest.main()
