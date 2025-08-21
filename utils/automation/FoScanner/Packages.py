#!/usr/bin/env python3

# SPDX-FileCopyrightText: © 2025 Siemens AG
# SPDX-FileContributor: Gaurav Mishra <mishra.gaurav@siemens.com>

# SPDX-License-Identifier: GPL-2.0-only


class Packages(object):
  """
  Class to hold the list of packages and their information.

  :ivar parent_package: Parent package during the scan
  :ivar dependencies: List of dependencies of the parent package
  """
  parent_package: dict = None
  dependencies: dict[str, dict] = {}
