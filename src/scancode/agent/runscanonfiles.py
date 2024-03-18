#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
Copyright (C) 2023  Sushant Kumar (sushantmishra02102002@gmail.com)

SPDX-License-Identifier: GPL-2.0-only
"""

import os
import json
import argparse

# Set SCANCODE_CACHE environment variable
script_directory = os.path.dirname(os.path.abspath(__file__))
os.environ["SCANCODE_CACHE"] = os.path.join(script_directory, '.cache')

from scancode import api

def update_license(licenses):
  """
  Extracts relevant information from the 'licenses' data.
  Parameters:
    licenses (dict): A dictionary containing license information.
  Returns:
    list: A list of dictionaries containing relevant license information.
  """
  updated_licenses = []
  keys_to_extract_from_licenses = ['spdx_license_key', 'score', 'name', 'text_url', 'start_line', 'matched_text']

  for key, value in licenses.items():
    if key == 'licenses':
      for license in value:
        updated_licenses.append({key: license[key] for key in keys_to_extract_from_licenses if key in license})

  return updated_licenses

def update_copyright(copyrights):
  """
  Extracts relevant information from the 'copyrights' data.
  Parameters:
    copyrights (dict): A dictionary containing copyright information.
  Returns:
    tuple: A tuple of two lists. The first list contains updated copyright information,
    and the second list contains updated holder information.
  """
  updated_copyrights = []
  updated_holders = []
  keys_to_extract_from_copyrights = ['copyright', 'start_line']
  keys_to_extract_from_holders = ['holder', 'start_line']
  key_mapping = {
    'start_line': 'start',
    'copyright': 'value',
    'holder': 'value'
  }

  for key, value in copyrights.items():
    if key == 'copyrights':
      for copyright in value:
        updated_copyrights.append({key_mapping.get(key, key): copyright[key] for key in keys_to_extract_from_copyrights if key in copyright})
    if key == 'holders':
      for holder in value:
        updated_holders.append({key_mapping.get(key, key): holder[key] for key in keys_to_extract_from_holders if key in holder})
  return updated_copyrights, updated_holders

def update_emails(emails):
  """
  Extracts relevant information from the 'emails' data.
  Parameters:
    emails (dict): A dictionary containing email information.
  Returns:
    list: A list of dictionaries containing relevant email information.
  """
  updated_emails = []
  keys_to_extract_from_emails = ['email', 'start_line']
  key_mapping = {
    'start_line': 'start',
    'email': 'value'
  }

  for key, value in emails.items():
    if key == 'emails':
      for email in value:
        updated_emails.append({key_mapping.get(key, key): email[key] for key in keys_to_extract_from_emails if key in email})

  return updated_emails

def update_urls(urls):
  """
  Extracts relevant information from the 'urls' data.
  Parameters:
    urls (dict): A dictionary containing url information.
  Returns:
    list: A list of dictionaries containing relevant url information.
  """
  updated_urls = []
  keys_to_extract_from_urls = ['url', 'start_line']
  key_mapping = {
    'start_line': 'start',
    'url': 'value'
  }

  for key, value in urls.items():
    if key == 'urls':
      for url in value:
        updated_urls.append({key_mapping.get(key, key): url[key] for key in keys_to_extract_from_urls if key in url})

  return updated_urls

def scan(line, scan_copyrights, scan_licenses, scan_emails, scan_urls, min_score):
  """
  Processes a single file and returns the results.
  Parameters:
    line (str): A line from the file containing the list of files to scan.
    scan_copyrights (bool):
    scan_licenses (bool):
    scan_emails (bool):
    scan_urls (bool):
  """
  result = {'file': line.strip()}
  result['licenses'] = []
  result['copyrights'] = []
  result['holders'] = []
  result['emails'] = []
  result['urls'] = []

  if scan_copyrights:
    copyrights = api.get_copyrights(result['file'])
    updated_copyrights, updated_holders = update_copyright(copyrights)
    result['copyrights'] = updated_copyrights
    result['holders'] = updated_holders

  if scan_licenses:
    licenses = api.get_licenses(result['file'], include_text=True, min_score=min_score)
    updated_licenses = update_license(licenses)
    result['licenses'] = updated_licenses

  if scan_emails:
    emails = api.get_emails(result['file'])
    updated_emails = update_emails(emails)
    result['emails'] = updated_emails

  if scan_urls:
    urls = api.get_urls(result['file'])
    updated_urls = update_urls(urls)
    result['urls'] = updated_urls

  return result

def process_files(file_location, outputFile, scan_copyrights, scan_licenses, scan_emails, scan_urls, min_score):
  """
  Processes the file containing the list of files to scan.
  Parameters:
    scan_copyrights (bool):
    scan_licenses (bool):
    scan_emails (bool):
    scan_urls (bool):
  """
  # Open the file containing the list of files to scan
  with open(file_location, "r") as locations:
    # Read and process each line
    with open(outputFile, "w") as json_file:
      json_file.write('[')
      first_iteration = True
      for line in locations:
        try:
          result = scan(line, scan_copyrights, scan_licenses, scan_emails, scan_urls, min_score)

          if not first_iteration:  # Check if it's not the first result
            json_file.write(',\n')  # Add a comma to separate elements in the JSON array
          else:
            first_iteration = False

          json.dump(result, json_file)

        except Exception as e:
          print(f"An error occurred for file '{line.strip()}': {e}")
          continue
      json_file.write('\n]')

if __name__ == "__main__":
  parser = argparse.ArgumentParser(description="Process a file specified by its location.")
  parser.add_argument("-c", "--scan-copyrights", action="store_true", help="Scan for copyrights")
  parser.add_argument("-l", "--scan-licenses", action="store_true", help="Scan for licenses")
  parser.add_argument("-e", "--scan-emails", action="store_true", help="Scan for emails")
  parser.add_argument("-u", "--scan-urls", action="store_true", help="Scan for urls")
  parser.add_argument("-m", "--min-score", dest="min_score", type=int, default=0, help="Minimum score for a license to be included in the results")
  parser.add_argument('file_location', type=str, help='Path to the file you want to process')
  parser.add_argument('outputFile', type=str, help='Path to the file you want save results to')

  args = parser.parse_args()
  scan_copyrights = args.scan_copyrights
  scan_licenses = args.scan_licenses
  scan_emails = args.scan_emails
  scan_urls = args.scan_urls
  min_score = args.min_score
  file_location = args.file_location
  outputFile = args.outputFile

  process_files(file_location, outputFile, scan_copyrights, scan_licenses, scan_emails, scan_urls, min_score)
