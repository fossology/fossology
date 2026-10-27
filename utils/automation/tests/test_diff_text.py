# SPDX-FileCopyrightText: 2026 Shubham Padkonde <shubhampadkonde12@gmail.com>
# SPDX-License-Identifier: GPL-2.0-only

import os
import tempfile
import unittest

from FoScanner.CliOptions import CliOptions
from FoScanner.FormatResults import FormatResult


class DiffTextTest(unittest.TestCase):
  def test_process_files_preserves_unicode_and_literal_escapes(self):
    for text in ('Copyright José', r'pattern = "\n\t\u0041"', 'plain ASCII'):
      with self.subTest(text=text):
        with tempfile.TemporaryDirectory() as directory:
          path = os.path.join(directory, 'example.py')
          with open(path, 'w', encoding='utf-8') as stream:
            stream.write('@@ -1,0 +1,1 @@\n+' + text + '\n')

          FormatResult(CliOptions()).process_files(directory)

          with open(path, encoding='utf-8') as stream:
            result = stream.read()
          self.assertEqual(result, '@@ -1,0 +1,1 @@\n+  1:' + text)
