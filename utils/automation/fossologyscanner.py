#!/usr/bin/env python3

# SPDX-FileCopyrightText: © 2020,2023 Siemens AG
# SPDX-FileCopyrightText: © anupam.ghosh@siemens.com
# SPDX-FileCopyrightText: © mishra.gaurav@siemens.com

# SPDX-License-Identifier: GPL-2.0-only

import argparse
import json
import os
import sys
import textwrap
from typing import List, Union, IO

from FoScanner.ApiConfig import (ApiConfig, Runner)
from FoScanner.CliOptions import (CliOptions, ReportFormat)
from FoScanner.RepoSetup import RepoSetup
from FoScanner.Scanners import (Scanners, ScanResult)
from FoScanner.SpdxReport import SpdxReport
from FoScanner.FormatResults import FormatResult


def get_api_config() -> ApiConfig:
  """
  Set the API configuration based on CI the job is running on

  :return: ApiConfig object
  """
  api_config = ApiConfig()
  if 'GITLAB_CI' in os.environ:
    api_config.running_on = Runner.GITLAB
    api_config.api_url = os.environ['CI_API_V4_URL'] if 'CI_API_V4_URL' in \
                                                        os.environ else ''
    api_config.project_id = os.environ['CI_PROJECT_ID'] if 'CI_PROJECT_ID' in \
                                                           os.environ else ''
    api_config.mr_iid = os.environ['CI_MERGE_REQUEST_IID'] if \
      'CI_MERGE_REQUEST_IID' in os.environ else ''
    api_config.api_token = os.environ['API_TOKEN'] if 'API_TOKEN' in \
                                                      os.environ else ''
    api_config.project_name = os.environ['CI_PROJECT_NAME'] if \
      'CI_PROJECT_NAME' in os.environ else ''
    api_config.project_desc = os.environ['CI_PROJECT_DESCRIPTION'].strip()
    if api_config.project_desc == "":
      api_config.project_desc = None
    api_config.project_orig = os.environ['CI_PROJECT_NAMESPACE']
    api_config.project_url = os.environ['CI_PROJECT_URL']
  elif 'TRAVIS' in os.environ and os.environ['TRAVIS'] == 'true':
    api_config.running_on = Runner.TRAVIS
    api_config.travis_repo_slug = os.environ['TRAVIS_REPO_SLUG']
    api_config.travis_pull_request = os.environ['TRAVIS_PULL_REQUEST']
    api_config.project_name = os.environ['TRAVIS_REPO_SLUG'].split("/")[-1]
    api_config.project_orig = "/".join(os.environ['TRAVIS_REPO_SLUG'].
                                       split("/")[:-2])
    api_config.project_url = "https://github.com/" + \
                             os.environ['TRAVIS_REPO_SLUG']
  elif 'GITHUB_ACTIONS' in os.environ and \
      os.environ['GITHUB_ACTIONS'] == 'true':
    api_config.running_on = Runner.GITHUB
    api_config.api_url = os.environ['GITHUB_API'] if 'GITHUB_API' in \
                                        os.environ else 'https://api.github.com'
    api_config.api_token = os.environ['GITHUB_TOKEN']
    api_config.github_repo_slug = os.environ['GITHUB_REPOSITORY']
    api_config.github_pull_request = os.environ['GITHUB_PULL_REQUEST']
    api_config.project_name = os.environ['GITHUB_REPOSITORY'].split("/")[-1]
    api_config.project_orig = os.environ['GITHUB_REPO_OWNER']
    api_config.project_url = os.environ['GITHUB_REPO_URL']
  return api_config


def get_allow_list() -> dict:
  """
  Decode json from `allowlist.json`

  :return: allowlist dictionary
  """
  if os.path.exists('whitelist.json'):
    file_name = 'whitelist.json'
  else:
    file_name = 'allowlist.json'
  with open(file_name) as f:
    data = json.load(f)
  return data


def print_results(name: str, failed_results: List[ScanResult],scan_results_with_line_number:List[dict],
                  result_file: IO):
  """
  Print the formatted scanner results

  :param name: Name of the scanner
  :param failed_results: formatted scanner results to be printed
  :param: scan_results_with_line_number : List[dict] List of words mapped to their line numbers
  :param result_file: File to write results to
  """
  for files in failed_results:
    print(f"File: {files.file}")
    result_file.write(f"File: {files.file}\n")
    plural = ""
    if len(files.result) > 1:
      plural = "s"
    print(f"{name}{plural}:")
    result_file.write(f"{name}{plural}:\n")
    for result in files.result:
      for item in scan_results_with_line_number:
        for scanned_word, lines in item.items():
          if len(lines) > 1 :
            plural = "s"
          else:
            plural = ""
          if result == scanned_word:
            lines_str = ", ".join(lines)
            result = f"{scanned_word} at line{plural} {lines_str}\n"
      print("\t" + result)
      result_file.write("\t" + result + "\n")


