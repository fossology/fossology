#!/usr/bin/env python3

# SPDX-FileContributor: © Rajul Jha <rajuljha49@gmail.com>
# SPDX-FileContributor: Gaurav Mishra <mishra.gaurav@siemens.com>
# SPDX-FileCopyrightText: © 2025 Siemens AG

# SPDX-License-Identifier: GPL-2.0-only

import os
import re
from bisect import bisect_right
from typing import Any

from .CliOptions import CliOptions


class FormatResult:
  """
  For formatting the results from scanners with line number information

  :ivar cli_options: CliOptions object
  """
  cli_options: CliOptions = None

  _RE_DIFF_HEADER = re.compile(r'^@@ -([0-9]+),([0-9]+) [+]([0-9]+),([0-9]+) @@')
  _RE_NON_CONTENT_LINE = re.compile(r'^(---|\+\+\+|[^-+ ])')


  def __init__(self, cli_options: CliOptions):
    self.cli_options = cli_options

  def format_diff(self, diff_content: str) -> str:
    """
    Format the diff content in a particular format with corrected line numbers.

    :param diff_content: String to format
    :return: formatted_string
    """
    formatted_diff = []
    diff_lines = diff_content.splitlines()
    left = right = 0
    left_num_len = right_num_len = 0
    for line in diff_lines:
      match = self._RE_DIFF_HEADER.match(line)
      if match:
        left = int(match.group(1))
        left_num_len = len(match.group(2))
        right = int(match.group(3))
        right_num_len = len(match.group(4))
        formatted_diff.append(line)
        continue

      if self._RE_NON_CONTENT_LINE.match(line):
        formatted_diff.append(line)
        continue

      # Remove the leading '+', '-', or ' '
      line_content = line[1:]
      if line.startswith('-'):
        padding = ' ' * right_num_len
        formatted_diff.append(
          f"-{left:<{left_num_len}} {padding}:{line_content}"
        )
        left += 1
      elif line.startswith('+'):
        padding = ' ' * left_num_len
        formatted_diff.append(
          f"+{padding} {right:<{right_num_len}}:{line_content}"
        )
        right += 1
      else:
        formatted_diff.append(
          f" {left:<{left_num_len}} {right:<{right_num_len}}:{line_content}"
        )
        left += 1
        right += 1

    return "\n".join(formatted_diff)

  def find_line_numbers(
    self, diff_string: str, word_start_byte: int, word_end_byte: int
  ) -> list[Any]:
    """
    Find line numbers from formatted diff data

    :param diff_string: Formatted diff string
    :param word_start_byte: Start byte of scanner result
    :param word_end_byte: End byte of scanner result
    :return: List of line_numbers found for a given word
    """
    escaped_word = re.escape(diff_string[word_start_byte:word_end_byte])
    pattern = re.compile(r'(\d+):.*?' + escaped_word)
    matches = pattern.findall(diff_string)
    return matches

  def find_word_line_numbers(
    self, file_path: str, words: list, key: str
  ) -> dict[str, set[str]] | None:
    """
    Find the line number of each word found for a given file path

    :param file_path: Path of the file to scan
    :param words: List of words(ScanResult Objects) to be scanned for
    :param key: Key to scan: 'contents' for copyright and keyword and 'license'
    for nomos and ojo
    :return: found_words_with_line_number : dict Dictionary of scanned results
              with key as scanned word and value as list of line_numbers where
              it is found.
    """
    found_words_with_line_number: dict[str, set[str]] = {}
    if (self.cli_options.repo or self.cli_options.scan_only_deps or
      self.cli_options.scan_dir):
      try:
        with open(file_path, 'rb') as file:
          binary_data = file.read()
        string_data = binary_data.decode('utf-8', errors='ignore')

        # line_start_offsets will store the byte offset of the first character of each line.
        # Example: "Hello\nWorld" -> [0, 6]
        line_start_offsets = [0]
        for i, char in enumerate(string_data):
          if char == '\n':
            line_start_offsets.append(i + 1)

        for word_info in words:
          word_start_byte = word_info['start']
          word_key_value = word_info[key]
          line_number = bisect_right(line_start_offsets, word_start_byte)
          found_words_with_line_number.setdefault(word_key_value,
                                                  set()).add(str(line_number))

        return found_words_with_line_number
      except Exception as e:
        print(f"An error occurred: {e}")
        return None
    else:
      with open(file_path, 'r') as file:
        content = file.read()
        for i in range(0, len(words)):
          line_numbers = self.find_line_numbers(
            content, words[i]['start'], words[i]['end']
          )
          found_words_with_line_number[words[i][f'{key}']] = set(line_numbers)
    return found_words_with_line_number

  def process_files(self, root_dir: str) -> None:
    """
    Format the files according to unified diff format

    :param root_dir: Path of the temp dir root to format the files
    :return: None
    """
    if (self.cli_options.repo or self.cli_options.scan_only_deps or
      self.cli_options.scan_dir):
      return None
    for root, dirs, files in os.walk(root_dir):
      for file_name in files:
        file_path = os.path.join(root, file_name)
        with open(file_path, 'r', encoding='UTF-8') as file:
          file_contents = file.read()
          try:
            normal_string = file_contents.encode().decode('unicode_escape')
          except UnicodeDecodeError:
            normal_string = file_contents
          formatted_diff = self.format_diff(normal_string)
        with open(file_path, 'w', encoding='utf-8') as file:
          file.write(formatted_diff)
    return None
