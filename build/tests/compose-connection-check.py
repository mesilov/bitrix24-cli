#!/usr/bin/env python3
"""Verify wrapper argument boundaries and readonly config/file mount forwarding."""
import json
import os
from pathlib import Path
import shutil
import subprocess
import tempfile

root = Path(__file__).resolve().parents[2]
with tempfile.TemporaryDirectory(prefix='b24cli-compose-') as directory:
    temp = Path(directory)
    docker = temp / 'docker'
    docker.write_text('#!/usr/bin/env python3\nimport json,os,sys\nprint(json.dumps({"args":sys.argv[1:],"readonly":os.environ.get("B24CLI_CONFIG_READ_ONLY")}))\n')
    docker.chmod(0o755)
    base = {**os.environ, 'PATH': str(temp) + os.pathsep + os.environ['PATH']}
    for variable in ['B24CLI_CONFIG_DIR', 'B24CLI_CONFIG_WRITE', 'B24CLI_CONFIG_READ_ONLY', 'B24CLI_ENV_FILE']:
        base.pop(variable, None)
    for variables, expected, readonly in [({}, [], None),
        ({'B24CLI_ENV_FILE': '/external/Файл space.env'}, ['docker-compose.env.yaml'], None),
        ({'B24CLI_CONFIG_DIR': '/external/Конфиг space'}, ['docker-compose.config.yaml'], 'true'),
        ({'B24CLI_CONFIG_DIR': '/external/config', 'B24CLI_CONFIG_WRITE': '1', 'B24CLI_ENV_FILE': '/external/file.env'}, ['docker-compose.config.yaml', 'docker-compose.env.yaml'], 'false')]:
        result = subprocess.run(['sh', str(root / 'scripts/compose.sh'), 'run', '--rm', '-T', 'php-cli', 'php', 'bin/b24cli', 'task:list'],
                                cwd=temp, env={**base, **variables}, capture_output=True, text=True, check=True)
        payload = json.loads(result.stdout)
        assert payload['args'][0] == 'compose'
        assert payload['args'][-7:] == ['run', '--rm', '-T', 'php-cli', 'php', 'bin/b24cli', 'task:list']
        assert payload['readonly'] == readonly
        paths = [Path(payload['args'][i+1]).name for i, arg in enumerate(payload['args']) if arg == '--file']
        assert set(paths) == {'docker-compose.yaml', *expected}
        assert str(root) in payload['args'] and payload['args'].count('--project-name') == 1
    # Compose parses our overlay values and preserves readonly mount contracts.
    real = shutil.which('docker', path=os.environ['PATH'])
    if real:
        config = temp / 'Конфиг space'
        config.mkdir(mode=0o700)
        envfile = temp / 'Файл space.env'
        envfile.write_text('BITRIX24_WEBHOOK=https://fixture.invalid/rest/1/FixtureSecret/\n')
        result = subprocess.run([real, 'compose', '--project-directory', str(root), '-f', str(root/'docker-compose.yaml'),
            '-f', str(root/'docker-compose.config.yaml'), '-f', str(root/'docker-compose.env.yaml'), 'config', '--format', 'json'],
            env={**os.environ, 'B24CLI_CONFIG_DIR': str(config), 'B24CLI_ENV_FILE': str(envfile), 'B24CLI_CONFIG_READ_ONLY': 'true'},
            capture_output=True, text=True, check=True)
        service = json.loads(result.stdout)['services']['php-cli']
        mounts = {v['target']: v for v in service['volumes']}
        assert mounts['/run/b24cli-config']['read_only'] and mounts['/run/b24cli.env']['read_only']
        assert service['environment']['B24CLI_CONFIG_DIR'] == '/run/b24cli-config'
        assert service['environment']['B24CLI_ENV_FILE'] == '/run/b24cli.env'
print('Compose connection forwarding passed (4 argument fixtures; readonly overlays).')