def print_log_message(filename: str,
                      failed_list: Union[bool, List[ScanResult]],
                      check_value: bool, failure_text: str,
                      acceptance_text: str, scan_type: str,
                      return_val: int, scan_results_with_line_number:List[dict] ) -> int:
  """
  Common helper function to print scan results.

  :param filename: File where results are to be stored.
  :param failed_list: Failed scan results.
  :param check_value: Boolean value which failed_list should have.
  :param failure_text: Message to print in case of failures.
  :param acceptance_text: Message to print in case of no failures.
  :param scan_type: Type of scan to print.
  :param return_val: Return value for program
  :param: scan_results_with_line_number : List[dict] List of words mapped to their line numbers
  :return: New return value
  """
  report_file = open(filename, 'w')
  if (isinstance(failed_list, bool) and failed_list is not check_value) or \
      (isinstance(failed_list, list) and len(failed_list) != 0):
    print(f"\u2718 {failure_text}:")
    report_file.write(f"{failure_text}:\n")
    print_results(scan_type, failed_list, scan_results_with_line_number,report_file)
    if scan_type == "License":
      return_val = return_val | 2
    elif scan_type == "Copyright":
      return_val = return_val | 4
    elif scan_type == "Keyword":
      return_val = return_val | 8
  else:
    print(f"\u2714 {acceptance_text}")
    report_file.write(f"{acceptance_text}\n")
  print()
  report_file.close()
  return return_val

def format_keyword_results_with_line_numbers(scanner:Scanners,format_results:FormatResult):
  """
  Format the keyword results with line numbers

  :param: scanner : Scanner Scanner object
  :param: format_results : FormatResult FormatResult object
  :return: list of dicts with key as word and value as list of line numbers of the words
  """
  keyword_results = scanner.get_keyword_whole()
  if keyword_results is False:
    return []
  formatted_list_of_keyword_line_numbers = list()
  for keyword_result in keyword_results:
    list_of_scan_results = list(keyword_result.result)
    words_to_search = [list_of_scan_results[i] for i in range(0,len(list_of_scan_results))]
    words_with_line_numbers = format_results.find_word_line_numbers(keyword_result.path,words_to_search)
    formatted_list_of_keyword_line_numbers.append(words_with_line_numbers)
  return formatted_list_of_keyword_line_numbers

def format_copyright_results_with_line_numbers(scanner:Scanners,format_results:FormatResult):
  """
  Format the copyright results with line numbers

  :param: scanner : Scanner Scanner object
  :param: format_results : FormatResult FormatResult object
  :return: list of dicts with key as word and value as list of line numbers of the words
  """
  copyright_results = scanner.get_copyright_whole()
  if copyright_results is False:
    return []
  formatted_list_of_copyright_line_numbers = list()
  for copyright_result in copyright_results:
    list_of_scan_results = list(copyright_result.result)
    words_to_search = [list_of_scan_results[i] for i in range(0,len(list_of_scan_results))]
    words_with_line_numbers = format_results.find_word_line_numbers(copyright_result.path,words_to_search)
    formatted_list_of_copyright_line_numbers.append(words_with_line_numbers)
  return formatted_list_of_copyright_line_numbers

def format_license_results_with_line_numbers(scanner:Scanners,format_results:FormatResult):
  """
  Format the licenses results with line numbers

  :param: scanner : Scanner Scanner object
  :param: format_results : FormatResult FormatResult object
  :return: list of dicts with key as word and value as list of line numbers of the words
  """
  license_results = scanner.results_are_allow_listed()
  if license_results is False:
    return []
  formatted_list_of_license_line_numbers = list()
  for license_result in license_results:
    list_of_scan_results = list(license_result.result)
    words_to_search = [list_of_scan_results[i] for i in range(0,len(list_of_scan_results))]
    words_with_line_numbers = format_results.find_word_line_numbers_without_bytes(license_result.path,words_to_search)
    formatted_list_of_license_line_numbers.append(words_with_line_numbers)
  return formatted_list_of_license_line_numbers

def text_report(cli_options: CliOptions, result_dir: str, return_val: int,
                scanner: Scanners, format_results = FormatResult) -> int:
  """
  Run scanners and print results in text format.

  :param cli_options: CLI options
  :param result_dir: Result directory location
  :param return_val: Return value of program
  :param scanner: Scanner object
  :param: format_results : FormatResult FormatResult object
  :return: Program's return value
  """
  if cli_options.nomos or cli_options.ojo:
    failed_licenses = scanner.results_are_allow_listed()
    scan_results_with_line_number = []
    print_log_message(f"{result_dir}/licenses.txt", failed_licenses, True,
                      "Following licenses found which are not allow listed",
                      "No license violation found", "License", return_val, scan_results_with_line_number)
  if cli_options.copyright:
    copyright_results = scanner.get_copyright_list()
    scan_results_with_line_number = format_copyright_results_with_line_numbers(scanner=scanner, format_results=format_results)
    print_log_message(f"{result_dir}/copyrights.txt", copyright_results, False,
                      "Following copyrights found",
                      "No copyright violation found", "Copyright", return_val, scan_results_with_line_number)
  if cli_options.keyword:
    keyword_results = scanner.get_keyword_list()
    scan_results_with_line_number = format_keyword_results_with_line_numbers(scanner=scanner, format_results=format_results)
    print_log_message(f"{result_dir}/keywords.txt", keyword_results, False,
                      "Following keywords found",
                      "No keyword violation found", "Keyword", return_val, scan_results_with_line_number)
  return return_val


