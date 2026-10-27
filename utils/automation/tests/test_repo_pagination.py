# SPDX-FileCopyrightText: 2026 Shubham Padkonde <shubhampadkonde12@gmail.com>
# SPDX-License-Identifier: GPL-2.0-only

import json
import os
import unittest
from unittest.mock import MagicMock, patch

from FoScanner.ApiConfig import ApiConfig, Runner
from FoScanner.CliOptions import CliOptions
from FoScanner.RepoSetup import RepoSetup


class RepoPaginationTest(unittest.TestCase):
  def response(self, body, links=''):
    response = MagicMock()
    response.__enter__.return_value = response
    response.read.return_value = json.dumps(body).encode('utf-8')
    response.headers = {'Link': links}
    return response

  def setup_repo(self, runner):
    config = ApiConfig()
    config.running_on = runner
    config.api_url = 'https://api.github.com'
    config.api_token = 'test-token'
    config.github_repo_slug = config.travis_repo_slug = 'owner/repo'
    config.github_pull_request = config.travis_pull_request = '1'
    return RepoSetup(CliOptions(), config)

  def test_collects_all_github_file_pages(self):
    for runner in (Runner.GITHUB, Runner.TRAVIS):
      with self.subTest(runner=runner):
        repo = self.setup_repo(runner)
        self.addCleanup(repo.temp_dir.cleanup)
        next_url = 'https://api.github.com/repos/owner/repo/pulls/1/files?page=2'
        pages = [
          self.response(
            [{'filename': 'first.txt', 'patch': 'first patch'}],
            f'<{next_url}>; rel="next", <{next_url}>; rel="last"'
          ),
          self.response(
            [{'filename': 'nested/second.txt', 'patch': 'second patch'},
             {'filename': 'binary.dat'}],
            '<https://api.github.com/repos/owner/repo/pulls/1/files>; rel="prev"'
          ),
        ]
        with patch('FoScanner.RepoSetup.urllib.request.urlopen', side_effect=pages) as request:
          directory = repo.get_diff_dir()
        self.assertEqual(request.call_count, 2)
        self.assertEqual(request.call_args_list[1].args[0].full_url, next_url)
        expected_auth = 'Bearer test-token' if runner == Runner.GITHUB else None
        for call in request.call_args_list:
          self.assertEqual(call.args[0].get_header('Authorization'), expected_auth)
        for filename, content in (('first.txt', 'first patch'), ('nested/second.txt', 'second patch')):
          with open(os.path.join(directory, filename), encoding='utf-8') as stream:
            self.assertEqual(stream.read(), content)
        self.assertFalse(os.path.exists(os.path.join(directory, 'binary.dat')))

  def test_compare_does_not_collect_commit_pages_as_files(self):
    repo = self.setup_repo(Runner.GITHUB)
    self.addCleanup(repo.temp_dir.cleanup)
    repo.cli_options.differential = True
    repo.cli_options.tags = ('v1', 'v2')
    response = self.response(
      {'files': [{'filename': 'first.txt', 'patch': 'patch'}]},
      '<https://api.github.com/repos/owner/repo/compare/v1...v2?page=2>; rel="next"'
    )
    with patch('FoScanner.RepoSetup.urllib.request.urlopen', return_value=response) as request:
      directory = repo.get_diff_dir()
    self.assertEqual(request.call_count, 1)
    self.assertTrue(os.path.exists(os.path.join(directory, 'first.txt')))

  def test_rejects_pagination_link_to_another_origin(self):
    repo = self.setup_repo(Runner.GITHUB)
    self.addCleanup(repo.temp_dir.cleanup)
    response = self.response([], '<https://other.example/files?page=2>; rel="next"')
    with patch('FoScanner.RepoSetup.urllib.request.urlopen', return_value=response) as request:
      with self.assertRaisesRegex(ValueError, 'origin'):
        repo.get_diff_dir()
    self.assertEqual(request.call_count, 1)

  def test_gitlab_changes_are_still_read_once(self):
    repo = self.setup_repo(Runner.GITLAB)
    self.addCleanup(repo.temp_dir.cleanup)
    repo.api_config.api_url = 'https://gitlab.example/api/v4'
    repo.api_config.project_id = '1'
    repo.api_config.mr_iid = '2'
    response = self.response({'changes': [{'new_path': 'first.txt', 'diff': 'patch'}]})
    with patch('FoScanner.RepoSetup.urllib.request.urlopen', return_value=response) as request:
      directory = repo.get_diff_dir()
    self.assertEqual(request.call_count, 1)
    self.assertEqual(request.call_args.args[0].get_header('Private-token'), 'test-token')
    self.assertTrue(os.path.exists(os.path.join(directory, 'first.txt')))

  def test_rejects_unexpected_next_page_shape(self):
    repo = self.setup_repo(Runner.GITHUB)
    self.addCleanup(repo.temp_dir.cleanup)
    pages = [
      self.response([], '<https://api.github.com/repos/owner/repo/pulls/1/files?page=2>; rel="next"'),
      self.response({'message': 'unexpected response'}),
    ]
    with patch('FoScanner.RepoSetup.urllib.request.urlopen', side_effect=pages):
      with self.assertRaisesRegex(ValueError, 'list of files'):
        repo.get_diff_dir()
