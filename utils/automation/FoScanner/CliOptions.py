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
  :ivar diff_dir: directory to scan
  :ivar allowlist: information from allowlist.json
  :ivar report_format: Report format to use
  """
  nomos: bool = False
  ojo: bool = False
  copyright: bool = False
  keyword: bool = False
  repo: bool = False
  diff_dir: str = os.getcwd()
  allowlist: dict[str, list[str]] = {
    'licenses': [],
    'exclude': []
  }
  report_format: ReportFormat = ReportFormat.TEXT

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
    if "repo" in args.operation:
      self.repo = True
    if self.nomos is False and self.ojo is False and self.copyright is False \
        and self.keyword is False:
      self.nomos = True
      self.ojo = True
      self.copyright = True
      self.keyword = True
    self.report_format = ReportFormat[args.report]
