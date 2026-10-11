#!/usr/bin/env python3
"""Prove that acceptance stops on a wrong version or an absent executable."""
import argparse
from pathlib import Path
import subprocess
import sys
import tempfile

parser = argparse.ArgumentParser()
parser.add_argument('dist', type=Path)
parser.add_argument('--phar', action='store_true')
args = parser.parse_args()
smoke = Path(__file__).with_name('smoke.py')
options = ['--phar'] if args.phar else []
wrong = subprocess.run([sys.executable, str(smoke), str(args.dist), *options,
                        '--expected-version', 'incorrect-build-identity-fixture'], capture_output=True, text=True)
if wrong.returncode == 0 or 'Version mismatch' not in wrong.stderr:
    raise AssertionError('Wrong build identity did not fail acceptance: ' + wrong.stderr)
with tempfile.TemporaryDirectory(prefix='b24cli-missing-artifact-') as directory:
    missing = subprocess.run([sys.executable, str(smoke), directory, *options,
                              '--expected-version', 'missing-fixture'], capture_output=True, text=True)
    if missing.returncode == 0 or 'FileNotFoundError' not in missing.stderr:
        raise AssertionError('Missing executable did not fail acceptance: ' + missing.stderr)
print('Acceptance rejected wrong version and missing executable')
