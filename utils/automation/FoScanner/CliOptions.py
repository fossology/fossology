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
  :ivar scan_dir: Scan a particular subdirectory
  :ivar tags: tuple of length 2: (from_tag , to_tag) to scan
  :ivar diff_dir: directory to scan
  :ivar dir_path: Path to subdirectory to scan
  :ivar keyword_conf_file_path: path to custom keyword.conf file passed by user
  :ivar allowlist_path: path to allowlist.json file
  :ivar allowlist: information from allowlist.json
  :ivar report_format: Report format to use
  :ivar scan_only_deps: Scan only dependencies
  :ivar sbom_path: Path to sbom file
  :ivar parser: Parser instance to hold list of parsed components
  """
  nomos: bool = False
  ojo: bool = False
  copyright: bool = False
  keyword: bool = False
  repo: bool = False
  differential: bool = False
  scan_dir: bool = False
  tags: tuple = ('', '')
  diff_dir: str = os.getcwd()
  dir_path: str = ''
  keyword_conf_file_path: str = ''
  allowlist_path: str = None
  allowlist: dict[str, list[str]] = {
    'licenses': [],
    'exclude': []
  }
  report_format: ReportFormat = ReportFormat.TEXT
  scan_only_deps: bool = False
  sbom_path: str = ''
  parser = None

  def update_args(self, args: Namespace):
    """
    Update options based on argsparse values.

    :param args: Argparse from cli
    """
    # Convert args.operation to a set for efficient lookups
    operations = set(args.operation) if hasattr(args, 'operation') else set()

    self.nomos = 'nomos' in operations
    self.copyright = 'copyright' in operations
    self.keyword = 'keyword' in operations
    self.ojo = 'ojo' in operations

    if 'repo' in operations and 'differential' in operations:
      raise ValueError(
        "You can only specify either 'repo' or 'differential' scans at a time."
      )

    self.repo = 'repo' in operations
    self.differential = 'differential' in operations
    self.scan_only_deps = 'scan-only-deps' in operations
    self.scan_dir = 'scan-dir' in operations

    if self.scan_dir and args.dir_path != '':
      self.dir_path = args.dir_path
    if args.tags is not None and self.differential and len(args.tags) == 2:
      self.tags = (args.tags[0], args.tags[1])
    if args.allowlist_path:
      self.allowlist_path = args.allowlist_path

    # If no specific scanner is selected, enable all by default.
    if not (self.nomos or self.ojo or self.copyright or self.keyword):
      self.nomos = self.ojo = self.copyright = self.keyword = True

    self.report_format = ReportFormat[args.report]
    if self.keyword and args.keyword_conf:
      self.keyword_conf_file_path = args.keyword_conf
    if (self.scan_only_deps or self.repo) and args.sbom_path:
      self.sbom_path = args.sbom_path
