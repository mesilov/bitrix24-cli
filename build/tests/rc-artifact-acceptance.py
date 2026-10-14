#!/usr/bin/env python3
"""Verify an existing RC archive set and its native copied CLI without rebuilding."""
import argparse
import hashlib
import json
import os
from pathlib import Path
import platform
import re
import shlex
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
        about = invoke('--about', '--env-file', str(clean / 'missing.env'))
        for text in ['CLI для работы с задачами, сотрудниками и подключениями Битрикс24.',
                     'Версия: ' + args.expected_version, 'Maksim Mesilov',
                     'bitrix24-cli-license', 'BSD-3-Clause',
                     'https://github.com/mesilov/bitrix24-cli',
                     'https://github.com/mesilov/bitrix24-cli-skill']:
            check(text in about, 'About omits ' + text)
        help_text = invoke('--help')
        check(help_text == invoke('-h'), 'Root help forms differ')
        check(invoke('list', '--help') == invoke('help', 'list'), 'List help forms differ')
        commands = [line.split()[0] for line in invoke('list', '--raw').splitlines() if line.strip()]
        check(len(commands) == args.expected_commands and len(commands) == len(set(commands)), 'Catalog count differs')
        check('task:status:durations' in commands and 'task:my' in commands, 'RC commands missing')
        catalog = json.loads(invoke('list', '--format=json'))
        definitions = {c['name']: c for c in catalog['commands'] if not c['hidden']}
        check(set(definitions) == set(commands), 'JSON and raw command catalogs differ')
        repository = checker.parents[2]
        command_map = (repository / 'docs/cli-command-map.md').read_text(encoding='utf-8')
        readme = (repository / 'README.md').read_text(encoding='utf-8')
        pattern = r'^\| (?:\[)?`([^`]+)`'
        for name, document in [('README', readme), ('command map', command_map)]:
            names = re.findall(pattern, document, re.MULTILINE)
            check(sorted(names) == sorted(commands), name + ' command catalog differs')
        examples = re.findall(r'\| `(b24cli [^`]+)` \|', command_map)
        check(len(examples) == len(commands), 'Command map example count differs')
        for example in examples:
            tokens = shlex.split(example.replace('\\|', '|'))
            definition = definitions[tokens[1]]['definition']
            arguments = []
            index = 2
            while index < len(tokens):
                token = tokens[index]
                if token.startswith('--'):
                    option, separator, value = token[2:].partition('=')
                    check(option in definition['options'], 'Unknown documented option: ' + example)
                    details = definition['options'][option]
                    if details['is_value_required'] and not separator:
                        index += 1
                        check(index < len(tokens) and not tokens[index].startswith('--'),
                              'Missing documented option value: ' + example)
                    check(details['accept_value'] or not separator, 'Unexpected option value: ' + example)
                else:
                    check(not token.startswith('-'), 'Unsupported documented short option: ' + example)
                    arguments.append(token)
                index += 1
            argument_definition = definition['arguments']
            positional = list(argument_definition.values()) if isinstance(argument_definition, dict) else argument_definition
            required = sum(a['is_required'] for a in positional)
            check(len(arguments) >= required, 'Missing documented argument: ' + example)
            check(any(a['is_array'] for a in positional) or len(arguments) <= len(positional),
                  'Extra documented argument: ' + example)
        for command in commands:
            check(command in help_text, 'Root help omits ' + command)
            direct_help = invoke(command, '--help')
            check(direct_help == invoke('help', command), 'Help forms differ: ' + command)
            if ':' in command:
                check('Примеры:' in direct_help, 'Command omits examples: ' + command)
        checks.extend(['version-about-agreement', 'both-root-help-forms', 'both-list-help-forms',
                       'complete-command-catalog', 'both-help-forms-for-every-public-command',
                       'about-purpose-author-license-links-with-missing-env',
                       'readme-map-json-raw-catalog-agreement', 'documented-example-syntax'])
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
        receipt['documented_examples'] = len(examples)
        receipt['command_map_sha256'] = hashlib.sha256(command_map.encode('utf-8')).hexdigest()
    args.output.parent.mkdir(parents=True, exist_ok=True)
    args.output.write_text(json.dumps(receipt, ensure_ascii=False, indent=2) + '\n', encoding='utf-8')
    print(json.dumps({k: v for k, v in receipt.items() if k not in {'commands', 'build_metadata'}}, ensure_ascii=False))


if __name__ == '__main__':
    main()
