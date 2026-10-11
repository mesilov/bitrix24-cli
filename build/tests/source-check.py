#!/usr/bin/env python3
"""Use real Git histories to check development provenance and production tags."""
import os
from pathlib import Path
import shutil
import subprocess
import sys
import tempfile
import unittest

BUILD = Path(__file__).resolve().parents[1]
sys.path.insert(0, str(BUILD))
from source import git, release_commit, verify_checkout


class SourceChecks(unittest.TestCase):
    def setUp(self):
        self.directory = tempfile.TemporaryDirectory(prefix='b24cli-source-')
        self.addCleanup(self.directory.cleanup)
        self.root = Path(self.directory.name)
        git(self.root, 'init', '--quiet', '--initial-branch=main')
        git(self.root, 'config', 'user.name', 'Packaging fixture')
        git(self.root, 'config', 'user.email', 'fixture@example.invalid')
        git(self.root, 'config', 'core.autocrlf', 'false')
        (self.root / 'build').mkdir()
        for name in ['source.py', 'package.py', 'runtime.py', 'package-linux.sh', 'config.json']:
            shutil.copy2(BUILD / name, self.root / 'build' / name)
        (self.root / 'src').mkdir()
        (self.root / 'src/application.txt').write_text('main source\n')
        (self.root / '.gitignore').write_text('runtime/\ndist/\nvendor/\nsrc/ignored.php\n')
        self.base = self.commit('base')
        (self.root / 'src/application.txt').write_text('main tip\n')
        self.tip = self.commit('main tip')
        git(self.root, 'update-ref', 'refs/remotes/origin/main', self.tip)

    def commit(self, message):
        git(self.root, 'add', '.')
        git(self.root, 'commit', '--quiet', '-m', message)
        return git(self.root, 'rev-parse', 'HEAD')

    def test_clean_tagless_feature_commit_is_allowed(self):
        git(self.root, 'switch', '--quiet', '-c', 'feature/test')
        (self.root / 'src/application.txt').write_text('feature source\n')
        head = self.commit('feature')
        self.assertEqual(verify_checkout(self.root), head)
        self.assertEqual(verify_checkout(self.root, head), head)
        self.assertEqual(git(self.root, 'tag', '--list'), '')

    def test_detached_older_commit_is_allowed(self):
        git(self.root, 'checkout', '--quiet', '--detach', self.base)
        self.assertEqual(verify_checkout(self.root), self.base)

    def test_dirty_tracked_file_is_rejected(self):
        (self.root / 'src/application.txt').write_text('not committed\n')
        with self.assertRaisesRegex(ValueError, 'clean checkout'):
            verify_checkout(self.root)

    def test_staged_file_is_rejected(self):
        (self.root / 'src/application.txt').write_text('staged\n')
        git(self.root, 'add', 'src/application.txt')
        with self.assertRaisesRegex(ValueError, 'clean checkout'):
            verify_checkout(self.root)

    def test_untracked_file_is_rejected(self):
        (self.root / 'extra.txt').write_text('untracked\n')
        with self.assertRaisesRegex(ValueError, 'clean checkout'):
            verify_checkout(self.root)

    def test_ignored_build_outputs_are_allowed(self):
        for name in ['runtime', 'dist', 'vendor']:
            (self.root / name).mkdir()
            (self.root / name / 'ignored.txt').write_text('generated\n')
        self.assertEqual(verify_checkout(self.root), self.tip)

    def test_ignored_application_file_is_rejected(self):
        (self.root / 'src/ignored.php').write_text('<?php echo "not committed";\n')
        self.assertEqual(git(self.root, 'status', '--porcelain'), '')
        with self.assertRaisesRegex(ValueError, 'Ignored application files'):
            verify_checkout(self.root)

    def test_incorrect_and_mismatched_sha_are_rejected(self):
        for sha in ['abc', 'z' * 40, self.base]:
            with self.subTest(sha=sha), self.assertRaises(ValueError):
                verify_checkout(self.root, sha)

    def test_main_version_tags_resolve_exact_commits(self):
        git(self.root, 'tag', '0.1.0', self.base)
        git(self.root, 'tag', '-a', 'v0.2.0', self.tip, '-m', 'annotated release')
        self.assertEqual(release_commit(self.root, '0.1.0'), self.base)
        self.assertEqual(release_commit(self.root, 'v0.2.0', self.tip), self.tip)
        with self.assertRaisesRegex(ValueError, 'differs from the selected'):
            release_commit(self.root, '0.1.0', self.tip)

    def test_feature_tag_and_non_version_tag_are_rejected(self):
        git(self.root, 'switch', '--quiet', '-c', 'feature/test')
        (self.root / 'src/application.txt').write_text('feature release\n')
        head = self.commit('feature')
        git(self.root, 'tag', 'v9.0.0', head)
        with self.assertRaisesRegex(ValueError, 'reachable from origin/main'):
            release_commit(self.root, 'v9.0.0')
        git(self.root, 'tag', 'latest', self.tip)
        with self.assertRaisesRegex(ValueError, 'version tag'):
            release_commit(self.root, 'latest')

    def test_all_builders_reject_dirty_inputs_before_staging(self):
        (self.root / 'src/application.txt').write_text('dirty\n')
        for target in ['phar', 'linux-amd64', 'macos-arm64', 'windows-amd64']:
            with self.subTest(target=target):
                result = subprocess.run([sys.executable, str(self.root / 'build/package.py'), target],
                                        capture_output=True, text=True)
                self.assertEqual(result.returncode, 1)
                self.assertIn('clean checkout', result.stderr)
        for name in ['runtime', 'dist']:
            self.assertFalse((self.root / name).exists(), 'Rejected build created ' + name)
        # Linux's supported Make wrapper must reject before submitting a Docker context.
        for target in ['phar', 'linux']:
            result = subprocess.run(['bash', str(self.root / 'build/package-linux.sh'), target],
                                    capture_output=True, text=True)
            self.assertEqual(result.returncode, 1)
            self.assertIn('clean checkout', result.stderr)

    def test_all_builders_reject_a_different_commit_before_staging(self):
        for target in ['phar', 'linux-amd64', 'macos-arm64', 'windows-amd64']:
            with self.subTest(target=target):
                result = subprocess.run([sys.executable, str(self.root / 'build/package.py'), target,
                                         '--source-sha', self.base], capture_output=True, text=True)
                self.assertEqual(result.returncode, 1)
                self.assertIn('differs from checkout HEAD', result.stderr)
        for target in ['phar', 'linux']:
            result = subprocess.run(['bash', str(self.root / 'build/package-linux.sh'), target],
                                    env={**os.environ, 'B24CLI_SOURCE_SHA': self.base},
                                    capture_output=True, text=True)
            self.assertEqual(result.returncode, 1)
            self.assertIn('differs from checkout HEAD', result.stderr)
        self.assertFalse((self.root / 'runtime').exists())
        self.assertFalse((self.root / 'dist').exists())


if __name__ == '__main__':
    unittest.main()
