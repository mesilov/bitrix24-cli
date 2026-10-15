#!/usr/bin/env python3
"""Offline installer contract; optional real native package through controlled curl."""
import argparse
import contextlib
import hashlib
import http.server
import io
import json
import os
from pathlib import Path
import platform
import re
import shutil
import subprocess
import tarfile
import tempfile
import threading
import time
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
      --write-out|--proto|--proto-redir|--connect-timeout|--max-time|--retry|--retry-max-time) shift 2;;
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

    @contextlib.contextmanager
    def real_transport(self, failures, refused=False):
        """Run real curl retries; only the fixture URL/HTTPS transport is adapted."""
        counts = {}
        assets = self.assets

        class Handler(http.server.BaseHTTPRequestHandler):
            def log_message(self, *unused):
                pass

            def do_GET(self):
                counts[self.path] = counts.get(self.path, 0) + 1
                pending = failures.get(self.path, [])
                status = pending.pop(0) if pending else 200
                if isinstance(status, int) and status != 200:
                    self.send_response(status)
                    self.send_header('Content-Length', '0')
                    self.end_headers()
                    return
                if self.path == '/latest':
                    self.send_response(302)
                    self.send_header('Location', '/tag/0.2.0')
                    self.end_headers()
                    return
                body = b'release' if self.path == '/tag/0.2.0' else (assets / self.path.rsplit('/', 1)[-1]).read_bytes()
                self.send_response(200)
                self.send_header('Content-Length', str(len(body)))
                self.end_headers()
                try:
                    if status == 'timeout':
                        self.wfile.write(body[:8])
                        self.wfile.flush()
                        time.sleep(0.4)
                        self.wfile.write(body[8:])
                    else:
                        self.wfile.write(body)
                except (BrokenPipeError, ConnectionResetError):
                    pass

        server = http.server.ThreadingHTTPServer(('127.0.0.1', 0), Handler, bind_and_activate=False)
        server.server_bind()
        endpoint = 'http://127.0.0.1:' + str(server.server_address[1])
        if refused:
            address = server.server_address
            server.server_close()
            server = http.server.ThreadingHTTPServer(address, Handler, bind_and_activate=False)

        def serve():
            if refused:
                marker = Path(self.env['FIXTURE_REFUSED_MARKER'])
                deadline = time.monotonic() + 10
                while time.monotonic() < deadline:
                    if marker.exists():
                        counts['connection-refused-observed'] = True
                        break
                    time.sleep(0.01)
                server.server_bind()
            server.server_activate()
            server.serve_forever(poll_interval=0.05)

        thread = threading.Thread(target=serve, daemon=True)
        thread.start()
        self.env.update(FIXTURE_ENDPOINT=endpoint, FIXTURE_REAL_CURL=shutil.which('curl', path='/usr/bin:/bin'))
        self.script('curl', '#!' + os.sys.executable + '\n' + '''import os, pathlib, subprocess, sys, threading
base = 'https://github.com/mesilov/bitrix24-cli/releases'
endpoint = os.environ['FIXTURE_ENDPOINT']
original = sys.argv[1:]
args = [endpoint + arg[len(base):] if arg.startswith(base + '/') else arg for arg in original]
args += ['--proto', '=http,https', '--proto-redir', '=http,https']
if os.environ.get('FIXTURE_SHORT_TIMEOUT'):
    args += ['--max-time', '0.1']
process = subprocess.Popen([os.environ['FIXTURE_REAL_CURL'], *args], stdout=subprocess.PIPE, stderr=subprocess.PIPE)
def forward_errors():
    for line in process.stderr:
        sys.stderr.buffer.write(line)
        sys.stderr.buffer.flush()
        if b'curl: (7)' in line and os.environ.get('FIXTURE_REFUSED_MARKER'):
            pathlib.Path(os.environ['FIXTURE_REFUSED_MARKER']).touch()
errors = threading.Thread(target=forward_errors)
errors.start()
output = process.stdout.read()
status = process.wait()
errors.join()
sys.stdout.buffer.write(output.replace(endpoint.encode(), base.encode()))
sys.exit(status)
''')
        try:
            yield counts
        finally:
            server.shutdown()
            server.server_close()
            thread.join(timeout=2)

    def test_transient_http_recovery_with_real_curl(self):
        for path, status in [('/latest', 504), ('/download/0.2.0/b24cli-linux-amd64.tar.gz', 503),
                             ('/download/0.2.0/SHA256SUMS', 429), ('/latest', 408),
                             ('/download/0.2.0/b24cli-linux-amd64.tar.gz', 500),
                             ('/download/0.2.0/SHA256SUMS', 502)]:
            with self.subTest(path=path, status=status), self.real_transport({path: [status]}) as counts:
                self.run_installer('--no-modify-path')
                self.assertEqual(counts[path], 2)

    def test_partial_download_timeout_with_real_curl(self):
        path = '/download/0.2.0/b24cli-linux-amd64.tar.gz'
        self.env['FIXTURE_SHORT_TIMEOUT'] = '1'
        with self.real_transport({path: ['timeout']}) as counts:
            self.run_installer('--no-modify-path')
            self.assertEqual(counts[path], 2)

    def test_refused_connection_with_real_curl(self):
        self.env['FIXTURE_REFUSED_MARKER'] = str(self.root / 'connection refused')
        with self.real_transport({}, refused=True) as counts:
            self.run_installer('--no-modify-path')
            self.assertEqual(counts['/latest'], 1)
            self.assertTrue(counts.get('connection-refused-observed'))

    def test_network_failures_preserve_install_with_real_curl(self):
        for status, attempts in [(504, 4), (404, 1)]:
            for path in ['/latest', '/download/0.2.0/b24cli-linux-amd64.tar.gz', '/download/0.2.0/SHA256SUMS']:
                with self.subTest(path=path, status=status):
                    before = self.sentinel()
                    for name in ['.local/share/b24cli/env', '.bashrc', '.profile', 'zsh settings/.zshrc']:
                        entry = self.home / name
                        entry.parent.mkdir(parents=True, exist_ok=True)
                        entry.write_text('previous ' + name)
                        before[name] = entry.read_bytes()
                    with self.real_transport({path: [status] * attempts}) as counts:
                        self.run_installer(success=False)
                        self.assertEqual(counts[path], attempts)
                    self.unchanged(before)

    def test_documented_shell_sequences(self):
        blocks = []
        for document in ['README.md', 'docs/install.md']:
            for block in re.findall(r'```sh\n(.*?)\n```', (ROOT / document).read_text(), re.S):
                if 'raw.githubusercontent.com/mesilov/bitrix24-cli/main/install.sh' in block or block.startswith('sh install.sh --install-dir'):
                    blocks.append((document, block))
        self.assertEqual(len(blocks), 5)
        shells = [shutil.which('bash'), shutil.which('zsh')]
        self.assertTrue(all(shells), 'Bash and zsh are required for documentation acceptance')
        activated = self.root / 'activated'
        invoked = self.root / 'invoked'
        active_bin = self.root / 'activated bin'
        active_bin.mkdir()
        version_script = '#!/bin/sh\nprintf invoked > "$FIXTURE_INVOKED"\nprintf "b24cli fixture\\n"\n'
        self.script('b24cli', version_script)
        (active_bin / 'b24cli').write_text(version_script)
        (active_bin / 'b24cli').chmod(0o755)
        share = self.home / '.local/share/b24cli'
        share.mkdir(parents=True)
        (share / 'env').write_text('''printf activated > "$FIXTURE_ACTIVATED"
[ "$FIXTURE_DOC_MODE" != activation ] || return 9
export PATH="$FIXTURE_ACTIVE_BIN:$PATH"
''')
        self.script('less', '#!/bin/sh\nexit 0\n')
        self.script('curl', '''#!/bin/sh
out=
while [ "$#" -gt 0 ]; do
    case "$1" in -o|--output) out=$2; shift 2;; *) shift;; esac
done
[ "$FIXTURE_DOC_MODE" != download ] || { printf 'exit 0\\n'; exit 22; }
if [ -n "$out" ]; then cp "$FIXTURE_DOC_SCRIPT" "$out"; else cat "$FIXTURE_DOC_SCRIPT"; fi
''')
        script = self.root / 'document fixture.sh'
        script.write_text('[ "$FIXTURE_DOC_MODE" != install ] || exit 1\nexit 0\n')
        self.env.update(FIXTURE_ACTIVATED=str(activated), FIXTURE_INVOKED=str(invoked),
                        FIXTURE_ACTIVE_BIN=str(active_bin), FIXTURE_DOC_SCRIPT=str(script))
        for document, block in blocks:
            for shell in shells:
                for mode in ['download', 'install', 'activation', 'success']:
                    if mode == 'download' and 'curl ' not in block:
                        continue
                    with self.subTest(document=document, shell=shell, mode=mode, block=block.splitlines()[0]):
                        activated.unlink(missing_ok=True)
                        invoked.unlink(missing_ok=True)
                        shutil.copyfile(script, self.root / 'install.sh')
                        self.env['FIXTURE_DOC_MODE'] = mode
                        command = block + '\ntask_status=$?\n[ "$task_status" -eq 0 ] || exit "$task_status"\ncommand -v b24cli\n'
                        result = subprocess.run([shell, '-f', '-c', command], env=self.env, cwd=self.root,
                                                text=True, capture_output=True)
                        if mode == 'success':
                            self.assertEqual(result.returncode, 0, result.stderr)
                            self.assertTrue(activated.exists())
                            self.assertEqual(result.stdout.strip().splitlines()[-1], str(active_bin / 'b24cli'))
                        else:
                            self.assertNotEqual(result.returncode, 0)
                            self.assertEqual(activated.exists(), mode == 'activation')
                            self.assertFalse(invoked.exists(), result.stdout + result.stderr)

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
