#!/usr/bin/env python3
"""Verify an existing RC archive set and its native copied CLI without rebuilding."""
import argparse
import hashlib
import json
import os
from pathlib import Path
import platform
import shutil
import subprocess
import sys
import tarfile
import tempfile
import zipfile


def check(condition, message):
    if not condition:
        raise AssertionError(message)


def main():
    sys.stdout.reconfigure(encoding='utf-8')
    parser = argparse.ArgumentParser()
    parser.add_argument('assets', type=Path)
    parser.add_argument('--target', choices=['linux-amd64', 'macos-arm64', 'windows-amd64'], required=True)
    parser.add_argument('--expected-version', required=True)
    parser.add_argument('--source-sha', required=True)
    parser.add_argument('--expected-commands', type=int, required=True)
    parser.add_argument('--output', type=Path, required=True)
    args = parser.parse_args()
    assets = args.assets.resolve()
    checker = Path(__file__).resolve().with_name('archive-check.py')
    subprocess.run([sys.executable, str(checker), str(assets)], check=True)
    checksums = dict(line.split('  ', 1)[::-1] for line in (assets / 'SHA256SUMS').read_text().splitlines())
    archive_name = 'b24cli-' + args.target + ('.zip' if args.target == 'windows-amd64' else '.tar.gz')
    checks = ['three-archive-checksums', 'archive-contents-permissions-licenses', 'corruption-rejection']
    with tempfile.TemporaryDirectory(prefix='b24cli-rc-native-') as temporary:
        root = Path(temporary).resolve()
        extracted = root / 'archive'
        extracted.mkdir()
        archive_path = assets / archive_name
        if archive_name.endswith('.zip'):
            with zipfile.ZipFile(archive_path) as archive:
                for name in archive.namelist():
                    target = (extracted / name).resolve()
                    check(target.is_relative_to(extracted) and not Path(name).is_absolute(), 'Unsafe ZIP path')
                archive.extractall(extracted)
        else:
            with tarfile.open(archive_path, 'r:gz') as archive:
                archive.extractall(extracted, filter='data')
        metadata = json.loads((extracted / 'build-info.json').read_text())
        check(metadata['version'] == args.expected_version, 'Metadata version mismatch')
        check(metadata['source_sha'] == args.source_sha, 'Metadata source SHA mismatch')
        check(metadata['php_version'] == '8.5.11', 'Unexpected PHP runtime')
        checks.append('exact-version-source-runtime-metadata')
        clean = root / 'Кандидат RC вне checkout'
        clean.mkdir()
        binary_name = 'b24cli.exe' if args.target == 'windows-amd64' else 'b24cli'
        binary = clean / binary_name
        shutil.copy2(extracted / binary_name, binary)
        binary_sha256 = hashlib.sha256(binary.read_bytes()).hexdigest()
        compression = json.loads((extracted / 'compression-info.json').read_text())
        check(binary_sha256 == compression['executables'][binary_name]['compressed_sha256'], 'Binary hash mismatch')
        if args.target == 'macos-arm64':
            subprocess.run(['codesign', '--verify', '--strict', str(binary)], check=True)
            checks.append('macos-strict-signature')
        env = {k: v for k, v in os.environ.items() if k.upper() in {'SYSTEMROOT', 'WINDIR', 'LANG', 'LC_ALL'}}
        user = root / 'user'
        env.update({'PATH': str(clean), 'HOME': str(user), 'APPDATA': str(user / 'Roaming'),
                    'LOCALAPPDATA': str(user / 'Local'), 'USERPROFILE': str(user),
                    'TMPDIR': str(clean), 'TMP': str(clean), 'TEMP': str(clean),
                    'NO_COLOR': '1', 'TERM': 'dumb'})
        invocations = 0

        def invoke(*arguments, expected=0):
            nonlocal invocations
            result = subprocess.run([str(binary), *arguments, '--no-ansi'], cwd=clean, env=env,
                                    text=True, encoding='utf-8', capture_output=True, timeout=30)
            invocations += 1
            check(result.returncode == expected,
                  f'{arguments}: exit {result.returncode}; {result.stdout}; {result.stderr}')
            return result.stdout

        version = invoke('--version').strip()
        check(version == 'b24cli ' + args.expected_version, 'CLI version mismatch: ' + version)
        check(args.expected_version in invoke('--about'), 'About version differs from --version')
        help_text = invoke('--help')
        check(help_text == invoke('-h'), 'Root help forms differ')
        check(invoke('list', '--help') == invoke('help', 'list'), 'List help forms differ')
        commands = [line.split()[0] for line in invoke('list', '--raw').splitlines() if line.strip()]
        check(len(commands) == args.expected_commands and len(commands) == len(set(commands)), 'Catalog count differs')
        check('task:status:durations' in commands and 'task:my' in commands, 'RC commands missing')
        for command in commands:
            check(command in help_text, 'Root help omits ' + command)
            check(invoke(command, '--help') == invoke('help', command), 'Help forms differ: ' + command)
        checks.extend(['version-about-agreement', 'both-root-help-forms', 'both-list-help-forms',
                       'complete-command-catalog', 'both-help-forms-for-every-public-command'])
        dry = json.loads(invoke('task:add', '--title', 'Проверка RC без записи', '--creator', '1',
                                '--responsible', '2', '--dry-run', '--json'))
        check(dry['error'] is None and dry['meta']['dryRun'] and dry['meta']['apiCalls'] == [], 'Offline dry-run differs')
        for arguments in [('несуществующая-команда',), ('task:my',), ('task:status:durations',)]:
            usage = json.loads(invoke(*arguments, '--json', expected=2))
            check(usage['error']['code'] == 'usage-error' and usage['meta']['apiCalls'] == [], 'Usage validation differs')
        missing = json.loads(invoke('task:show', '123', '--json', expected=1))
        check(missing['error']['code'] == 'connection-unavailable' and missing['meta']['apiCalls'] == [],
              'Missing credentials did not stop before API')
        checks.extend(['unicode-offline-json-dry-run', 'usage-errors-before-credentials-api',
                       'missing-connection-zero-api-calls', 'outside-checkout-clean-path-no-credentials'])
        receipt = {'status': 'passed', 'target': args.target, 'os': platform.platform(),
                   'architecture': platform.machine(), 'version': args.expected_version,
                   'source_sha': args.source_sha, 'archive': archive_name,
                   'archive_sha256': checksums[archive_name], 'binary_sha256': binary_sha256,
                   'all_archive_checksums': checksums, 'public_commands': len(commands),
                   'command_help_invocations': len(commands) * 2, 'total_cli_invocations': invocations,
                   'commands': commands, 'checks': checks, 'build_metadata': metadata}
    args.output.parent.mkdir(parents=True, exist_ok=True)
    args.output.write_text(json.dumps(receipt, ensure_ascii=False, indent=2) + '\n', encoding='utf-8')
    print(json.dumps({k: v for k, v in receipt.items() if k not in {'commands', 'build_metadata'}}, ensure_ascii=False))


if __name__ == '__main__':
    main()
