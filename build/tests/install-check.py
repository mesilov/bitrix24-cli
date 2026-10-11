#!/usr/bin/env python3
"""Use the documented archive, checksum, unpack and PATH installation sequence."""
import argparse
import hashlib
import json
import os
from pathlib import Path
import platform
import subprocess
import sys
import tarfile
import tempfile
import zipfile

parser = argparse.ArgumentParser()
parser.add_argument('dist', type=Path)
args = parser.parse_args()
dist = args.dist.resolve()
target = {'Linux': 'linux-amd64', 'Darwin': 'macos-arm64', 'Windows': 'windows-amd64'}[platform.system()]
packager = Path(__file__).resolve().parents[1] / 'release-assets.py'
with tempfile.TemporaryDirectory(prefix='b24cli-install-') as directory:
    root = Path(directory)
    subprocess.run([sys.executable, str(packager), str(dist.parent), str(root), '--target', target], check=True)
    checksum, name = (root / 'SHA256SUMS').read_text().strip().split('  ')
    if hashlib.sha256((root / name).read_bytes()).hexdigest() != checksum:
        raise AssertionError('Archive checksum mismatch')
    install = root / 'bin'
    install.mkdir()
    if name.endswith('.zip'):
        with zipfile.ZipFile(root / name) as archive:
            archive.extractall(install)
    else:
        with tarfile.open(root / name) as archive:
            archive.extractall(install, filter='data')
    executable = 'b24cli.exe' if os.name == 'nt' else 'b24cli'
    if not os.name == 'nt' and not os.access(install / executable, os.X_OK):
        raise AssertionError('Extracted binary is not executable')
    env = {key: value for key, value in os.environ.items()
           if key.upper() in {'SYSTEMROOT', 'WINDIR', 'TMP', 'TEMP', 'LANG', 'LC_ALL'}}
    env.update({'PATH': str(install), 'NO_COLOR': '1', 'TERM': 'dumb'})
    version = subprocess.check_output([executable, '--version', '--no-ansi'], cwd=root, env=env, text=True).strip()
    expected = 'Bitrix24 CLI ' + json.loads((dist / 'build-info.json').read_text())['version']
    if version != expected:
        raise AssertionError('Installed binary version differs: ' + version)
    print('Verified checksum, archive unpack, executable permissions and PATH installation: ' + version)
