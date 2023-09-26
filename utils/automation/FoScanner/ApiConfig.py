#!/usr/bin/env python3

# SPDX-FileCopyrightText: © 2023 Siemens AG
# SPDX-FileContributor: Gaurav Mishra <mishra.gaurav@siemens.com>

# SPDX-License-Identifier: GPL-2.0-only

from enum import Enum
from typing import Optional


class Runner(Enum):
  """
  Type of runners
  """
  GITLAB = 0
  TRAVIS = 1
  GITHUB = 2


class ApiConfig:
  """
  Hold configurations required for different APIs to pull code.

  :ivar running_on: which CI the script is running
  :ivar travis_repo_slug: repo slug provided for Travis
  :ivar travis_pull_request: pull request id provided for Travis
  :ivar api_url: api url to use for GitLab
  :ivar project_id: project id for GitLab
  :ivar mr_iid: merge request id for GitLab
  :ivar api_token: token used for api authentication
  :ivar github_repo_slug: repo slug provided for GitHub
  :ivar github_pull_request: pull request id provided for GitHub
  :ivar project_name: project name
  :ivar project_desc: project description
  :ivar project_orig: project originator
  :ivar project_url: project URL
  """
  running_on: Runner = None
  travis_repo_slug: Optional[str] = None
  travis_pull_request: Optional[str] = None
  api_url: Optional[str] = None
  project_id: Optional[str] = None
  mr_iid: Optional[str] = None
  api_token: Optional[str] = None
  github_repo_slug: Optional[str] = None
  github_pull_request: Optional[str] = None
  project_name: str = ""
  project_desc: Optional[str] = None
  project_orig: str = None
  project_url: str = None
