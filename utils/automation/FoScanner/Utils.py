#!/usr/bin/env python3

# SPDX-FileCopyrightText: © 2024 Rajul Jha <rajuljha49@gmail.com>

# SPDX-License-Identifier: GPL-2.0-only

import logging
import os
import re
import shutil


def validate_keyword_conf_file(file_path: str) -> tuple[bool, str]:
  """
  Validate whether input file in keyword.conf format or not
  :param file_path: File path to validate
  :return: tuple (bool, str) - True if file in correct format,
  False otherwise, and a message.
  """
  keyword_search_pattern = re.compile(r'keyword=')
  keyword_format_pattern = re.compile(r'__\w+__')

  try:
    with open(file_path, 'r', encoding='utf-8') as file:
      lines = file.readlines()
    if not lines:
      return False, "File is empty"

    non_commented_lines = []
    for line in lines:
      stripped_line = line.strip()
      # Consider lines that are not empty and do not start with '#' as
      # non-commented
      if stripped_line and not stripped_line.startswith('#'):
        non_commented_lines.append(stripped_line)

    if not non_commented_lines:
      return False, "File has no keyword to search for"

    keyword_found = False
    for line in non_commented_lines:
      if not keyword_search_pattern.search(line):
        continue
      keyword_found = True
      matches = re.findall(r'__.*?__', line)
      for match in matches:
        if not keyword_format_pattern.fullmatch(match):
          return False, f"Invalid '__keyword__' format in line: {line}"

    if not keyword_found:
      return False, "File must contain at least one 'keyword=' line"

    return True, "Valid keyword.conf file"

  except FileNotFoundError:
    return False, "File not found"
  except UnicodeDecodeError:
    return False, "Error decoding file. Please ensure it's UTF-8 compatible."
  except IOError as e:
    return False, f"An I/O error occurred: {e}"
  except Exception as e:
    return False, str(e)


def copy_keyword_file_to_destination(
    source_path: str, destination_path: str
) -> None:
  """
  Make destination path and copy keyword file to destination
  :param source_path: Source file path
  :param destination_path: Destination file path
  :return: None
  """
  try:
    destination_dir = os.path.dirname(destination_path)
    if destination_dir:
      os.makedirs(destination_dir, exist_ok=True)

    shutil.copyfile(source_path, destination_path)
    logging.info(f"Keyword configuration file copied to {destination_path}")

  except FileNotFoundError:
    logging.error(f"Source file not found at {source_path}")
  except (OSError, shutil.Error) as e:
    logging.error(f"Unable to copy the file to {destination_path}: {e}")
  except Exception as e:
    logging.error(f"An unexpected error occurred while copying file: {e}")
