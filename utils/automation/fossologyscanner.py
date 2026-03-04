#!/usr/bin/env python3

# SPDX-FileCopyrightText: © 2020,2023,2025 Siemens AG
# SPDX-FileCopyrightText: © anupam.ghosh@siemens.com
# SPDX-FileCopyrightText: © mishra.gaurav@siemens.com

# SPDX-License-Identifier: GPL-2.0-only

import argparse
import json
import logging
import os
import sys
import textwrap
from typing import IO

# Configure logging
logging.basicConfig(level=logging.INFO, format='%(levelname)s: %(message)s')

from FoScanner.ApiConfig import (ApiConfig, Runner)
from FoScanner.CliOptions import (CliOptions, ReportFormat)
from FoScanner.FormatResults import FormatResult
from FoScanner.Packages import Packages
from FoScanner.RepoSetup import RepoSetup
from FoScanner.Scanners import (Scanners, ScanResult)
from FoScanner.SpdxReport import SpdxReport
from FoScanner.Utils import (
  validate_keyword_conf_file, copy_keyword_file_to_destination
)
from ScanDeps.Downloader import Downloader
from ScanDeps.Parsers import Parser, PythonParser, NPMParser


def get_api_config() -> ApiConfig:
  """
  Set the API configuration based on CI the job is running on

  :return: ApiConfig object
  """
  api_config = ApiConfig()
  if 'GITLAB_CI' in os.environ:
    api_config.running_on = Runner.GITLAB
    api_config.api_url = os.environ.get('CI_API_V4_URL', '')
    api_config.project_id = os.environ.get('CI_PROJECT_ID', '')
    api_config.mr_iid = os.environ.get('CI_MERGE_REQUEST_IID', '')
    api_config.api_token = os.environ.get('API_TOKEN', '')
    api_config.project_name = os.environ.get('CI_PROJECT_NAME', '')
    api_config.project_desc = os.environ.get('CI_PROJECT_DESCRIPTION', '').strip()
    if not api_config.project_desc:
      api_config.project_desc = None
    api_config.project_orig = os.environ.get('CI_PROJECT_NAMESPACE', '')
    api_config.project_url = os.environ.get('CI_PROJECT_URL', '')
  elif os.environ.get('TRAVIS') == 'true':
    api_config.running_on = Runner.TRAVIS
    api_config.travis_repo_slug = os.environ.get('TRAVIS_REPO_SLUG', '')
    api_config.travis_pull_request = os.environ.get('TRAVIS_PULL_REQUEST', '')
    if api_config.travis_repo_slug:
      api_config.project_name = api_config.travis_repo_slug.split("/")[-1]
      api_config.project_orig = "/".join(api_config.travis_repo_slug.split("/")[:-2])
      api_config.project_url = f"https://github.com/{api_config.travis_repo_slug}"
  elif os.environ.get('GITHUB_ACTIONS') == 'true':
    api_config.running_on = Runner.GITHUB
    api_config.api_url = os.environ.get('GITHUB_API', 'https://api.github.com')
    api_config.api_token = os.environ.get('GITHUB_TOKEN', '')
    api_config.github_repo_slug = os.environ.get('GITHUB_REPOSITORY', '')
    api_config.github_pull_request = os.environ.get('GITHUB_PULL_REQUEST', '')
    if api_config.github_repo_slug:
      api_config.project_name = api_config.github_repo_slug.split("/")[-1]
      api_config.project_orig = os.environ.get('GITHUB_REPO_OWNER', '')
      api_config.project_url = os.environ.get('GITHUB_REPO_URL', '')
  return api_config


def get_allow_list(path: str = '') -> dict:
  """
  Decode json from `allowlist.json`

  :param path: path to allowlist file. Default=''
  :return: allowlist dictionary
  """
  file_name = 'allowlist.json'
  if not path:
    if os.path.exists('whitelist.json'):
      file_name = 'whitelist.json'
      logging.warning(
        "Name 'whitelist.json' is deprecated. "
        "Please use 'allowlist.json' instead."
      )
    logging.info(f"Reading {file_name} file...")
  else:
    file_name = path
    logging.info(f"Reading allowlist.json file from the path: '{file_name}'")
  with open(file_name, 'r', encoding='utf-8') as f:
    data = json.load(f)
  return data


