#!/usr/bin/env python3

# SPDX-FileContributor: © Rajul Jha <rajuljha49@gmail.com>

# SPDX-License-Identifier: GPL-2.0-only

import re
import os

from .CliOptions import CliOptions

class FormatResult:
    """
    For formatting the results from scanners with line number information

    :ivar cli_options: CliOptions object
    """

    def __init__(self,cli_options:CliOptions):
        self.cli_options = cli_options
    
    def format_diff(self,diff_content):
      """
      Format the diff content in a particular format with corrected line numbers.

      :param: diff_content: str String to format
      :return: str formatted_string
      """
      formatted_diff = []
      diff_lines = diff_content.splitlines()
      left = right = 0
      ll = rl = 0
      for line in diff_lines:
        match = re.match(r'^@@ -([0-9]+),([0-9]+) [+]([0-9]+),([0-9]+) @@', line)
        if match:
          left = int(match.group(1))
          ll = len(match.group(2))
          right = int(match.group(3))
          rl = len(match.group(4))
          formatted_diff.append(line)
          continue
          
        if re.match(r'^(---|\+\+\+|[^-+ ])', line):
          formatted_diff.append(line)
          continue
        line_content = line[1:]
        if line.startswith('-'):
          padding = ' ' * rl
          formatted_diff.append(f"-{left:<{ll}} {padding}:{line_content}")
          left += 1
        elif line.startswith('+'):
          padding = ' ' * ll
          formatted_diff.append(f"+{padding} {right:<{rl}}:{line_content}")
          right += 1
        else:
          formatted_diff.append(f" {left:<{ll}} {right:<{rl}}:{line_content}")
          left += 1
          right += 1

      return "\n".join(formatted_diff)

    def find_line_numbers(self, diff_string, word_start_byte, word_end_byte):
      """
      Find line numbers from formmatted diff data

      :param: diff_string : str Formatted diff string 
      :param: word_start_byte : int Start byte of scanner result
      :param: word_end_byte : int End byte of scanner result
      :return: List of line_numbers found for a given word
      """
      escaped_word = re.escape(diff_string[word_start_byte:word_end_byte])
      # pattern = re.compile(r'(\d+):.*\b' + escaped_word + r'\b')
      pattern = re.compile(r'(\d+):.*?' + escaped_word)
      matches = pattern.findall(diff_string)
      return matches

    def find_word_line_numbers(self, file_path, words:list) -> dict:
      """
      Find the line number of each word found for a given file path

      :param: file_path : str Path of the file to scan
      :param: words: list List of words(ScanResult Objects) to be scanned for
      :return: found_words_with_line_number : dict Dictionary of scanned results
                with key as scanned word and value as list of line_numbers where 
                it is found.
      """
      if self.cli_options.repo is True:
          return {}
      found_words_with_line_number = {}
      with open(file_path, 'r') as file:
          content = file.read()
          for i in range(0,len(words)):
              line_numbers = self.find_line_numbers(content, words[i]['start'], words[i]['end'])
              found_words_with_line_number[words[i]['content']] = line_numbers
      return found_words_with_line_number

    def process_files(self, root_dir):
      """
      Format the files according to unified diff format
      
      :param: root_dir : str Path of the temp dir root to format the files
      :return: None
      """
      if self.cli_options.repo is True:
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
