"""Trust and publication gates against concrete API projections, no GitHub writes."""
import importlib.util
import json
from pathlib import Path
import subprocess
import tempfile
import unittest
from unittest.mock import patch

MODULE = Path(__file__).resolve().parents[2] / 'bin/github-delivery.py'
spec = importlib.util.spec_from_file_location('github_delivery', MODULE)
delivery = importlib.util.module_from_spec(spec)
spec.loader.exec_module(delivery)
SHA = 'a' * 40
REPO = 'owner/project'


def ci_run():
    return dict(id=1, head_sha=SHA, path='.github/workflows/ci.yml', head_branch='main', event='push',
                status='completed', conclusion='success', repository={'full_name': REPO},
                head_repository={'full_name': REPO})


def staged():
    return dict(id=10, tag_name='delivery-' + SHA, draft=True, prerelease=False, assets=[
        dict(id=i, name=name, size=100, state='uploaded', updated_at='2026-10-20T00:00:00Z')
        for i, name in enumerate(['delivery.json', 'delivery.sig', 'runtime.tar.gz'])])


class MainCIGate(unittest.TestCase):
    def test_accepts_only_the_requested_successful_main_push(self):
        self.assertEqual(delivery.verify_ci({'workflow_runs': [ci_run()]}, REPO, SHA), 1)
        mutations = dict(head_sha='b' * 40, path='.github/workflows/other.yml', head_branch='topic',
                         event='pull_request', status='in_progress', conclusion='failure',
                         repository={'full_name': 'attacker/project'}, head_repository={'full_name': 'attacker/project'})
        for key, value in mutations.items():
            with self.subTest(key=key):
                run = ci_run()
                run[key] = value
                with self.assertRaises(delivery.DeliveryError):
                    delivery.verify_ci({'workflow_runs': [run]}, REPO, SHA)
        for value in (None, {}, {'workflow_runs': []}):
            with self.assertRaises(delivery.DeliveryError):
                delivery.verify_ci(value, REPO, SHA)

    def test_source_must_be_on_main_before_ci_and_version_are_used(self):
        for comparison in ({'status': 'ahead'}, {'status': 'diverged'},
                           {'status': 'behind', 'merge_base_commit': {'sha': 'b' * 40}}):
            with self.subTest(comparison=comparison), patch.object(delivery, 'api', return_value=comparison) as api:
                with self.assertRaises(delivery.DeliveryError):
                    delivery.verify_source(REPO, SHA, Path('.'))
                self.assertEqual(api.call_count, 1)
        with patch.object(delivery, 'api', side_effect=[{'status': 'identical', 'merge_base_commit': {'sha': SHA}},
                                                      {'workflow_runs': [ci_run()]}]), patch.object(delivery, 'command', return_value='3.0.1'):
            self.assertEqual(delivery.verify_source(REPO, SHA, Path('.')), '3.0.1')


class StageGate(unittest.TestCase):
    def test_requires_exact_private_draft_complete_safe_unique_assets(self):
        self.assertEqual(delivery.verify_stage(staged(), SHA), 10)
        for patch_data in ({'draft': False}, {'prerelease': True}, {'tag_name': 'delivery-' + 'b' * 40},
                           {'assets': []}, {'assets': [{'name': '../../key', 'size': 1, 'state': 'uploaded'}]}):
            value = staged()
            value.update(patch_data)
            with self.subTest(patch=patch_data), self.assertRaises(delivery.DeliveryError):
                delivery.verify_stage(value, SHA)
        for mutation in ('duplicate', 'missing', 'incomplete'):
            value = staged()
            if mutation == 'duplicate':
                value['assets'].append(value['assets'][0])
            elif mutation == 'missing':
                value['assets'].pop()
            else:
                value['assets'][0]['state'] = 'new'
            with self.subTest(mutation=mutation), self.assertRaises(delivery.DeliveryError):
                delivery.verify_stage(value, SHA)

    def test_only_docker_can_explicitly_accept_the_matching_published_stable_version(self):
        value = staged()
        value.update(tag_name='v3.0.1', draft=False)
        self.assertEqual(delivery.verify_stage(value, SHA, '3.0.1'), 10)
        for version in (None, '3.0.2'):
            with self.assertRaises(delivery.DeliveryError):
                delivery.verify_stage(value, SHA, version)

    def test_download_passes_trusted_source_verifier_not_a_key_from_bundle(self):
        from argparse import Namespace
        with tempfile.TemporaryDirectory() as temp:
            root = Path(temp)
            args = Namespace(sha=SHA, version='3.0.1', directory=root / 'download', kind='release',
                             output=None, allow_published=False)
            with patch.object(delivery, 'verify_source', return_value='3.0.1'), patch.object(delivery, 'api', return_value=[staged()]), \
                    patch.object(delivery.subprocess, 'run'), patch.object(delivery, 'command') as command:
                delivery.stage(args, REPO, root)
                verification = command.call_args.args
                self.assertEqual(verification[1], str(root / 'bin/delivery.py'))
                self.assertIn('verify', verification)
                self.assertNotIn('--public-key', verification)
                self.assertIn(SHA, verification)


