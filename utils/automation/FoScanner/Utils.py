#!/usr/bin/env python3

# SPDX-FileCopyrightText: © 2024 Rajul Jha <rajuljha49@gmail.com>

# SPDX-License-Identifier: GPL-2.0-only

import os
import re
import shutil

def validate_keyword_conf_file(file_path:str):
    """
    Validate whether input file in keyword.conf format or not
    :param: file_path : str File path to validate
    :return: bool True if file in correct format. Else False
    :return: str Validation message(True)/Error message(False)
    """
    try:
      with open(file_path, 'r') as file:
        lines = file.readlines()
      if not lines:
        return False, "File is empty"
      commented_lines = []
      non_commented_lines = []
      for line in lines:
        stripped_line = line.strip()
        if stripped_line.startswith('#') or not stripped_line:
          commented_lines.append(stripped_line)
        else:
          non_commented_lines.append(stripped_line)

      if not non_commented_lines:
        return False, "File has no keyword to search for"

      keyword_found = False
      for line in non_commented_lines:
        if not re.search(r'keyword=',line):
          continue
        keyword_found = True
        matches = re.findall(r'__.*?__', line)
        for match in matches:
          if not re.match(r'__\w+__', match):
            return False, f"Invalid '__keyword__' format in line: {line}"

      if not keyword_found:
        return False, "File must contain at least one 'keyword=' line"
    
      return True, "Valid keyword.conf file"
    
    except FileNotFoundError:
      return False, "File not found"
    except Exception as e:
      return False, str(e)

def copy_keyword_file_to_destination(source_path:str, destination_path:str) -> None:
    """
    Make destination path and copy keyword file to destination
    :param: source_path:str Source file path 
    :param: destination_path:str Destination file path
    :return: None 
    """
    try:
      os.makedirs(os.path.dirname(destination_path),exist_ok=True)
      shutil.copyfile(source_path,destination_path)
      print(f"Keyword configuration file copied to {destination_path}")

    except Exception as e:
      print(f"Unable to copy the file to {destination_path}: {e}")