def print_results(
    name: str, failed_results: list[ScanResult],
    scan_results_with_line_number: list[dict[str, set[str]]],
    result_file: IO
):
  """
  Print the formatted scanner results

  :param name: Name of the scanner
  :param failed_results: formatted scanner results to be printed
  :param scan_results_with_line_number: List of words mapped to their line
  numbers
  :param result_file: File to write results to
  """
  line_number_map: dict[str, set[str]] = {}
  for item in scan_results_with_line_number:
    if item:
      line_number_map.update(item)

  for files in failed_results:
    logging.info(f"File: {files.file}")
    result_file.write(f"File: {files.file}\n")

    plural_name = "s" if len(files.result) > 1 else ""
    logging.info(f"{name}{plural_name}:")
    result_file.write(f"{name}{plural_name}:\n")

    for result_item in files.result:
      if isinstance(result_item, dict):
        scanned_word = result_item.get('content') or result_item.get('license')
      else:
        scanned_word = str(result_item)

      if scanned_word in line_number_map:
        lines = line_number_map[scanned_word]
        plural_lines = "s" if len(lines) > 1 else ""
        lines_str = ", ".join(lines)
        formatted_output = f"{scanned_word} at line{plural_lines} {lines_str}"
      else:
        formatted_output = scanned_word

      logging.info(f"\t{formatted_output}")
      result_file.write(f"\t{formatted_output}\n")


def print_log_message(
    filename: str,
    failed_list: bool | list[ScanResult],
    check_value: bool, failure_text: str,
    acceptance_text: str, scan_type: str,
    return_val: int, scan_results_with_line_number: list[dict[str, set[str]]]
) -> int:
  """
  Common helper function to print scan results.

  :param filename: File where results are to be stored.
  :param failed_list: Failed scan results.
  :param check_value: Boolean value which failed_list should have.
  :param failure_text: Message to print in case of failures.
  :param acceptance_text: Message to print in case of no failures.
  :param scan_type: Type of scan to print.
  :param return_val: Return value for program
  :param scan_results_with_line_number: List of words mapped to their line
  numbers
  :return: New return value
  """
  with open(filename, 'w', encoding='utf-8') as report_file:
    has_failures = False
    if isinstance(failed_list, bool):
      has_failures = (failed_list != check_value)
    elif isinstance(failed_list, list):
      has_failures = (len(failed_list) > 0)

    if has_failures:
      logging.error(f"\u2718 {failure_text}:") # Cross mark
      report_file.write(f"{failure_text}:\n")
      print_results(
        scan_type, failed_list, scan_results_with_line_number, report_file
      )
      if scan_type == "License":
        return_val |= 2
      elif scan_type == "Copyright":
        return_val |= 4
      elif scan_type == "Keyword":
        return_val |= 8
    else:
      logging.info(f"\u2714 {acceptance_text}") # Check mark
      report_file.write(f"{acceptance_text}\n")

  logging.info("")
  return return_val


def _format_results_with_line_numbers(
    scanner: Scanners, format_results: FormatResult, result_type: str, key: str
) -> list[dict[str, set[str]]]:
  """
  Generic function to format scanner results with line numbers.

  :param scanner: Scanner object
  :param format_results: FormatResult object
  :param result_type: Type of results to retrieve ('keyword', 'copyright',
  'license')
  :param key: The key within the scan result dictionary to use for the word (
  e.g., 'content' for copyrights and 'licenses' for license scans)
  :return: List of dicts with key as word and value as list of line numbers of the words
  """
  if result_type == 'keyword':
    scan_results = scanner.get_keyword_results()
  elif result_type == 'copyright':
    scan_results = scanner.get_copyright_results()
  elif result_type == 'license':
    # license_results can be True/None or a list, ensure it's a list
    license_res = scanner.results_are_allow_listed(whole=True)
    scan_results = license_res if isinstance(license_res, list) else []
  else:
    return []

  formatted_list_of_line_numbers = []
  for scan_result_item in scan_results:
    list_of_scan_results = (
      list(scan_result_item.result)
      if scan_result_item and scan_result_item.result
      else []
    )

    words_with_line_numbers = format_results.find_word_line_numbers(
      scan_result_item.path, list_of_scan_results, key=key
    )
    if words_with_line_numbers:
      formatted_list_of_line_numbers.append(words_with_line_numbers)
  return formatted_list_of_line_numbers