class DraftLookupGate(unittest.TestCase):
    def test_walks_all_authenticated_pages_and_selects_the_exact_draft(self):
        unrelated = [dict(id=index, tag_name=f'v1.0.{index}', draft=False) for index in range(100)]
        with patch.object(delivery, 'api', side_effect=[unrelated, [staged()]]) as api:
            self.assertEqual(delivery.find_stage(REPO, SHA), staged())
            self.assertEqual([call.args[1] for call in api.call_args_list],
                             ['releases?per_page=100&page=1', 'releases?per_page=100&page=2'])
        with patch.object(delivery, 'api', return_value=[dict(tag_name='delivery-' + 'b' * 40, draft=True)]):
            self.assertIsNone(delivery.find_stage(REPO, SHA))

    def test_duplicate_staging_tags_and_malformed_lists_fail_closed(self):
        for response in ([staged(), staged()], {}, None):
            with self.subTest(response=response), patch.object(delivery, 'api', return_value=response):
                with self.assertRaises(delivery.DeliveryError):
                    delivery.find_stage(REPO, SHA)

    def test_api_error_never_falls_back_to_an_unrelated_or_published_release(self):
        from argparse import Namespace
        args = Namespace(sha=SHA, version='3.0.1', directory=Path('/unused'), kind='release',
                         output=None, allow_published=True)
        failure = subprocess.CalledProcessError(1, ['gh', 'api'], stderr='Forbidden (HTTP 403)')
        with patch.object(delivery, 'verify_source', return_value='3.0.1'), \
                patch.object(delivery, 'api', side_effect=failure) as api:
            with self.assertRaises(subprocess.CalledProcessError):
                delivery.stage(args, REPO, Path('.'))
            self.assertEqual(api.call_count, 1)

    def test_published_fallback_is_explicit_and_accepts_only_the_requested_version(self):
        from argparse import Namespace
        for allowed in (False, True):
            with tempfile.TemporaryDirectory() as temp:
                root = Path(temp)
                args = Namespace(sha=SHA, version='3.0.1', directory=root / 'download', kind='release',
                                 output=None, allow_published=allowed)
                stable = staged()
                stable.update(tag_name='v3.0.1', draft=False)
                with patch.object(delivery, 'verify_source', return_value='3.0.1'), \
                        patch.object(delivery, 'api', side_effect=[[], stable]) as api, \
                        patch.object(delivery.subprocess, 'run'), patch.object(delivery, 'command'):
                    if allowed:
                        delivery.stage(args, REPO, root)
                        self.assertEqual(api.call_args_list[1].args[1], 'releases/tags/v3.0.1')
                    else:
                        with self.assertRaises(delivery.DeliveryError):
                            delivery.stage(args, REPO, root)
                        self.assertEqual(api.call_count, 1)


class PublicationGate(unittest.TestCase):
    def test_any_existing_release_or_non404_api_failure_prevents_publication(self):
        from argparse import Namespace
        args = Namespace(sha=SHA, directory=Path('/unused'))
        for status, response in ((0, 'HTTP/2.0 200 OK'), (1, 'HTTP/2.0 403 Forbidden'), (1, 'HTTP/2.0 500 Server Error')):
            with self.subTest(response=response), patch.object(delivery, 'verify_source', return_value='3.0.1'), \
                    patch.object(delivery, 'verify_bundle'), patch.object(delivery, 'command', side_effect=['tag', SHA]), \
                    patch.object(delivery.subprocess, 'run', return_value=subprocess.CompletedProcess([], status, response, '')), \
                    self.assertRaises(delivery.DeliveryError):
                delivery.publish(args, REPO, Path('.'))

    def test_staging_replacement_after_verification_cannot_be_published(self):
        from argparse import Namespace
        with tempfile.TemporaryDirectory() as temp:
            directory = Path(temp) / 'download'
            before = staged()
            directory.with_suffix('.stage.json').write_text(json.dumps(before))
            after = staged()
            after['assets'][0]['id'] = 1000
            args = Namespace(sha=SHA, directory=directory)
            with patch.object(delivery, 'verify_source', return_value='3.0.1'), patch.object(delivery, 'verify_bundle'), \
                    patch.object(delivery, 'command', side_effect=['tag', SHA]), patch.object(delivery, 'api', return_value=[after]) as api, \
                    patch.object(delivery.subprocess, 'run', return_value=subprocess.CompletedProcess([], 1, 'HTTP/2.0 404 Not Found', '')):
                with self.assertRaises(delivery.DeliveryError):
                    delivery.publish(args, REPO, Path('.'))
                self.assertEqual(api.call_count, 1)


if __name__ == '__main__':
    unittest.main()