def bom_report(cli_options: CliOptions, result_dir: str, return_val: int,
               scanner: Scanners, api_config: ApiConfig, format_results: FormatResult) -> int:
  """
  Run scanners and print results as an SBOM.

  :param cli_options: CLI options
  :param result_dir: Result directory location
  :param return_val: Return value
  :param scanner: Scanner object
  :param api_config: API config options
  :param: format_results : FormatResult FormatResult object
  :return: Program's return value
  """
  report_obj = SpdxReport(cli_options, api_config)
  if cli_options.nomos or cli_options.ojo:
    scan_results = scanner.get_scanner_results()
    report_obj.add_license_results(scan_results)
    scan_results_with_line_number = []
    failed_licenses = scanner.get_non_allow_listed_results(scan_results)
    return_val = print_log_message(f"{result_dir}/licenses.txt",
        failed_licenses, True, "Following licenses found which are not allow "
                               "listed", "No license violation found",
        "License", return_val, scan_results_with_line_number)
  if cli_options.copyright:
    copyright_results = scanner.get_copyright_list(all_results=True)
    if copyright_results is False:
      copyright_results = []
    report_obj.add_copyright_results(copyright_results)
    failed_copyrights = scanner.get_non_allow_listed_copyrights(
      copyright_results)
    scan_results_with_line_number = format_copyright_results_with_line_numbers(scanner=scanner, format_results=format_results)
    return_val = print_log_message(f"{result_dir}/copyrights.txt",
        failed_copyrights, False, "Following copyrights found",
        "No copyright violation found", "Copyright", return_val,scan_results_with_line_number)
  if cli_options.keyword:
    keyword_results = scanner.get_keyword_list()
    scan_results_with_line_number = format_keyword_results_with_line_numbers(scanner=scanner, format_results=format_results)
    return_val = print_log_message(f"{result_dir}/keywords.txt",
        keyword_results, False, "Following keywords found",
        "No keyword violation found", "Keyword", return_val, scan_results_with_line_number)
  report_obj.finalize_document()
  report_name = f"{result_dir}/sbom_"
  if cli_options.report_format == ReportFormat.SPDX_JSON:
    report_name += "spdx.json"
  elif cli_options.report_format == ReportFormat.SPDX_RDF:
    report_name += "spdx.rdf"
  elif cli_options.report_format == ReportFormat.SPDX_TAG_VALUE:
    report_name += "spdx.spdx"
  elif cli_options.report_format == ReportFormat.SPDX_YAML:
    report_name += "spdx.yaml"
  report_obj.write_report(report_name)
  print(f"\u2714 Saved SBOM as {report_name}")
  return return_val


def main(parsed_args):
  """
  Main

  :param parsed_args:
  :return: 0 for success, error code on failure.
  """
  api_config = get_api_config()
  cli_options = CliOptions()
  cli_options.update_args(parsed_args)

  try:
    cli_options.allowlist = get_allow_list()
  except FileNotFoundError:
    print("Unable to find allowlist.json in current dir\n"
          "Continuing without it.", file=sys.stderr)

  repo_setup = RepoSetup(cli_options, api_config)
  if cli_options.repo is False:
    cli_options.diff_dir = repo_setup.get_diff_dir()

  scanner = Scanners(cli_options)
  return_val = 0

  # Populate tmp dir in unified diff format
  format_results = FormatResult(cli_options)
  format_results.process_files(scanner.cli_options.diff_dir)
     
  # Create result dir
  result_dir = "results"
  os.makedirs(name=result_dir, exist_ok=True)

  if cli_options.report_format == ReportFormat.TEXT:
    return_val = text_report(cli_options, result_dir, return_val, scanner,format_results)
  else:
    return_val = bom_report(cli_options, result_dir, return_val, scanner,
                            api_config,format_results)                      
  return return_val


if __name__ == "__main__":
  parser = argparse.ArgumentParser(
    description=textwrap.dedent("""fossology scanner designed for CI""")
  )
  parser.add_argument(
    "operation", type=str, help="Operations to run.", nargs='*',
    choices=["nomos", "copyright", "keyword", "ojo", "repo"]
  )
  parser.add_argument(
    "--report", type=str, help="Type of report to generate. Default 'TEXT'.",
    choices=[member.name for member in ReportFormat], default=ReportFormat.TEXT.name
  )
  args = parser.parse_args()
  sys.exit(main(args))

