#!/usr/bin/env python3
"""Offline installer contract; optional real native package through controlled curl."""
import argparse
import hashlib
import io
import json
import os
from pathlib import Path
import platform
import shutil
import subprocess
import tarfile
import tempfile
import unittest

ROOT = Path(__file__).resolve().parents[2]
BASE = 'https://github.com/mesilov/bitrix24-cli/releases'
parser = argparse.ArgumentParser()
parser.add_argument('--dist', type=Path)
parser.add_argument('--assets', type=Path)
parser.add_argument('--version')
args = parser.parse_args()
NATIVE_TARGET = 'macos-arm64' if platform.system() == 'Darwin' else 'linux-amd64'


class InstallerTests(unittest.TestCase):
    def setUp(self):
        self.tmp = tempfile.TemporaryDirectory(prefix='b24cli installer ')
        self.addCleanup(self.tmp.cleanup)
        self.root = Path(self.tmp.name).resolve()
        self.home = self.root / "user's home with spaces"
        self.home.mkdir()
        self.tools = self.root / 'tools'
        self.tools.mkdir()
        self.assets = self.root / 'assets'
        self.assets.mkdir()
        self.log = self.root / 'requests'
        self.env = {'HOME': str(self.home), 'PATH': str(self.tools) + ':/usr/bin:/bin',
                    'TMPDIR': str(self.root), 'FIXTURE_ASSETS': str(self.assets),
                    'FIXTURE_LOG': str(self.log), 'FIXTURE_TAG': '0.2.0',
                    'FIXTURE_OS': 'Linux', 'FIXTURE_ARCH': 'x86_64',
                    'ZDOTDIR': str(self.home / 'zsh settings'), 'LC_ALL': 'C'}
        self.script('uname', '#!/bin/sh\ncase "$1" in -s) echo "$FIXTURE_OS";; -m) echo "$FIXTURE_ARCH";; esac\n')
        self.script('curl', '''#!/bin/sh
out=
url=
while [ "$#" -gt 0 ]; do
    case "$1" in
      --output) out=$2; shift 2;;
      --write-out|--proto|--proto-redir|--connect-timeout|--max-time) shift 2;;
      https:*) url=$1; shift;;
      *) shift;;
    esac
done
printf '%s\\n' "$url" >> "$FIXTURE_LOG"
[ "${FIXTURE_DOWNLOAD_FAIL:-}" != "${url##*/}" ] || exit 22
case "$url" in
 */latest) printf 'https://github.com/mesilov/bitrix24-cli/releases/tag/%s' "$FIXTURE_TAG";;
 */download/"$FIXTURE_TAG"/*) cp "$FIXTURE_ASSETS/${url##*/}" "$out";;
 *) exit 22;;
esac
''')
        if not shutil.which('sha256sum', path='/usr/bin:/bin'):
            self.script('sha256sum', '#!/bin/sh\nexec /usr/bin/shasum -a 256 "$@"\n')
        self.package()

    def script(self, name, text):
        path = self.tools / name
        path.write_text(text)
        path.chmod(0o755)

    def package(self, version='0.2.0', target='linux-amd64', mode=None):
        name = 'b24cli-' + target + '.tar.gz'
        archive = self.assets / name
        files = {'b24cli': '#!/bin/sh\nprintf "b24cli ' + version + '\\n"\n',
                 'build-info.json': json.dumps({'version': version}),
                 'runtime-sources.json': '{}', 'compression-info.json': '{}',
                 'THIRD-PARTY-NOTICES.md': 'notices',
                 'BITRIX24-CLI-LICENSE.txt': 'own license', 'YIIPRESS-LICENSE.txt': 'upstream',
                 'runtime-licenses/runtime.txt': 'runtime license',
                 'compression-licenses/packer.txt': 'packer license',
                 'compression-licenses/zstd-2.0+zstd.1.5.6/LICENSE': 'dependency license'}
        if mode == 'missing-license':
            del files['runtime-licenses/runtime.txt']
        if mode == 'candidate-failure':
            files['b24cli'] = '#!/bin/sh\nexit 9\n'
        if mode == 'staged-failure':
            files['b24cli'] = '#!/bin/sh\ncase "$0" in */.b24cli.*) exit 9;; esac\nprintf "b24cli 0.2.0\\n"\n'
        if mode == 'traversal':
            files['../escape'] = 'unsafe'
        with tarfile.open(archive, 'w:gz') as tar:
            for filename, value in files.items():
                data = value.encode()
                item = tarfile.TarInfo(filename)
                item.size = len(data)
                item.mode = 0o755 if filename == 'b24cli' else 0o644
                tar.addfile(item, io.BytesIO(data))
            if mode == 'symlink':
                item = tarfile.TarInfo('runtime-licenses/link')
                item.type = tarfile.SYMTYPE
                item.linkname = '/tmp/escape'
                tar.addfile(item)
        self.checksum(archive)
        return archive

    def checksum(self, archive):
        self.record = hashlib.sha256(archive.read_bytes()).hexdigest() + '  ' + archive.name + '\n'
        (self.assets / 'SHA256SUMS').write_text(self.record)

    def run_installer(self, *options, success=True):
        result = subprocess.run(['/bin/sh', str(ROOT / 'install.sh'), *options], env=self.env,
                                cwd=self.root, text=True, capture_output=True)
        if success:
            self.assertEqual(result.returncode, 0, result.stdout + result.stderr)
        else:
            self.assertNotEqual(result.returncode, 0, result.stdout)
            self.assertIn('b24cli installer:', result.stderr)
        self.assertFalse(list(self.root.glob('b24cli-install.*')))
        self.assertFalse(list((self.home / '.local/bin').glob('.b24cli.*')))
        self.assertFalse(list((self.home / '.local/share/b24cli').glob('.package.*')))
        return result

    def sentinel(self):
        paths = ['.local/bin/b24cli', '.config/b24cli/config.json',
                 '.local/share/b24cli/user-data', '.local/share/b24cli/package/old-license']
        for name in paths:
            path = self.home / name
            path.parent.mkdir(parents=True, exist_ok=True)
            path.write_text('previous ' + name)
        return {name: (self.home / name).read_bytes() for name in paths}

    def unchanged(self, before):
        for name, value in before.items():
            self.assertEqual((self.home / name).read_bytes(), value, name)

    def test_install_repeat_update_and_shells(self):
        before = self.sentinel()
        self.run_installer()
        self.run_installer()
        share = self.home / '.local/share/b24cli'
        self.assertTrue((share / 'package/compression-licenses/packer.txt').exists())
        self.assertEqual((self.home / '.local/bin/b24cli').stat().st_mode & 0o777, 0o755)
        for name in ['.bashrc', '.profile', 'zsh settings/.zshrc']:
            self.assertEqual((self.home / name).read_text().count('# b24cli installer'), 1)
        self.shells('0.2.0', self.home / '.local/bin')
        self.env['FIXTURE_TAG'] = 'v0.3.0-rc.1'
        self.package('v0.3.0-rc.1')
        self.run_installer('--version', 'v0.3.0-rc.1')
        self.shells('v0.3.0-rc.1', self.home / '.local/bin')
        for name in ['.config/b24cli/config.json', '.local/share/b24cli/user-data']:
            self.assertEqual((self.home / name).read_bytes(), before[name])
        requests = self.log.read_text().splitlines()
        self.assertEqual(requests[-2:], [BASE + '/download/v0.3.0-rc.1/b24cli-linux-amd64.tar.gz',
                                        BASE + '/download/v0.3.0-rc.1/SHA256SUMS'])

    def shells(self, version, install):
        env = dict(self.env, PATH='/usr/bin:/bin')
        command = '''cd /; b24cli --version --no-ansi; printf '%s\\n' "$PATH"'''
        activation = '. "$HOME/.local/share/b24cli/env"; . "$HOME/.local/share/b24cli/env"; '
        commands = [['/bin/sh', '-c', activation + command],
                    ['/bin/bash', '--noprofile', '--norc', '-c', '. "$HOME/.profile"; ' + command],
                    ['/bin/bash', '--noprofile', '--rcfile', str(self.home / '.bashrc'), '-ic', command]]
        # Linux login bash resets HOME through /etc/profile. Isolated startup checks
        # above cover the selected login file; native zsh also exercises login itself.
        if platform.system() == 'Darwin':
            commands.append(['/bin/bash', '-lc', command])
        if shutil.which('zsh'):
            commands.extend([[shutil.which('zsh'), '-ic', command],
                             [shutil.which('zsh'), '-lic', command]])
        for cmd in commands:
            result = subprocess.run(cmd, env=env, cwd=self.root, text=True, capture_output=True)
            self.assertEqual(result.returncode, 0, repr(cmd) + result.stderr)
            lines = result.stdout.strip().splitlines()
            self.assertEqual(lines[-2], 'b24cli ' + version)
            self.assertEqual(lines[-1].split(':').count(str(install)), 1)
            self.assertNotIn('.', lines[-1].split(':'))

    def test_custom_and_no_modify_profiles(self):
        for name in ['.bashrc', '.profile', '.bash_profile']:
            (self.home / name).write_text('echo original\n')
        before = {name: (self.home / name).read_bytes() for name in ['.bashrc', '.profile', '.bash_profile']}
        custom = self.root / "custom ' binary dir"
        result = self.run_installer('--install-dir', str(custom), '--no-modify-path')
        self.unchanged(before)
        self.assertFalse((self.home / 'zsh settings/.zshrc').exists())
        self.assertIn(str(custom) + '/b24cli', result.stdout)
        result = subprocess.run(['/bin/sh', '-c', '. "$HOME/.local/share/b24cli/env"; cd /; b24cli --version'],
                                env=self.env, text=True, capture_output=True)
        self.assertEqual(result.stdout.strip(), 'b24cli 0.2.0', result.stderr)
        self.run_installer('--install-dir', str(custom))
        self.assertIn('# b24cli installer', (self.home / '.bash_profile').read_text())
        self.assertEqual((self.home / '.profile').read_bytes(), before['.profile'])

    def test_macos_target(self):
        self.env.update(FIXTURE_OS='Darwin', FIXTURE_ARCH='arm64')
        self.package(target='macos-arm64')
        self.run_installer('--no-modify-path')
        self.assertIn('b24cli-macos-arm64.tar.gz', self.log.read_text())

    def test_failures_preserve_previous_install(self):
        for scenario in ['download', 'latest', 'checksum-download', 'missing', 'malformed', 'duplicate',
                         'mismatch', 'invalid-archive', 'version', 'candidate-failure',
                         'missing-license', 'traversal', 'symlink', 'unsupported', 'bad-tag', 'relative-dir', 'staged-failure', 'unpack']:
            with self.subTest(scenario=scenario):
                before = self.sentinel()
                self.env.pop('FIXTURE_DOWNLOAD_FAIL', None)
                self.env.update(FIXTURE_ARCH='x86_64', FIXTURE_TAG='0.2.0')
                archive = self.package(mode=scenario)
                options = []
                if scenario == 'download': self.env['FIXTURE_DOWNLOAD_FAIL'] = archive.name
                if scenario == 'latest': self.env['FIXTURE_DOWNLOAD_FAIL'] = 'latest'
                if scenario == 'checksum-download': self.env['FIXTURE_DOWNLOAD_FAIL'] = 'SHA256SUMS'
                if scenario == 'missing': (self.assets / 'SHA256SUMS').write_text('')
                if scenario == 'malformed': (self.assets / 'SHA256SUMS').write_text('nohash  ' + archive.name + '\n')
                if scenario == 'duplicate': (self.assets / 'SHA256SUMS').write_text(self.record * 2)
                if scenario == 'mismatch': (self.assets / 'SHA256SUMS').write_text('0' * 64 + '  ' + archive.name + '\n')
                if scenario == 'invalid-archive': archive.write_text('invalid'); self.checksum(archive)
                if scenario == 'version': self.package(version='0.0.0')
                if scenario == 'unsupported': self.env['FIXTURE_ARCH'] = 'aarch64'
                if scenario == 'bad-tag': options = ['--version', '../bad']
                if scenario == 'relative-dir': options = ['--install-dir', '.']
                if scenario == 'unpack':
                    real_tar = shutil.which('tar')
                    self.script('tar', '#!/bin/sh\n[ "$1" != -xzf ] || exit 9\nexec ' + real_tar + ' "$@"\n')
                self.run_installer(*options, success=False)
                self.unchanged(before)

    def test_prerequisite_failure(self):
        before = self.sentinel()
        isolated = self.root / 'minimal tools'
        isolated.mkdir()
        for utility in ['uname', 'cat', 'tar', 'awk', 'sed', 'grep', 'mktemp', 'cp', 'mv', 'chmod', 'mkdir', 'rm', 'ls']:
            source = self.tools / utility if (self.tools / utility).exists() else Path(shutil.which(utility))
            (isolated / utility).symlink_to(source)
        self.env['PATH'] = str(isolated)
        result = self.run_installer(success=False)
        self.assertIn('required utility missing: curl', result.stderr)
        self.unchanged(before)

    def test_cli_errors(self):
        for options in [('--version',), ('--install-dir',), ('--unknown',),
                        ('--install-dir', '/tmp/path:colon'), ('--install-dir', '/tmp/path\nnewline')]:
            self.run_installer(*options, success=False)
        self.run_installer('--help')

    @unittest.skipUnless(args.dist or args.assets, 'native package supplied only in platform acceptance')
    def test_native_archive(self):
        if args.dist:
            metadata = json.loads((args.dist / 'build-info.json').read_text())
            version = metadata['version']
            subprocess.run([os.sys.executable, str(ROOT / 'build/release-assets.py'),
                            str(args.dist.resolve().parent), str(self.assets), '--target', NATIVE_TARGET], check=True)
        else:
            version = args.version
            self.assertTrue(version)
            for name in ['b24cli-' + NATIVE_TARGET + '.tar.gz', 'SHA256SUMS']:
                shutil.copyfile(args.assets / name, self.assets / name)
        self.env.update(FIXTURE_OS=platform.system(), FIXTURE_ARCH=platform.machine(), FIXTURE_TAG=version)
        self.run_installer('--version', version)
        self.run_installer('--version', version)
        self.shells(version, self.home / '.local/bin')
        # Custom destination and opt-out use the same real archive.
        self.run_installer('--version', version, '--install-dir', str(self.root / 'native custom bin'), '--no-modify-path')
        print('Native installer archive verified:', NATIVE_TARGET, version,
              hashlib.sha256((self.assets / ('b24cli-' + NATIVE_TARGET + '.tar.gz')).read_bytes()).hexdigest())


if __name__ == '__main__':
    unittest.main(argv=['shell-installer-check'], verbosity=2)