def text_report(
    cli_options: CliOptions, result_dir: str, return_val: int,
    scanner: Scanners, format_results: FormatResult
) -> int:
  """
  Run scanners and print results in text format.

  :param cli_options: CLI options
  :param result_dir: Result directory location
  :param return_val: Return value of program
  :param scanner: Scanner object
  :param format_results: FormatResult object
  :return: Program's return value
  """
  return perform_scans(
    cli_options, format_results, result_dir, return_val, scanner
  )


def perform_scans(cli_options, format_results, result_dir, return_val, scanner):
  if cli_options.nomos or cli_options.ojo:
    logging.info("Scanning for licenses...")
    scanner.set_scanner_results(whole=True)
    scan_results_with_line_number = _format_results_with_line_numbers(
      scanner=scanner, format_results=format_results,
      result_type='license', key='license'
    )
    failed_licenses = scanner.results_are_allow_listed()
    return_val = print_log_message(
      f"{result_dir}/licenses.txt", failed_licenses, True,
      "Following licenses found which are not allow listed",
      "No license violation found", "License", return_val,
      scan_results_with_line_number
    )
  if cli_options.copyright:
    logging.info("Scanning for copyrights...")
    scanner.set_copyright_list(all_results=True, whole=True)
    failed_copyrights = scanner.get_non_allow_listed_copyrights()
    scan_results_with_line_number = _format_results_with_line_numbers(
      scanner=scanner, format_results=format_results,
      result_type='copyright', key='content'
    )
    return_val = print_log_message(
      f"{result_dir}/copyrights.txt",
      failed_copyrights, False, "Following copyrights found",
      "No copyright violation found", "Copyright", return_val,
      scan_results_with_line_number
    )
  if cli_options.keyword:
    logging.info("Scanning keywords...")
    scanner.set_keyword_list(whole=True)
    scan_results_with_line_number = _format_results_with_line_numbers(
      scanner=scanner, format_results=format_results,
      result_type='keyword', key='content'
    )
    keyword_results = [
      r.result.get('content') for r in scanner.get_keyword_results()
      if r.result and r.result.get('content')
    ]

    return_val = print_log_message(
      f"{result_dir}/keywords.txt",
      keyword_results, False, "Following keywords found",
      "No keyword violation found", "Keyword", return_val,
      scan_results_with_line_number
    )
  return return_val


def bom_report(
    cli_options: CliOptions, result_dir: str, return_val: int,
    scanner: Scanners, format_results: FormatResult
) -> int:
  """
  Run scanners and print results as an SBOM.

  :param cli_options: CLI options
  :param result_dir: Result directory location
  :param return_val: Return value
  :param scanner: Scanner object
  :param format_results: FormatResult object
  :return: Program's return value
  """
  report_obj = SpdxReport(cli_options, scanner)
  return_val = perform_scans(
    cli_options, format_results, result_dir, return_val, scanner
  )
  logging.info("Finalizing reports...")
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

  logging.info(f"Validating and writing report to file {report_name}...")
  try:
    report_obj.write_report(report_name)
    logging.info(f"\u2714 Saved SBOM as {report_name}")
  except RuntimeError as e:
    logging.error(f"Failed to write SBOM report: {e}")
    return_val |= 1

  return return_val


def get_scan_packages(api_config: ApiConfig) -> Packages:
  scan_packages = Packages()
  scan_packages.parent_package = {
    'name': api_config.project_name,
    'description': api_config.project_desc,
    'author': api_config.project_orig,
    'url': api_config.project_url
  }

  return scan_packages


