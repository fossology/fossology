#!/usr/bin/env python3

# SPDX-FileCopyrightText: © 2026 Siemens AG
# SPDX-FileContributor: Shatakshi Tiwari <shatakshi.tiwari@siemens.com>
#
# SPDX-License-Identifier: GPL-2.0-only

"""
Abstract base class for all report generators.
"""

from abc import ABC, abstractmethod

from .CliOptions import CliOptions
from .Scanners import Scanners


class ReportBase(ABC):
  """
  Interface contract for report generators.

  Every report class must accept ``cli_options`` and ``scanner`` at
  construction time and implement :meth:`finalize_document` and
  :meth:`write_report`.
  """

  def __init__(self, cli_options: CliOptions, scanner: Scanners):
    """
    :param cli_options: Resolved CLI options for the current run.
    :param scanner:     Scanner instance holding scan results.
    """
    self.cli_options = cli_options
    self.scanner = scanner

  @abstractmethod
  def finalize_document(self) -> None:
    """
    Process scan results and assemble the internal document model.

    Called after all scans have completed but before writing.
    """

  @abstractmethod
  def write_report(self, file_name: str) -> None:
    """
    Validate and serialize the report to *file_name*.

    :param file_name: Destination path for the generated report.
    :raises RuntimeError: If validation or serialization fails.
    """

