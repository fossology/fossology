#!/usr/bin/env python3

# SPDX-FileCopyrightText: © 2023 Siemens AG
# SPDX-FileContributor: Gaurav Mishra <mishra.gaurav@siemens.com>

# SPDX-License-Identifier: GPL-2.0-only

import fnmatch
import json
import os
import re
import ssl
import urllib.request
from tempfile import TemporaryDirectory
from typing import Union

from .ApiConfig import ApiConfig, Runner
from .CliOptions import CliOptions


class RepoSetup:
  """
  Setup temp_dir using the diff or current MR

  :ivar temp_dir: Temporary directory location for storing MR changes.
  :ivar allowlist: Allow list from JSON
  :ivar api_config: ApiConfig
  """

  def __init__(self, cli_options: CliOptions, api_config: ApiConfig):
    """
    Create a temp dir

    :param cli_options: CliOptions object to get allow list from
    :param api_config: API configuration for the CI
    """
    self.temp_dir: Union[TemporaryDirectory[str], TemporaryDirectory[bytes]] \
      = TemporaryDirectory()
    self.allowlist: dict[str, list[str]] = cli_options.allowlist
    self.api_config: ApiConfig = api_config

  def __del__(self):
    """
    Clean the created temp dir
    """
    self.temp_dir.cleanup()

  def __is_excluded_path(self, path: str) -> bool:
    """
    Check if the path is allow listed

    The function used fnmatch to check if the path is in allow list or not.

    :param path: path to check
    :return: True if the path is in allow list, False otherwise
    """
    path_is_excluded = False
    for pattern in self.allowlist['exclude']:
      if fnmatch.fnmatchcase(path, pattern):
        path_is_excluded = True
        break
    return path_is_excluded

  def get_diff_dir(self) -> str:
    """
    Populate temp dir using the gitlab API `merge_requests`

    :return: temp dir path
    """
    if self.api_config.running_on == Runner.GITLAB:
      api_req_url = f"{self.api_config.api_url}/projects/" \
                    f"{self.api_config.project_id}/merge_requests/" \
                    f"{self.api_config.mr_iid}/changes"
      headers = {'Private-Token': self.api_config.api_token}
      path_key = "new_path"
      change_key = "diff"
    elif self.api_config.running_on == Runner.GITHUB:
      api_req_url = f"{self.api_config.api_url}/repos/" \
                    f"{self.api_config.github_repo_slug}/pulls/" + \
                    f"{self.api_config.github_pull_request}/files"
      headers = {
        "Authorization": f"Bearer {self.api_config.api_token}",
        "X-GitHub-Api-Version": "2022-11-28",
        "Accept": "application/vnd.github+json"
      }
      path_key = "filename"
      change_key = "patch"
    else:
      api_req_url = "https://api.github.com/repos/" \
                    f"{self.api_config.travis_repo_slug}/pulls/" \
                    f"{self.api_config.travis_pull_request}/files"
      headers = {}
      path_key = "filename"
      change_key = "patch"

    context = ssl.create_default_context()
    context.check_hostname = False
    context.verify_mode = ssl.CERT_NONE

    req = urllib.request.Request(api_req_url, headers=headers)
    try:
      with urllib.request.urlopen(req, context=context) as response:
        change_response = response.read()
    except Exception as e:
      print(f"Unable to get URL {api_req_url}")
      raise e

    change_response = json.loads(change_response)
    if self.api_config.running_on == Runner.GITLAB:
      changes = change_response['changes']
    else:
      changes = change_response

    for change in changes:
      if path_key in change and change_key in change:
        path_to_be_excluded = self.__is_excluded_path(change[path_key])
        if path_to_be_excluded is False:
          curr_file = os.path.join(self.temp_dir.name, change[path_key])
          curr_dir = os.path.dirname(curr_file)
          if curr_dir != self.temp_dir.name:
            os.makedirs(name=curr_dir, exist_ok=True)
          curr_file = open(file=curr_file, mode='w+', encoding='UTF-8')
          print(change[change_key],file=curr_file)

    return self.temp_dir.name