def main(parsed_args):
  """
  Main

  :param parsed_args:
  :return: 0 for success, error code on failure.
  """
  api_config = get_api_config()
  cli_options = CliOptions()
  cli_options.update_args(parsed_args)
  save_dir = 'pkg_downloads'
  scan_packages = get_scan_packages(api_config)

  try:
    if cli_options.allowlist_path:
      cli_options.allowlist = get_allow_list(path=cli_options.allowlist_path)
    else:
      cli_options.allowlist = get_allow_list()
  except FileNotFoundError:
    logging.warning("Unable to find allowlist.json in current dir. "
                    "Continuing without it.")
  except json.JSONDecodeError:
    logging.error("Error parsing allowlist.json. Please ensure it's valid JSON."
                  " Continuing without it.")
  except Exception as e:
    logging.error(f"An unexpected error occurred while reading allowlist: {e}."
                  " Continuing without it.")

  if cli_options.keyword and cli_options.keyword_conf_file_path:
    keyword_conf_file_path = cli_options.keyword_conf_file_path
    destination_path = '/usr/local/share/fossology/keyword/agent/keyword.conf'
    is_valid, message = validate_keyword_conf_file(keyword_conf_file_path)
    if is_valid:
      logging.info(f"Validation of keyword file successful: {message}")
      copy_keyword_file_to_destination(keyword_conf_file_path, destination_path)
    else:
      logging.error(f"Could not validate keyword file: {message}")

  if (cli_options.scan_only_deps or cli_options.repo) and cli_options.sbom_path:
    sbom_file_path = cli_options.sbom_path
    cli_options.parser = Parser(sbom_file_path)
    cli_options.parser.classify_components(save_dir)

    if cli_options.parser.python_components:
      python_parser = PythonParser()
      python_parser.parse_components(cli_options.parser)

    if cli_options.parser.npm_components:
      npm_parser = NPMParser()
      npm_parser.parse_components(cli_options.parser)

    if cli_options.parser.unsupported_components:
      for comp in cli_options.parser.unsupported_components:
        logging.warning(
          f"The purl {comp.get('purl', 'N/A')} is not supported. "
          "Package will not be downloaded."
        )

    scan_packages.dependencies = cli_options.parser.parsed_components

    try:
      downloader = Downloader()
      downloader.download_concurrently(cli_options.parser)
    except Exception as e:
      logging.error(
        f"Something went wrong while downloading the dependencies: {e}")

  if cli_options.scan_dir:
    cli_options.diff_dir = cli_options.dir_path
  elif not cli_options.repo and not cli_options.scan_only_deps:
    repo_setup = RepoSetup(cli_options, api_config)
    cli_options.diff_dir = repo_setup.get_diff_dir()

  scanner = Scanners(cli_options, scan_packages)
  return_val = 0

  # Populate tmp dir in unified diff format
  format_results = FormatResult(cli_options)
  format_results.process_files(scanner.cli_options.diff_dir)

  # Create result dir
  result_dir = "results"
  os.makedirs(name=result_dir, exist_ok=True)

  logging.info("Preparing scan reports...")
  if cli_options.report_format == ReportFormat.TEXT:
    return_val = text_report(
      cli_options, result_dir, return_val, scanner,
      format_results
    )
  else:
    return_val = bom_report(
      cli_options, result_dir, return_val, scanner,
      format_results
    )
  return return_val


if __name__ == "__main__":
  parser = argparse.ArgumentParser(
    description=textwrap.dedent("""fossology scanner designed for CI""")
  )
  parser.add_argument(
    "operation", type=str, help="Operations to run.", nargs='*',
    choices=[
      "nomos", "copyright", "keyword", "ojo", "repo", "differential",
      "scan-only-deps", "scan-dir"
    ]
  )
  parser.add_argument(
    "--tags", type=str, nargs=2,
    help="Tags for differential scan. Required if 'differential' is specified."
  )
  parser.add_argument(
    "--report", type=str, help="Type of report to generate. Default 'TEXT'.",
    choices=[member.name for member in ReportFormat],
    default=ReportFormat.TEXT.name
  )
  parser.add_argument(
    '--keyword-conf', type=str, help='Path to the keyword configuration file. '
                                     'Use only when keyword argument is true'
  )
  parser.add_argument(
    '--dir-path', type=str, help='Path to directory for scanning.'
  )

  parser.add_argument(
    "--allowlist-path", type=str,
    help="Pass allowlist.json to allowlist dependencies."
  )
  parser.add_argument(
    "--sbom-path", type=str,
    help="Path to SBOM file for downloading dependencies."
  )

  args = parser.parse_args()
  sys.exit(main(args))
