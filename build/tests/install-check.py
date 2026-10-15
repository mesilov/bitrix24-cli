#!/usr/bin/env python3
"""Use the documented archive, checksum, unpack and PATH installation sequence."""
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
    # Windows CreateProcess searches the parent PATH, ignoring env['PATH'].
    # Resolve the command against the installation PATH before starting it.
    command = shutil.which(executable, path=env['PATH'])
    if command is None or Path(command).resolve() != (install / executable).resolve():
        raise AssertionError('Installed binary was not found in the installation PATH')
    version = subprocess.check_output([command, '--version', '--no-ansi'], cwd=root,
                                      env=env, text=True, encoding='utf-8').strip()
    expected = 'b24cli ' + json.loads((dist / 'build-info.json').read_text())['version']
    if version != expected:
        raise AssertionError('Installed binary version differs: ' + version)
    print('Verified checksum, archive unpack, executable permissions and PATH installation: ' + version)
