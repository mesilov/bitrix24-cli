#!/usr/bin/env python3
"""Verify source provenance before using a checkout as packaging input."""
import argparse
import json
from pathlib import Path
import re
import subprocess
import sys


def git(root, *args):
    return subprocess.check_output(['git', '-C', str(root), *args], text=True).strip()


def validate_sha(sha):
    if not re.fullmatch(r'[0-9a-f]{40}', sha):
        raise ValueError('Source SHA must contain 40 lowercase hexadecimal characters')
    return sha


def verify_checkout(root, source_sha=None):
    head = validate_sha(git(root, 'rev-parse', 'HEAD'))
    if source_sha is not None and validate_sha(source_sha) != head:
        raise ValueError('Source SHA differs from checkout HEAD; checkout the requested commit first')
    if git(root, 'status', '--porcelain=v1', '--untracked-files=normal', '--ignore-submodules=none'):
        raise ValueError('Packaging requires a clean checkout; commit or isolate source changes first')
    config = json.loads((Path(root) / 'build/config.json').read_text())
    paths = config['application_paths'] + config['optional_resource_paths']
    if git(root, 'ls-files', '--others', '--ignored', '--exclude-standard', '--', *paths):
        raise ValueError('Ignored application files are not part of HEAD; isolate the package source')
    return head


def release_commit(root, tag, source_sha=None):
    if not re.fullmatch(r'v?\d+\.\d+\.\d+(?:-[0-9A-Za-z.-]+)?', tag):
        raise ValueError('Production CI requires a version tag, e.g. 0.1.0 or v0.1.0')
    sha = validate_sha(git(root, 'rev-parse', '--verify', 'refs/tags/' + tag + '^{commit}'))
    if source_sha is not None and validate_sha(source_sha) != sha:
        raise ValueError('Production source SHA differs from the selected version tag')
    result = subprocess.run(['git', '-C', str(root), 'merge-base', '--is-ancestor', sha, 'origin/main'])
    if result.returncode == 1:
        raise ValueError('Production version tag must point to a commit reachable from origin/main')
    result.check_returncode()
    return sha


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument('--source-sha')
    parser.add_argument('--release-tag')
    args = parser.parse_args()
    root = Path(__file__).resolve().parent.parent
    sha = release_commit(root, args.release_tag, args.source_sha) if args.release_tag else verify_checkout(root, args.source_sha)
    print(sha)


if __name__ == '__main__':
    try:
        main()
    except (ValueError, subprocess.CalledProcessError) as error:
        print(str(error), file=sys.stderr)
        sys.exit(1)
