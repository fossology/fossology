# SPDX-FileCopyrightText: 2026 Shubham Padkonde <shubhampadkonde12@gmail.com>
# SPDX-License-Identifier: GPL-2.0-only

import unittest

from FoScanner.CliOptions import CliOptions
from FoScanner.FormatResults import FormatResult


class DiffHunkCountsTest(unittest.TestCase):
  def test_optional_hunk_counts(self):
    formatter = FormatResult(CliOptions())
    cases = [
      ('@@ -9 +9 @@', '-old\n+new', '-9  :old\n+  9:new'),
      ('@@ -10,0 +11 @@', '+new', '+  11:new'),
      ('@@ -12 +11,0 @@', '-old', '-12  :old'),
      ('@@ -9,1 +9,1 @@', '-old\n+new', '-9  :old\n+  9:new'),
    ]
    for header, content, expected in cases:
      with self.subTest(header=header):
        self.assertEqual(
          formatter.format_diff(header + '\n' + content),
          header + '\n' + expected
        )

  def test_later_single_line_hunk_resets_line_numbers(self):
    diff = '@@ -9,1 +9,1 @@\n-old\n+new\n@@ -30 +30 @@\n-next\n+replacement'
    expected = (
      '@@ -9,1 +9,1 @@\n-9  :old\n+  9:new\n'
      '@@ -30 +30 @@\n-30  :next\n+  30:replacement'
    )
    self.assertEqual(FormatResult(CliOptions()).format_diff(diff), expected)
