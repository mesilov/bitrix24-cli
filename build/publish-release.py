#!/usr/bin/env python3
"""Resume draft creation/upload/publication after an interrupted CI attempt."""
import argparse
import json
from pathlib import Path
import subprocess
import sys

ASSETS = ['b24cli-linux-amd64.tar.gz', 'b24cli-macos-arm64.tar.gz',
          'b24cli-windows-amd64.zip', 'SHA256SUMS']


def publish(tag, directory, run=subprocess.run):
    files = [str(Path(directory) / name) for name in ASSETS]
    if not all(Path(name).is_file() for name in files):
        raise ValueError('Complete verified release assets are required')
    # gh release view also finds drafts by their pending tag name.
    # https://github.com/cli/cli/blob/trunk/pkg/cmd/release/shared/fetch.go
    result = run(['gh', 'release', 'view', tag, '--json', 'isDraft'],
                 capture_output=True, text=True, encoding='utf-8')
    if result.returncode:
        if result.stderr.strip() != 'release not found':
            raise RuntimeError('Cannot inspect release: ' + result.stderr.strip())
        run(['gh', 'release', 'create', tag, '--verify-tag', '--draft',
             '--title', tag, '--generate-notes'], check=True)
    else:
        draft = json.loads(result.stdout)['isDraft']
        if not isinstance(draft, bool):
            raise ValueError('Invalid draft state returned by GitHub CLI')
        if not draft:
            print('Release already published; leaving its assets unchanged')
            return
    run(['gh', 'release', 'upload', tag, *files, '--clobber'], check=True)
    run(['gh', 'release', 'edit', tag, '--draft=false', '--latest'], check=True)


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument('tag')
    parser.add_argument('assets', type=Path)
    args = parser.parse_args()
    subprocess.run([sys.executable, str(Path(__file__).parent / 'tests/archive-check.py'),
                    str(args.assets)], check=True)
    publish(args.tag, args.assets)


if __name__ == '__main__':
    try:
        main()
    except (ValueError, RuntimeError, OSError, subprocess.CalledProcessError) as error:
        print(str(error), file=sys.stderr)
        sys.exit(1)
