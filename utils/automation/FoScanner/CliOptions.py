#!/usr/bin/env python3

# SPDX-FileCopyrightText: © 2023 Siemens AG
# SPDX-FileContributor: Gaurav Mishra <mishra.gaurav@siemens.com>

# SPDX-License-Identifier: GPL-2.0-only

import os
from argparse import Namespace
from enum import Enum


class ReportFormat(Enum):
  """
  Report formats supported by the script.
  """
  TEXT = 0
  SPDX_JSON = 1
  SPDX_RDF = 2
  SPDX_TAG_VALUE = 3
  SPDX_YAML = 4


class CliOptions(object):
  """
  Hold the various shared flags and data

  :ivar nomos: run nomos scanner
  :ivar ojo: run ojo scanner
  :ivar copyright: run copyright scanner
  :ivar keyword: run keyword scanner
  :ivar repo: scan whole repo or just diff
  :ivar differential: scan between two versions of a repo
  :ivar tags: tuple of length 2: (from_tag , to_tag) to scan
  :ivar diff_dir: directory to scan
  :ivar keyword_conf_file_path: path to custom keyword.conf file passed by user
  :ivar allowlist_path: path to allowlist.json file
  :ivar allowlist: information from allowlist.json
  :ivar report_format: Report format to use
  :ivar scan_only_deps: Scan only dependencies
  :ivar sbom_path: Path to sbom file
  """
  nomos: bool = False
  ojo: bool = False
  copyright: bool = False
  keyword: bool = False
  repo: bool = False
  differential: bool = False
  tags: tuple = ('','')
  diff_dir: str = os.getcwd()
  keyword_conf_file_path : str = ''
  allowlist_path: str = None
  allowlist: dict[str, list[str]] = {
    'licenses': [],
    'exclude': []
  }
  report_format: ReportFormat = ReportFormat.TEXT
  scan_only_deps: bool = False
  sbom_path : str = ''

  def update_args(self, args: Namespace):
    """
    Update options based on argsparse values.

    :param args: Argparse from cli
    """
    if "nomos" in args.operation:
      self.nomos = True
    if "copyright" in args.operation:
      self.copyright = True
    if "keyword" in args.operation:
      self.keyword = True
    if "ojo" in args.operation:
      self.ojo = True
    if 'repo' in args.operation and 'differential' in args.operation:
      raise ValueError("You can only specify either 'repo' or 'differential' scans at a time.")
    if "repo" in args.operation:
      self.repo = True
    if "differential" in args.operation:
      self.differential = True
    if 'scan-only-deps' in args.operation:
      self.scan_only_deps = True
    if args.tags is not None and self.differential and len(args.tags) == 2:
      self.tags = (args.tags[0],args.tags[1])
    if args.allowlist_path:
      self.allowlist_path = args.allowlist_path
    if self.nomos is False and self.ojo is False and self.copyright is False \
        and self.keyword is False:
      self.nomos = True
      self.ojo = True
      self.copyright = True
      self.keyword = True
    self.report_format = ReportFormat[args.report]
    if self.keyword and args.keyword_conf:
      self.keyword_conf_file_path = args.keyword_conf
    if (self.scan_only_deps or self.repo) and args.sbom_path:
      self.sbom_path = args.sbom_path
