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
        env = {k: v for k, v in os.environ.items() if k.upper() in {'SYSTEMROOT', 'WINDIR', 'SYSTEMDRIVE', 'COMSPEC', 'PROGRAMFILES',
                    'PROGRAMFILES(X86)', 'PROGRAMW6432', 'PROGRAMDATA', 'USERPROFILE', 'LOCALAPPDATA',
                    'LANG', 'LC_ALL'}}
        env.update({'HOME': str(home), 'APPDATA': str(home / 'Roaming'), 'PATH': str(binaries),
                    'TMPDIR': str(temp), 'TMP': str(temp), 'TEMP': str(temp), 'NO_COLOR': '1', 'TERM': 'dumb'})
        secret = 'RecognizableFixtureSecret'
        webhook = 'https://fixture.invalid/rest/1/' + secret + '/'
        assert webhook.encode() not in (binaries / ('b24cli' + suffix)).read_bytes(), 'Fixture credential entered production binary'
        (root / '.env').write_text('BITRIX24_WEBHOOK=https://untrusted.invalid/rest/1/UntrustedSecret/\n')
        (binaries / '.env.local').write_text('BITRIX24_WEBHOOK=https://untrusted.invalid/rest/1/UntrustedSecret/\n')
        executable_acl = None
        powershell = Path(env.get('SystemRoot', env.get('SYSTEMROOT', 'C:\\Windows'))) / 'System32/WindowsPowerShell/v1.0/powershell.exe'

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
            result = subprocess.run(prefix + [str(binaries / (name + suffix)), *argv], cwd=root,
                                    env={**env, **(extra or {})}, text=True, encoding='utf-8',
                                    capture_output=True, input=stdin, timeout=90)
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
            print('Native connection stage: first profile and SDK sources', flush=True)
            run(['connection:add', 'work', '--webhook-stdin', '--portal-domain', 'description.invalid',
                 '--group-id', '42', '--creator-id', '1', '--responsible-id', '2', '--json'], stdin=webhook+'\r\n')
            saved = json.loads((config / 'config.json').read_text())
            assert saved['defaultProfile'] is None
            profile = saved['profiles']['work']
            assert profile['name'] == 'description.invalid' and profile['connection']['hostname'] == 'fixture.invalid'
            assert profile['default']['task'] == {'groupId': 42, 'creatorId': 1, 'responsibleId': 2, 'allowsTimeTracking': False}
            credential = config / profile['connection']['credentialFile']
            initial = credential.read_bytes()
            run(['connection:use', 'work', '--json'])
            assert credential.read_bytes() == initial
            plan = json.loads(run(['task:add', '--profile', 'work', '--title', 'Native defaults', '--dry-run', '--json']))
            assert plan['data']['plan']['steps'][0]['fields']['groupId'] == 42
            for selector, extra in [(['--profile', 'work'], {'BITRIX24_WEBHOOK': 'https://ambient.invalid/rest/1/AmbientSecret/'}),
                                    ([], {})]:
                result = json.loads(run(['task:list', '--json', *selector], fixture=True,
                                        extra={**extra, 'B24CLI_FIXTURE_EXPECTED_HOST': 'fixture.invalid'}))
                assert result['meta']['connection']['source'] == 'profile'
                assert len(result['meta']['apiCalls']) == 1 and result['data']['items'][0]['id'] == 123
            external = root / 'Внешний файл space.env'
            external.write_bytes(('BITRIX24_WEBHOOK=' + webhook.replace('fixture.invalid', 'file.invalid') + '\r\n').encode())
            for selector, extra, source in [(['--env-file', str(external)], {}, 'explicit-file'),
                    ([], {'B24CLI_ENV_FILE': str(external)}, 'environment-file'),
                    ([], {'BITRIX24_WEBHOOK': webhook.replace('fixture.invalid', 'process.invalid')}, 'process-env')]:
                host = 'process.invalid' if source == 'process-env' else 'file.invalid'
                result = json.loads(run(['task:list', '--json', *selector], fixture=True,
                                        extra={**extra, 'B24CLI_FIXTURE_EXPECTED_HOST': host}))
                assert result['meta']['connection']['source'] == source and len(result['meta']['apiCalls']) == 1
            # Repeat documented redirection/selection through the native user shell.
            secret_file = root / 'webhook input.txt'
            secret_file.write_text(webhook + '\n')
            shell_env = {**env, 'B24CLI_BINARY': str(binaries / ('b24cli' + suffix)), 'B24CLI_SECRET_FILE': str(secret_file), 'B24CLI_SHELL_CONFIG': str(root / 'shell profiles')}
            if not args.phar:
                print('Native connection stage: documented shells', flush=True)
                if os.name == 'nt':
                    shell_result = subprocess.run([str(powershell), '-NoProfile', '-NonInteractive', '-Command', "Get-Content -Raw $env:B24CLI_SECRET_FILE | & $env:B24CLI_BINARY connection:add work --webhook-stdin -n --config-dir $env:B24CLI_SHELL_CONFIG --json; if($LASTEXITCODE -ne 0){exit $LASTEXITCODE}; & $env:B24CLI_BINARY connection:use work --config-dir $env:B24CLI_SHELL_CONFIG --json"], env=shell_env, cwd=root, capture_output=True, text=True, timeout=90)
                    check_shell(shell_result, 'PowerShell setup')
                    cmd = Path(env.get('SystemRoot', env.get('SYSTEMROOT', 'C:\\Windows'))) / 'System32/cmd.exe'
                    # Let cmd parse a batch file directly, without CRT argument quoting of shell code.
                    batch = root / 'connection-setup.cmd'
                    batch.write_bytes(b'@echo off\r\n"%B24CLI_BINARY%" connection:add pet --webhook-stdin -n --config-dir "%B24CLI_SHELL_CONFIG%" --json < "%B24CLI_SECRET_FILE%"\r\nexit /b %errorlevel%\r\n')
                    shell_result = subprocess.run([str(cmd), '/d', '/c', batch.name], env=shell_env, cwd=root, capture_output=True, text=True, timeout=90)
                    check_shell(shell_result, 'cmd.exe setup')
                else:
                    shell = '/bin/zsh' if os.uname().sysname == 'Darwin' else '/bin/bash'
                    shell_result = subprocess.run([shell, '-c', '"$B24CLI_BINARY" connection:add work --webhook-stdin -n --config-dir "$B24CLI_SHELL_CONFIG" --json < "$B24CLI_SECRET_FILE" && "$B24CLI_BINARY" connection:use work --config-dir "$B24CLI_SHELL_CONFIG" --json'], env=shell_env, cwd=root, capture_output=True, text=True, timeout=90)
                    check_shell(shell_result, 'Unix shell setup')
            # Readers and dry-run may inspect metadata with a missing credential.
            credential.unlink()
            run(['task:add', '--profile', 'work', '--title', 'No secret read', '--dry-run', '--json'])
            run(['connection:show', 'work', '--json'])
            failure = json.loads(run(['task:list', '--profile', 'work', '--json'], 1))
            assert failure['meta']['apiCalls'] == []
            run(['connection:remove', 'work', '--json'])
            assert json.loads((config / 'config.json').read_text())['defaultProfile'] is None
            # Missing user home never redirects managed state into cwd or payload.
            no_home = {'HOME': '', 'APPDATA': '', 'XDG_CONFIG_HOME': ''}
            result = json.loads(run(['connection:list', '--json'], 1, extra=no_home))
            assert result['error']['code'] == 'configuration-error'
            run(['task:add', '--title', 'Offline no home', '--creator', '1', '--responsible', '2', '--dry-run', '--json'], extra=no_home)
            for control in ['\t', '\x00', '\n', '\r', ' ', '\x7f']:
                invalid = webhook.replace(secret, secret + control + 'Test')
                run(['connection:add', 'invalid', '--webhook-stdin', '--json'], 1, stdin=invalid)
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
                run(['connection:add', alias, '--webhook-stdin', '--json'], stdin=webhook)
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
            run(['--config-dir', str(other), 'connection:add', 'work', '--webhook-stdin', '--json'], stdin=webhook)
            run(['connection:show', 'work', '--config-dir', str(other), '--json'])
            for path in [config / 'config.json', other / 'config.json']:
                assert secret not in path.read_text(), 'Secret in metadata'
            assert all(hashlib.sha256((binaries / name).read_bytes()).hexdigest() == digest for name, digest in hashes.items())
            assert not list(binaries.glob('credentials')), 'Configuration was written beside binary'
            print(json.dumps({'status': 'passed', 'checks': ['private-storage', 'Windows-ACL-and-unsafe-grant-rejection' if os.name == 'nt' else 'Unix-modes',
                'concurrent-writers', 'profile-defaults', 'source-precedence', 'full-SDK-controlled-transport',
                'CRLF', 'documented-native-shells', 'Unicode-space-paths', 'read-only-executable-dir', 'no-cwd-dotenv', 'missing-user-home', 'raw-URL-controls', 'filesystem-only-env-file', 'no-secret-output', 'no-binary-mutation']}))
        finally:
            if os.name != 'nt':
                binaries.chmod(0o700)
            elif executable_acl:
                ps("$a=[System.IO.Directory]::GetAccessControl($env:B24CLI_TEST_PATH); $a.SetSecurityDescriptorSddlForm($env:B24CLI_ORIGINAL_ACL,[System.Security.AccessControl.AccessControlSections]::Access); [System.IO.Directory]::SetAccessControl($env:B24CLI_TEST_PATH,$a)", {'B24CLI_ORIGINAL_ACL': executable_acl.strip()})


if __name__ == '__main__':
    main()
