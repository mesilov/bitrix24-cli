#!/usr/bin/env python3
"""Native connection acceptance using copied binaries and the packaged SDK fixture.

No real portal, PHP CLI or downloaded credential helper is used. Windows ACL
checks invoke the inbox Windows PowerShell through its absolute SystemRoot path.
"""
import argparse
import base64
from concurrent.futures import ThreadPoolExecutor
import hashlib
import json
import os
from pathlib import Path
import shutil
import subprocess
import tempfile
import time


def check_scope_calls(result, cold):
    methods = (['scope'] if cold else []) + ['tasks.task.list']
    expected = [{'method': method, 'apiVersion': '1.0' if method == 'scope' else '3.0',
                 'effect': 'read'} for method in methods]
    assert result['meta']['apiCalls'] == expected, 'Cold/warm scope API sequence differs: ' + repr(result['meta']['apiCalls'])
    scope = result['meta']['scopeValidation']
    assert scope['status'] == 'fresh' and scope['missingScopes'] == [], 'Scope fixture did not verify grants'
    assert scope['requiredScopeGroups'] == [['task', 'tasks']], 'Task scope requirements differ'


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument('dist', type=Path)
    parser.add_argument('--phar', action='store_true')
    parser.add_argument('--php', default='php')
    args = parser.parse_args()
    suffix = '.phar' if args.phar else ('.exe' if os.name == 'nt' else '')
    prefix = [str(Path(shutil.which(args.php)).resolve())] if args.phar else []
    with tempfile.TemporaryDirectory(prefix='b24cli-connection-') as directory:
        root = Path(directory).resolve()
        binaries = root / 'bin'
        binaries.mkdir()
        for name in ['b24cli', 'runtime-probe']:
            shutil.copy2(args.dist.resolve() / (name + suffix), binaries / (name + suffix))
        hashes = {p.name: hashlib.sha256(p.read_bytes()).hexdigest() for p in binaries.iterdir()}
        home = root / 'user home'
        config = home / '.config' / 'b24cli' if os.name != 'nt' else home / 'Roaming' / 'b24cli'
        temp = root / 'temporary'
        temp.mkdir()
        env = {k: v for k, v in os.environ.items() if k.upper() in {'SYSTEMROOT', 'WINDIR', 'SYSTEMDRIVE', 'COMSPEC', 'PATHEXT', 'PROGRAMFILES',
                    'PROGRAMFILES(X86)', 'PROGRAMW6432', 'PROGRAMDATA', 'USERPROFILE', 'LOCALAPPDATA',
                    'LANG', 'LC_ALL'}}
        env.update({'HOME': str(home), 'APPDATA': str(home / 'Roaming'), 'PATH': str(binaries),
                    'TMPDIR': str(temp), 'TMP': str(temp), 'TEMP': str(temp), 'NO_COLOR': '1', 'TERM': 'dumb'})
        # Direct inbox .NET ACL calls avoid the measured module-discovery delay.
        native_timeout = 90
        secret = 'RecognizableFixtureSecret'
        webhook = 'https://fixture.invalid/rest/1/' + secret + '/'
        assert webhook.encode() not in (binaries / ('b24cli' + suffix)).read_bytes(), 'Fixture credential entered production binary'
        (root / '.env').write_text('BITRIX24_WEBHOOK=https://untrusted.invalid/rest/1/UntrustedSecret/\n')
        (binaries / '.env.local').write_text('BITRIX24_WEBHOOK=https://untrusted.invalid/rest/1/UntrustedSecret/\n')
        executable_acl = None
        powershell = Path(env.get('SystemRoot', env.get('SYSTEMROOT', 'C:\\Windows'))) / 'System32/WindowsPowerShell/v1.0/powershell.exe'
        if os.name == 'nt':
            # Keep command discovery inside the inbox modules and its cache in
            # the fixture; inherited runner/user module paths are not inputs.
            env['PSModulePath'] = str(powershell.parent / 'Modules')
            env['PSModuleAnalysisCachePath'] = str(temp / 'powershell-module-cache')

        def ps(script, extra=None):
            encoded = base64.b64encode(script.encode('utf-16le')).decode('ascii')
            started = time.monotonic()
            result = subprocess.run([str(powershell), '-NoProfile', '-NonInteractive', '-EncodedCommand', encoded],
                                  env={**env, 'B24CLI_TEST_PATH': str(binaries), **(extra or {})},
                                  stdin=subprocess.DEVNULL, text=True, capture_output=True, timeout=30)
            assert secret not in result.stdout + result.stderr, 'Credential leaked in ACL fixture output'
            assert result.returncode == 0, f'Windows ACL fixture: exit {result.returncode}\n{result.stdout}\n{result.stderr}'
            print(json.dumps({'stage': 'Windows inbox ACL fixture', 'seconds': round(time.monotonic() - started, 2)}), flush=True)
            return result.stdout

        def run(command, expected=0, stdin=None, extra=None, fixture=False):
            name = 'runtime-probe' if fixture else 'b24cli'
            argv = ['connection-fixture', *command] if fixture else command
            started = time.monotonic()
            try:
                result = subprocess.run(prefix + [str(binaries / (name + suffix)), *argv], cwd=root,
                                        env={**env, **(extra or {})}, text=True, encoding='utf-8',
                                        capture_output=True, input=stdin, timeout=native_timeout)
            except subprocess.TimeoutExpired:
                if os.name == 'nt':
                    # Log process metadata and names only; never credential contents or environment.
                    diagnostic = subprocess.run([str(powershell), '-NoProfile', '-NonInteractive', '-Command',
                        "Get-Process b24cli,runtime-probe,powershell -ErrorAction SilentlyContinue | Select-Object Id,ProcessName,CPU,StartTime | ConvertTo-Json -Compress"],
                        capture_output=True, text=True, timeout=30)
                    print(diagnostic.stdout, flush=True)
                    print(json.dumps({'managed_paths_at_timeout': [str(p.relative_to(home)) for p in home.rglob('*')]}), flush=True)
                raise
            print(json.dumps({'native_command': command[0], 'seconds': round(time.monotonic() - started, 2)}), flush=True)
            assert secret not in result.stdout + result.stderr, 'Credential leaked in native output'
            assert result.returncode == expected, f'{command}: exit {result.returncode}\n{result.stdout}\n{result.stderr}'
            return result.stdout

        def check_shell(result, name):
            assert secret not in result.stdout + result.stderr, 'Credential leaked in ' + name + ' output'
            assert result.returncode == 0, f'{name}: exit {result.returncode}\n{result.stdout}\n{result.stderr}'

        try:
            if os.name != 'nt':
                binaries.chmod(0o500)
            else:
                print('Native connection stage: Windows inbox ACL setup', flush=True)
                executable_acl = ps("[System.IO.Directory]::GetAccessControl($env:B24CLI_TEST_PATH).GetSecurityDescriptorSddlForm([System.Security.AccessControl.AccessControlSections]'Owner,Group,Access')")
                ps("$sid=[System.Security.Principal.WindowsIdentity]::GetCurrent().User; $a=[System.Security.AccessControl.DirectorySecurity]::new(); $a.SetOwner($sid); $a.SetAccessRuleProtection($true,$false); $a.AddAccessRule([System.Security.AccessControl.FileSystemAccessRule]::new($sid,'ReadAndExecute','ContainerInherit,ObjectInherit','None','Allow')); [System.IO.Directory]::SetAccessControl($env:B24CLI_TEST_PATH,$a)")
            print('Native connection stage: offline surfaces', flush=True)
            for command in [['--help'], ['--version'], ['list'], ['completion', 'bash'],
                            ['task:add', '--title', 'Offline', '--creator', '1', '--responsible', '2', '--dry-run', '--json']]:
                run(command)
            assert not home.exists(), 'Offline surfaces wrote user configuration'
            missing = json.loads(run(['task:list', '--json'], 1))
            assert missing['error']['code'] == 'connection-unavailable' and missing['meta']['apiCalls'] == []
            print('Native connection stage: documented shells', flush=True)
            # Repeat documented redirection/selection through the native user shell.
            secret_file = root / 'webhook input.txt'
            secret_file.write_bytes((webhook + '\n').encode('utf-8'))
            assert secret_file.read_bytes().startswith(b'https://'), 'Fixture source has a BOM'
            shell_env = {**env, 'B24CLI_BINARY': str(binaries / ('b24cli' + suffix)), 'B24CLI_STDIN_PROBE': str(binaries / ('runtime-probe' + suffix)), 'B24CLI_SECRET_FILE': str(secret_file), 'B24CLI_SHELL_CONFIG': str(root / 'shell profiles')}
            if not args.phar:
                if os.name == 'nt':
                    # .NET Framework initializes Process.StandardInput with Console.InputEncoding
                    # and AutoFlush before PowerShell's OutputEncoding writer is used.
                    fingerprint_script = "[Console]::Error.WriteLine('console-input-preamble=' + [BitConverter]::ToString([Console]::InputEncoding.GetPreamble())); Get-Content -Raw $env:B24CLI_SECRET_FILE | & $env:B24CLI_STDIN_PROBE stdin-fingerprint -n; $OutputEncoding = [System.Text.UTF8Encoding]::new($false); Get-Content -Raw -Encoding UTF8 $env:B24CLI_SECRET_FILE | & $env:B24CLI_STDIN_PROBE stdin-fingerprint -n; [Console]::InputEncoding = $OutputEncoding; [Console]::Error.WriteLine('explicit-console-input-preamble=' + [BitConverter]::ToString([Console]::InputEncoding.GetPreamble())); Get-Content -Raw -Encoding UTF8 $env:B24CLI_SECRET_FILE | & $env:B24CLI_STDIN_PROBE stdin-fingerprint -n"
                    fingerprints = subprocess.run([str(powershell), '-NoProfile', '-NonInteractive', '-Command', fingerprint_script], env=shell_env, cwd=root, capture_output=True, text=True, timeout=60, check=True)
                    results = [json.loads(line) for line in fingerprints.stdout.splitlines()]
                    print(json.dumps({'PowerShell_stdin_fingerprints': results, 'encoding': fingerprints.stderr}), flush=True)
                    assert results[-1]['trimmed_sha256'] == hashlib.sha256(webhook.encode()).hexdigest(), 'PowerShell changed UTF-8 fixture stdin'
                    script = "$OutputEncoding = [Console]::InputEncoding = [System.Text.UTF8Encoding]::new($false); Get-Content -Raw -Encoding UTF8 $env:B24CLI_SECRET_FILE | & $env:B24CLI_BINARY auth:login work --webhook-stdin --skip-check -n --config-dir $env:B24CLI_SHELL_CONFIG --json; if($LASTEXITCODE -ne 0){exit $LASTEXITCODE}; & $env:B24CLI_BINARY auth:switch work --config-dir $env:B24CLI_SHELL_CONFIG --json"
                    shell_result = subprocess.run([str(powershell), '-NoProfile', '-NonInteractive', '-Command', script], env=shell_env, cwd=root, capture_output=True, text=True, timeout=native_timeout)
                    original = args.dist.resolve() / 'uncompressed/b24cli.exe'
                    if shell_result.returncode != 0 and original.is_file():
                        # Compare the identical shell pipeline with the equivalent original.
                        # This diagnostic never relaxes the final executable's assertion.
                        raw_bin = root / 'original shell'
                        raw_bin.mkdir()
                        raw_binary = raw_bin / 'b24cli.exe'
                        shutil.copy2(original, raw_binary)
                        control_env = {**shell_env, 'B24CLI_BINARY': str(raw_binary),
                                       'B24CLI_SHELL_CONFIG': str(root / 'original shell profiles')}
                        control = subprocess.run([str(powershell), '-NoProfile', '-NonInteractive', '-Command', script], env=control_env, cwd=root, capture_output=True, text=True, timeout=native_timeout)
                        print(json.dumps({'original_shell_control': {'returncode': control.returncode,
                            'stdout': control.stdout.replace(secret, '[REDACTED]'),
                            'stderr': control.stderr.replace(secret, '[REDACTED]')}}), flush=True)
                    assert shell_result.returncode == 0 and secret not in shell_result.stdout + shell_result.stderr, json.dumps({'final_shell': {'returncode': shell_result.returncode, 'stdout': shell_result.stdout.replace(secret, '[REDACTED]'), 'stderr': shell_result.stderr.replace(secret, '[REDACTED]')}})
                    cmd = Path(env.get('SystemRoot', env.get('SYSTEMROOT', 'C:\\Windows'))) / 'System32/cmd.exe'
                    # cmd.exe does not use the MS C runtime argument parser. Pass its
                    # documented /s /c outer quote pair as a command line directly;
                    # list2cmdline would inject backslash escapes into this syntax.
                    command_line = '"' + str(cmd) + '" /d /s /c ""%B24CLI_BINARY%" auth:login pet --webhook-stdin --skip-check -n --config-dir "%B24CLI_SHELL_CONFIG%" --json < "%B24CLI_SECRET_FILE%""'
                    shell_result = subprocess.run(command_line, env=shell_env, cwd=root, capture_output=True, text=True, timeout=native_timeout)
                    assert shell_result.returncode == 0 and secret not in shell_result.stdout + shell_result.stderr, json.dumps({'cmd_shell': {'returncode': shell_result.returncode, 'stdout': shell_result.stdout.replace(secret, '[REDACTED]'), 'stderr': shell_result.stderr.replace(secret, '[REDACTED]')}})
                else:
                    shell = '/bin/zsh' if os.uname().sysname == 'Darwin' else '/bin/bash'
                    shell_result = subprocess.run([shell, '-c', '"$B24CLI_BINARY" auth:login work --webhook-stdin --skip-check -n --config-dir "$B24CLI_SHELL_CONFIG" --json < "$B24CLI_SECRET_FILE" && "$B24CLI_BINARY" auth:switch work --config-dir "$B24CLI_SHELL_CONFIG" --json'], env=shell_env, cwd=root, capture_output=True, text=True, timeout=native_timeout)
                    check_shell(shell_result, 'Unix shell setup')
            print('Native connection stage: first profile and SDK sources', flush=True)
            # Offline login: the production binary never reaches a portal in this acceptance.
            login = json.loads(run(['auth:login', 'work', '--webhook-stdin', '--skip-check', '-n', '--json'], stdin=webhook+'\r\n'))
            assert login['data']['becameDefault'] is True and login['data']['verified'] is False and login['meta']['apiCalls'] == []
            saved = json.loads((config / 'config.json').read_text())
            assert saved['defaultProfile'] == 'work', 'The first profile must become the default'
            profile = saved['profiles']['work']
            assert profile == {'name': 'fixture.invalid', 'connection': profile['connection']} and profile['connection']['hostname'] == 'fixture.invalid'
            assert not (config / 'state.json').exists(), 'Offline login wrote scope state'
            credential = config / profile['connection']['credentialFile']
            initial = credential.read_bytes()
            for key, value in [('name', 'description.invalid'), ('default.task.groupId', '42'),
                               ('default.task.creatorId', '1'), ('default.task.responsibleId', '2')]:
                changed = json.loads(run(['config:set', key, value, '--profile', 'work', '--json']))
                assert changed['data']['source'] == 'profile' and changed['meta']['apiCalls'] == []
            profile = json.loads((config / 'config.json').read_text())['profiles']['work']
            assert profile['name'] == 'description.invalid' and profile['connection']['hostname'] == 'fixture.invalid'
            assert profile['default']['task'] == {'groupId': 42, 'creatorId': 1, 'responsibleId': 2}
            assert json.loads(run(['config:get', 'default.task.allowsTimeTracking', '--json']))['data'] == {
                'key': 'default.task.allowsTimeTracking', 'value': False, 'source': 'default', 'profile': 'work'}
            run(['auth:switch', 'work', '--json'])
            assert credential.read_bytes() == initial, 'Settings and selection must not rewrite the credential'
            assert not (config / 'state.json').exists(), 'Local commands opened scope state'
            plan = json.loads(run(['task:add', '--profile', 'work', '--title', 'Native defaults', '--dry-run', '--json']))
            assert plan['data']['plan']['steps'][0]['fields']['groupId'] == 42
            for selector, extra, cold in [(['--profile', 'work'], {'BITRIX24_WEBHOOK': 'https://ambient.invalid/rest/1/AmbientSecret/'}, True),
                                         ([], {}, False)]:
                result = json.loads(run(['task:list', '--json', *selector], fixture=True,
                                        extra={**extra, 'B24CLI_FIXTURE_EXPECTED_HOST': 'fixture.invalid'}))
                assert result['meta']['connection']['source'] == 'profile'
                check_scope_calls(result, cold)
                assert result['data']['items'][0]['id'] == 123
            external = root / 'Внешний файл space.env'
            external.write_bytes(('BITRIX24_WEBHOOK=' + webhook.replace('fixture.invalid', 'file.invalid') + '\r\n').encode())
            for selector, extra, source in [(['--env-file', str(external)], {}, 'explicit-file'),
                    ([], {'B24CLI_ENV_FILE': str(external)}, 'environment-file'),
                    ([], {'BITRIX24_WEBHOOK': webhook.replace('fixture.invalid', 'process.invalid')}, 'process-env')]:
                host = 'process.invalid' if source == 'process-env' else 'file.invalid'
                for cold in [True, False]:
                    result = json.loads(run(['task:list', '--json', *selector], fixture=True,
                                            extra={**extra, 'B24CLI_FIXTURE_EXPECTED_HOST': host}))
                    assert result['meta']['connection']['source'] == source
                    check_scope_calls(result, cold)
            # Readers and dry-run may inspect metadata with a missing credential.
            credential.unlink()
            run(['task:add', '--profile', 'work', '--title', 'No secret read', '--dry-run', '--json'])
            listed = json.loads(run(['auth:list', 'work', '--json']))
            assert listed['data']['items'] == [{'profile': 'work', 'name': 'description.invalid', 'hostname': 'fixture.invalid', 'isDefault': True}]
            assert listed['data']['effective']['source'] == 'profile' and listed['data']['effective']['hostname'] == 'fixture.invalid'
            run(['config:list', '--profile', 'work', '--json'])
            failure = json.loads(run(['task:list', '--profile', 'work', '--json'], 1))
            assert failure['meta']['apiCalls'] == []
            # Replacing the webhook restores the secret and keeps every setting.
            run(['auth:login', 'work', '--replace', '--webhook-stdin', '--skip-check', '-n', '--json'], stdin=webhook)
            replaced = json.loads((config / 'config.json').read_text())
            assert replaced['defaultProfile'] == 'work' and replaced['profiles']['work']['default']['task']['groupId'] == 42
            assert replaced['profiles']['work']['name'] == 'description.invalid'
            assert sorted(p.name for p in (config / 'credentials').iterdir()) == [Path(replaced['profiles']['work']['connection']['credentialFile']).name]
            # Verified login through the controlled SDK transport: owner, first snapshot and a warm next command.
            verified = json.loads(run(['auth:login', 'verified', '--webhook-stdin', '-n', '--json'], fixture=True, stdin=webhook,
                                      extra={'B24CLI_FIXTURE_EXPECTED_HOST': 'fixture.invalid'}))
            assert verified['data']['verified'] is True and verified['data']['becameDefault'] is False
            assert verified['data']['owner'] == {'ID': '1', 'NAME': 'Fixture', 'LAST_NAME': 'Owner', 'ADMIN': False}
            assert [call['method'] for call in verified['meta']['apiCalls']] == ['profile', 'scope']
            assert verified['meta']['scopeValidation']['status'] == 'fresh' and 'im' in verified['meta']['scopeValidation']['missingScopes']
            warm = json.loads(run(['task:list', '--profile', 'verified', '--json'], fixture=True,
                                  extra={'B24CLI_FIXTURE_EXPECTED_HOST': 'fixture.invalid'}))
            check_scope_calls(warm, False)
            run(['auth:logout', 'verified', '--json'])
            assert json.loads((config / 'config.json').read_text())['defaultProfile'] == 'work'
            removed = json.loads(run(['auth:logout', 'work', '--json']))
            assert removed['data']['wasDefault'] is True and removed['data']['portalWebhookRevoked'] is False
            assert json.loads((config / 'config.json').read_text())['defaultProfile'] is None
            assert not list((config / 'credentials').iterdir()), 'Logout left a credential file'
            # Missing user home never redirects managed state into cwd or payload.
            no_home = {'HOME': '', 'APPDATA': '', 'XDG_CONFIG_HOME': ''}
            result = json.loads(run(['auth:list', '--json'], 1, extra=no_home))
            assert result['error']['code'] == 'configuration-error'
            run(['task:add', '--title', 'Offline no home', '--creator', '1', '--responsible', '2', '--dry-run', '--json'], extra=no_home)
            for control in ['\t', '\x00', '\n', '\r', ' ', '\x7f']:
                invalid = webhook.replace(secret, secret + control + 'Test')
                run(['auth:login', 'invalid', '--webhook-stdin', '--json'], 1, stdin=invalid)
                external.write_text("BITRIX24_WEBHOOK='" + invalid + "'\n")
                run(['task:list', '--env-file', str(external), '--json'], 1)
                if control != '\x00':
                    run(['task:list', '--json'], 1, extra={'BITRIX24_WEBHOOK': invalid})
            for source in ['https://fixture.invalid/credential.env', 'data://text/plain;base64,VGVzdA==']:
                command = ['task:add', '--title', 'Offline filesystem selection', '--creator', '1', '--responsible', '2', '--dry-run', '--json']
                run([*command, '--env-file', source], 1)
                run(command, 1, extra={'B24CLI_ENV_FILE': source})
            print('Native connection stage: concurrent writers and private permissions', flush=True)
            # Concurrent writers must preserve all independent profiles.
            def add(alias):
                run(['auth:login', alias, '--webhook-stdin', '--skip-check', '--json'], stdin=webhook)
            with ThreadPoolExecutor(max_workers=4) as workers:
                list(workers.map(add, ['work', 'pet', 'clientA', 'clientB']))
            saved = json.loads((config / 'config.json').read_text())
            assert set(saved['profiles']) == {'work', 'pet', 'clientA', 'clientB'}
            for p in [config, config / 'credentials', config / 'config.json', *list((config / 'credentials').glob('*'))]:
                if os.name != 'nt':
                    assert p.stat().st_mode & 0o777 == (0o700 if p.is_dir() else 0o600)
                else:
                    ps("$sid=[System.Security.Principal.WindowsIdentity]::GetCurrent().User; if([System.IO.Directory]::Exists($env:B24CLI_MANAGED)){$a=[System.IO.Directory]::GetAccessControl($env:B24CLI_MANAGED)}else{$a=[System.IO.File]::GetAccessControl($env:B24CLI_MANAGED)}; if(!$a.AreAccessRulesProtected -or $a.GetOwner([System.Security.Principal.SecurityIdentifier]).Value -ne $sid.Value){exit 1}; foreach($r in $a.GetAccessRules($true,$true,[System.Security.Principal.SecurityIdentifier])){if($r.IdentityReference.Value -ne $sid.Value){exit 1}}", {'B24CLI_MANAGED': str(p)})
            if os.name == 'nt':
                managed = config / saved['profiles']['work']['connection']['credentialFile']
                original = ps("[System.IO.File]::GetAccessControl($env:B24CLI_MANAGED).GetSecurityDescriptorSddlForm([System.Security.AccessControl.AccessControlSections]'Owner,Group,Access')", {'B24CLI_MANAGED': str(managed)})
                try:
                    ps("$a=[System.IO.File]::GetAccessControl($env:B24CLI_MANAGED); $a.AddAccessRule([System.Security.AccessControl.FileSystemAccessRule]::new([System.Security.Principal.SecurityIdentifier]::new('S-1-1-0'),'Read','Allow')); [System.IO.File]::SetAccessControl($env:B24CLI_MANAGED,$a)", {'B24CLI_MANAGED': str(managed)})
                    rejected = json.loads(run(['task:list', '--profile', 'work', '--json'], 1, fixture=True, extra={'B24CLI_FIXTURE_EXPECTED_HOST': 'fixture.invalid'}))
                    assert rejected['error']['code'] == 'configuration-error' and rejected['meta']['apiCalls'] == []
                finally:
                    ps("$a=[System.IO.File]::GetAccessControl($env:B24CLI_MANAGED); $a.SetSecurityDescriptorSddlForm($env:B24CLI_ORIGINAL_ACL,[System.Security.AccessControl.AccessControlSections]::Access); [System.IO.File]::SetAccessControl($env:B24CLI_MANAGED,$a)", {'B24CLI_MANAGED': str(managed), 'B24CLI_ORIGINAL_ACL': original.strip()})
            # Explicit Unicode paths work independently of executable/cwd/default paths.
            other = root / 'Профили space'
            run(['--config-dir', str(other), 'auth:login', 'work', '--webhook-stdin', '--skip-check', '--json'], stdin=webhook)
            run(['config:set', 'default.task.groupId', '7', '--config-dir', str(other), '--json'])
            run(['auth:list', 'work', '--config-dir', str(other), '--json'])
            run(['config:list', '--config-dir', str(other), '--json'])
            for path in [config / 'config.json', other / 'config.json']:
                assert secret not in path.read_text(), 'Secret in metadata'
            assert all(hashlib.sha256((binaries / name).read_bytes()).hexdigest() == digest for name, digest in hashes.items())
            assert not list(binaries.glob('credentials')), 'Configuration was written beside binary'
            if os.name == 'nt':
                assert not list(temp.glob('b24cli-payload-*')), 'Temporary PHAR file or directory leaked after concurrent launches'
            print(json.dumps({'status': 'passed', 'checks': ['private-storage', 'Windows-ACL-and-unsafe-grant-rejection' if os.name == 'nt' else 'Unix-modes',
                'concurrent-writers', 'profile-defaults', 'config-commands', 'webhook-replacement', 'verified-login-snapshot', 'source-precedence', 'full-SDK-controlled-transport',
                'CRLF', 'documented-native-shells', 'Unicode-space-paths', 'read-only-executable-dir', 'no-cwd-dotenv', 'missing-user-home', 'raw-URL-controls', 'filesystem-only-env-file', 'no-secret-output', 'no-binary-mutation']}))
        finally:
            if os.name != 'nt':
                binaries.chmod(0o700)
            elif executable_acl:
                ps("$a=[System.IO.Directory]::GetAccessControl($env:B24CLI_TEST_PATH); $a.SetSecurityDescriptorSddlForm($env:B24CLI_ORIGINAL_ACL,[System.Security.AccessControl.AccessControlSections]::Access); [System.IO.Directory]::SetAccessControl($env:B24CLI_TEST_PATH,$a)", {'B24CLI_ORIGINAL_ACL': executable_acl.strip()})


if __name__ == '__main__':
    main()
