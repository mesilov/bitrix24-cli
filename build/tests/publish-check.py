#!/usr/bin/env python3
"""Exercise interrupted release state transitions without contacting GitHub."""
import importlib.util
import json
from pathlib import Path
import subprocess
import sys
import tempfile
import unittest
from unittest.mock import patch

BUILD = Path(__file__).resolve().parents[1]
spec = importlib.util.spec_from_file_location('publish_release', BUILD / 'publish-release.py')
release = importlib.util.module_from_spec(spec)
spec.loader.exec_module(release)


class FakeGitHub:
    def __init__(self, draft=None, fail=None):
        self.draft = draft
        self.fail = fail
        self.calls = []
        self.assets = set()

    def __call__(self, command, **kwargs):
        self.calls.append(command)
        action = command[2]
        if action == 'view':
            if self.fail == 'lookup':
                return subprocess.CompletedProcess(command, 1, '', 'HTTP 403: permission denied')
            if self.draft is None:
                return subprocess.CompletedProcess(command, 1, '', 'release not found\n')
            return subprocess.CompletedProcess(command, 0, json.dumps({'isDraft': self.draft}), '')
        if action == 'create':
            if self.draft is not None:
                raise AssertionError('Duplicate create')
            self.draft = True
        if action == 'upload':
            self.assets.add(Path(command[4]).name)
        if self.fail == action:
            self.fail = None
            raise subprocess.CalledProcessError(1, command)
        if action == 'upload':
            self.assets = {Path(value).name for value in command[4:-1]}
        if action == 'edit':
            if self.assets != set(release.ASSETS):
                raise AssertionError('Publishing before complete upload')
            self.draft = False
        return subprocess.CompletedProcess(command, 0)


class PublishChecks(unittest.TestCase):
    def setUp(self):
        self.directory = tempfile.TemporaryDirectory(prefix='b24cli-publish-')
        self.addCleanup(self.directory.cleanup)
        self.assets = Path(self.directory.name)
        for name in release.ASSETS:
            (self.assets / name).write_bytes(b'verified by archive-check before publish')

    def test_first_publication_uploads_every_asset_before_edit(self):
        gh = FakeGitHub()
        release.publish('v0.1.0', self.assets, gh)
        self.assertFalse(gh.draft)
        self.assertEqual([call[2] for call in gh.calls], ['view', 'create', 'upload', 'edit'])
        self.assertIn('--verify-tag', gh.calls[1])
        self.assertIn('--draft', gh.calls[1])
        self.assertIn('--clobber', gh.calls[2])
        self.assertEqual(gh.assets, set(release.ASSETS))

    def test_interrupted_create_upload_and_publish_resume_draft(self):
        for action in ['create', 'upload', 'edit']:
            with self.subTest(action=action):
                gh = FakeGitHub(fail=action)
                with self.assertRaises(subprocess.CalledProcessError):
                    release.publish('v0.1.0', self.assets, gh)
                self.assertTrue(gh.draft)
                release.publish('v0.1.0', self.assets, gh)
                self.assertFalse(gh.draft)
                self.assertEqual(sum(call[2] == 'create' for call in gh.calls), 1)
                self.assertEqual(gh.assets, set(release.ASSETS))

    def test_existing_partial_draft_is_repaired(self):
        gh = FakeGitHub(draft=True)
        gh.assets = {release.ASSETS[0]}
        release.publish('v0.1.0', self.assets, gh)
        self.assertEqual([call[2] for call in gh.calls], ['view', 'upload', 'edit'])
        self.assertEqual(gh.assets, set(release.ASSETS))

    def test_retry_after_success_preserves_public_assets(self):
        gh = FakeGitHub()
        release.publish('v0.1.0', self.assets, gh)
        before = list(gh.calls)
        release.publish('v0.1.0', self.assets, gh)
        self.assertEqual(gh.calls[len(before):], [['gh', 'release', 'view', 'v0.1.0', '--json', 'isDraft']])

    def test_lookup_error_cannot_create_or_publish(self):
        gh = FakeGitHub(fail='lookup')
        with self.assertRaisesRegex(RuntimeError, 'permission denied'):
            release.publish('v0.1.0', self.assets, gh)
        self.assertEqual(len(gh.calls), 1)
        self.assertIsNone(gh.draft)

    def test_incomplete_assets_cannot_call_github(self):
        (self.assets / release.ASSETS[1]).unlink()
        gh = FakeGitHub()
        with self.assertRaisesRegex(ValueError, 'Complete verified release assets'):
            release.publish('v0.1.0', self.assets, gh)
        self.assertEqual(gh.calls, [])

    def test_archive_failure_stops_before_github(self):
        def reject_archive(command, **kwargs):
            self.assertEqual(command[:2], [sys.executable, str(BUILD / 'tests/archive-check.py')])
            raise subprocess.CalledProcessError(1, command)

        with patch.object(sys, 'argv', ['publish-release.py', 'v0.1.0', str(self.assets)]), \
                patch.object(release.subprocess, 'run', side_effect=reject_archive) as run:
            with self.assertRaises(subprocess.CalledProcessError):
                release.main()
            self.assertEqual(run.call_count, 1)


if __name__ == '__main__':
    unittest.main()
