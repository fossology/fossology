# SPDX-FileCopyrightText: 2026 Shubham Padkonde <shubhampadkonde12@gmail.com>
# SPDX-License-Identifier: GPL-2.0-only

import os
import tempfile
import unittest

from FoScanner.CliOptions import CliOptions
from FoScanner.FormatResults import FormatResult


class ByteLineNumbersTest(unittest.TestCase):
  def test_scanner_offsets_are_bytes(self):
    for mode in ('repo', 'scan_dir', 'scan_only_deps'):
      for prefix in (b'plain', 'é'.encode('utf-8') * 10, b'\xff' * 10):
        with self.subTest(mode=mode, prefix=prefix):
          options = CliOptions()
          setattr(options, mode, True)
          formatter = FormatResult(options)
          data = prefix + b'\nCopyright example\nlast line\n'
          words = [
            {'start': data.index(b'Copyright'), 'contents': 'Copyright example'},
            {'start': data.index(b'last'), 'contents': 'last line'},
          ]
          with tempfile.TemporaryDirectory() as directory:
            path = os.path.join(directory, 'source.txt')
            with open(path, 'wb') as stream:
              stream.write(data)
            self.assertEqual(
              formatter.find_word_line_numbers(path, words, 'contents'),
              {'Copyright example': {'2'}, 'last line': {'3'}}
            )
