#!/usr/bin/env python3

# SPDX-FileCopyrightText: © 2023,2025 Siemens AG
# SPDX-FileContributor: Gaurav Mishra <mishra.gaurav@siemens.com>

# SPDX-License-Identifier: GPL-2.0-only

import fnmatch
import json
import multiprocessing
import os
from subprocess import Popen, PIPE
from typing import Any

from .CliOptions import CliOptions
from .Packages import Packages


class ScanResult:
  """
  Store scan results from agents.

  :ivar file: File location
  :ivar path: Actual location of file
  :ivar result: License list for file
  """
  file: str = None
  path: str = None
  result: set[str] = None

  def __init__(self, file: str, path: str, result: set[str]):
    self.file = file
    self.path = path
    self.result = result


class ScanResultList(ScanResult):
  """
  Store scan results from agents with result as a list of dictionaries.

  :ivar file: File location
  :ivar path: Actual location of file
  :ivar result: License list for file as a list of dictionaries
  """
  file: str = None
  path: str = None
  result: list[dict] = None

  def __init__(self, file: str, path: str, result: list[dict]):
    self.file = file
    self.path = path
    self.result = result


class Scanners:
  """
  Handle all the data from different scanners.

  :ivar nomos_path: path to nomos bin
  :ivar copyright_path: path to copyright bin
  :ivar keyword_path: path to keyword bin
  :ivar ojo_path: path to ojo bin
  :ivar cli_options: CliOptions object
  """
  nomos_path: str = '/bin/nomossa'
  copyright_path: str = '/bin/copyright'
  keyword_path: str = '/bin/keyword'
  ojo_path: str = '/bin/ojo'

  def __init__(self, cli_options: CliOptions, scan_packages: Packages):
    """
    Initialize the cli_options

    :param cli_options: CliOptions object to use
    :type cli_options: CliOptions
    :param scan_packages: ScanPackages for references
    :type scan_packages: Packages
    """
    self.cli_options: CliOptions = cli_options
    self.scan_packages: Packages = scan_packages
    self._allowlist_licenses_set = set(
      self.cli_options.allowlist.get('licenses', [])
    )

  def get_scan_packages(self) -> Packages:
    return self.scan_packages

  def is_excluded_path(self, path: str) -> bool:
    """
    Check if the path is allow listed

    The function used fnmatch to check if the path is in allow list or not.

    :param path: path to check
    :return: True if the path is in allow list, False otherwise
    """
    for pattern in self.cli_options.allowlist.get('exclude', []):
      if fnmatch.fnmatchcase(path, pattern):
        return True
    return False

  def __normalize_path(self, path: str, against: str) -> str:
    """
    Normalize the given path against the given directory.

    :param path: path to normalize
    :param against: directory to normalize against
    :return: Normalized path
    """
    if not against.endswith(os.sep):
      against += os.sep
    start_index_of_prefix = path.find(against)
    if start_index_of_prefix == -1:
      return path

    relative_path_start_index = start_index_of_prefix + len(against)
    return path[relative_path_start_index:]

  def _execute_scanner_command(
      self, scanner_path: str, dir_to_scan: str, extra_args: list[str] = None
  ) -> dict:
    """
    Helper to execute a scanner command and return its JSON output.
    """
    command = [scanner_path, "-J", "-d", dir_to_scan]
    if extra_args:
      command.extend(extra_args)

    try:
      # Use text=True for universal newlines and automatic decoding
      process = Popen(command, stdout=PIPE, text=True, encoding='UTF-8')
      stdout, stderr = process.communicate()

      if process.returncode != 0:
        msg = (f"Scanner {scanner_path} exited with error code "
               f"{process.returncode}. Stderr: {stderr}")
        print(msg)
        raise RuntimeError(msg)

      # Handle potential empty or malformed JSON output
      if not stdout.strip():
        return {}

      return json.loads(stdout.strip())
    except FileNotFoundError as e:
      print(f"Error: Scanner executable not found at {scanner_path}")
      raise e
    except json.JSONDecodeError as e:
      print(f"Error: Failed to decode JSON from scanner {scanner_path} output.")
      print(f"Raw output: {stdout}")
      raise e
    except Exception as e:
      print(f"An unexpected error occurred while running {scanner_path}: {e}")
      raise e

  def __get_nomos_result(self, dir_to_scan: str) -> dict:
    """
    Get the raw results from nomos scanner

    :return: raw json from nomos
    """
    extra_args = ["-S", "-l", "-n", str(multiprocessing.cpu_count() - 1)]
    return self._execute_scanner_command(
      self.nomos_path, dir_to_scan, extra_args
    )

  def __get_ojo_result(self, dir_to_scan: str) -> dict:
    """
    Get the raw results from ojo scanner

    :return: raw json from ojo
    """
    return self._execute_scanner_command(self.ojo_path, dir_to_scan)

  def __get_copyright_results(self, dir_to_scan: str) -> dict:
    """
    Get the raw results from copyright scanner

    :return: raw json from copyright
    """
    return self._execute_scanner_command(self.copyright_path, dir_to_scan)

  def __get_keyword_results(self, dir_to_scan: str) -> dict:
    """
    Get the raw results from keyword scanner

    :return: raw json from keyword
    """
    return self._execute_scanner_command(self.keyword_path, dir_to_scan)

  def _process_single_scanner_package(
      self, component: dict, is_parent: bool, scanner_func: callable,
      result_key: str, whole: bool = False, all_results: bool = False
  ) -> list[ScanResult] | list[ScanResultList]:
    """
    Generalized function to process results from a single scanner for a given
    component. Set `result_key` to 'results' for copyrights and 'licenses' for
    license scanning.
    """
    dir_to_scan = self.cli_options.diff_dir if is_parent else os.path.join(
      component['download_dir'], component['base_dir']
    )

    raw_results = scanner_func(dir_to_scan)
    processed_list: list[ScanResult] | list[ScanResultList] = []
    raw_results_list: list[
      dict[str, str | list[dict[str, str | int]] | None]] = []

    if isinstance(raw_results, dict):
      if 'results' in raw_results:
        raw_results_list = raw_results['results']
    elif isinstance(raw_results, list):
      raw_results_list = raw_results

    if not raw_results_list:
      return processed_list

    for result_entry in raw_results_list:
      # Skip if 'file' or 'results'/'licenses' key is missing or malformed
      if (
          'file' not in result_entry
          or result_key not in result_entry
          or result_entry.get(result_key) == "Unable to read file"
      ):
        continue

      file_path = self.__normalize_path(result_entry['file'], dir_to_scan)

      if self.cli_options.repo and not all_results and self.is_excluded_path(
          file_path
      ):
        continue

      current_findings: set[str] | list[dict[str, Any]] = set() if not whole \
        else []

      findings_list = result_entry.get(result_key, None)
      if findings_list is None:
        continue

      for finding in findings_list:
        if finding is None:
          continue

        if whole:
          # Need whole JSON for ScanResultList
          if (
              result_key == 'results'
              and 'type' in finding
              and finding['type'] == 'statement'
              and finding.get('content')
          ):
            current_findings.append(finding)
          elif (
              result_key == 'licenses'
              and finding.get('license') != "No_license_found"
          ):
            current_findings.append(finding)
        else:
          # Need set of string for ScanResult
          content = finding.get('content') or finding.get('license')
          content = content.strip()
          if (
            result_key == 'results'
            and 'type' in finding
            and finding['type'] != 'statement'
          ):
            continue

          if content and content != "No_license_found":
            current_findings.add(content)

      if (whole and current_findings) or (not whole and current_findings):
        if whole:
          processed_list.append(
            ScanResultList(file_path, result_entry['file'], current_findings)
          )
        else:
          processed_list.append(
            ScanResult(file_path, result_entry['file'], current_findings)
          )

    return processed_list

  def set_copyright_list(
      self, all_results: bool = False, whole: bool = False
  ) -> None:
    """
    Set the formatted results from copyright scanner for the components.
    """
    if not self.cli_options.scan_only_deps:
      self.scan_packages.parent_package[
        'COPYRIGHT_RESULT'] = self._process_single_scanner_package(
        component=self.scan_packages.parent_package, is_parent=True,
        scanner_func=self.__get_copyright_results, result_key='results',
        whole=whole, all_results=all_results
      )
    for purl in self.scan_packages.dependencies.keys():
      component = self.scan_packages.dependencies[purl]
      component['COPYRIGHT_RESULT'] = self._process_single_scanner_package(
        component=component, is_parent=False,
        scanner_func=self.__get_copyright_results, result_key='results',
        whole=whole, all_results=all_results
      )

  def set_keyword_list(self, whole: bool = False) -> None:
    """
    Get the formatted results from keyword scanner
    """
    if not self.cli_options.scan_only_deps:
      self.scan_packages.parent_package[
        'KEYWORD_RESULT'] = self._process_single_scanner_package(
        component=self.scan_packages.parent_package, is_parent=True,
        scanner_func=self.__get_keyword_results, result_key='results',
        whole=whole
      )
    for purl in self.scan_packages.dependencies.keys():
      component = self.scan_packages.dependencies[purl]
      component['KEYWORD_RESULT'] = self._process_single_scanner_package(
        component=component, is_parent=False,
        scanner_func=self.__get_keyword_results, result_key='results',
        whole=whole
      )

  def __set_license_nomos(self, whole: bool = False) -> None:
    """
    Update the packages with formatted results of nomos scanner
    """
    if not self.cli_options.scan_only_deps:
      self.scan_packages.parent_package[
        'NOMOS_RESULT'] = self._process_single_scanner_package(
        component=self.scan_packages.parent_package, is_parent=True,
        scanner_func=self.__get_nomos_result, result_key='licenses', whole=whole
      )
    for purl in self.scan_packages.dependencies.keys():
      component = self.scan_packages.dependencies[purl]
      component['NOMOS_RESULT'] = self._process_single_scanner_package(
        component=component, is_parent=False,
        scanner_func=self.__get_nomos_result, result_key='licenses', whole=whole
      )

  def __set_license_ojo(self, whole: bool = False) -> None:
    """
    Update the packages with formatted results of ojo scanner
    """
    if not self.cli_options.scan_only_deps:
      self.scan_packages.parent_package[
        'OJO_RESULT'] = self._process_single_scanner_package(
        component=self.scan_packages.parent_package, is_parent=True,
        scanner_func=self.__get_ojo_result, result_key='licenses', whole=whole
      )
    for purl in self.scan_packages.dependencies.keys():
      component = self.scan_packages.dependencies[purl]
      component['OJO_RESULT'] = self._process_single_scanner_package(
        component=component, is_parent=False,
        scanner_func=self.__get_ojo_result, result_key='licenses', whole=whole
      )

  def __merge_nomos_ojo(
      self, nomos_licenses: list[ScanResult], ojo_licenses: list[ScanResult]
  ) -> list[ScanResult]:
    """
    Merge the results from nomos and ojo based on file name
    """
    nomos_dict = {entry.file: entry for entry in nomos_licenses}

    for ojo_entry in ojo_licenses:
      if ojo_entry.file in nomos_dict:
        nomos_dict[ojo_entry.file].result.update(ojo_entry.result)
      else:
        # If an ojo entry doesn't have a corresponding nomos entry, add it
        nomos_licenses.append(ojo_entry)
    return nomos_licenses

  def get_non_allow_listed_results(
      self, scan_results: list[ScanResult] = None,
      scan_results_whole: list[ScanResultList] = None, whole: bool = False
  ) -> list[ScanResult] | list[ScanResultList]:
    """
    Get results where license check failed.
    """
    final_results = []
    if whole and scan_results_whole is not None:
      for row in scan_results_whole:
        if self.cli_options.repo and self.is_excluded_path(row.file):
          continue

        # Filter licenses that are NOT in the allowlist
        failed_licenses_list = [
          lic for lic in row.result if
          lic.get('license') not in self._allowlist_licenses_set
        ]
        if failed_licenses_list:
          final_results.append(
            ScanResultList(row.file, row.path, failed_licenses_list)
          )
    elif not whole and scan_results is not None:
      for row in scan_results:
        if self.cli_options.repo and self.is_excluded_path(row.file):
          continue

        # Filter licenses that are NOT in the allowlist
        failed_licenses = {
          lic for lic in row.result if
          lic not in self._allowlist_licenses_set
        }
        if failed_licenses:
          final_results.append(ScanResult(row.file, row.path, failed_licenses))
    return final_results

  def get_non_allow_listed_copyrights(self) -> list[ScanResult]:
    """
    Get copyrights from files which are not allow listed.
    """
    copyright_results = self.get_copyright_results()
    return [row for row in copyright_results if
      self.cli_options.repo and not self.is_excluded_path(row.file)]

  def get_copyright_results(self) -> list[ScanResultList]:
    """
    Get list of copyright scan results from the package list.
    """
    copyright_results = []
    copyright_results.extend(
      self.scan_packages.parent_package.get('COPYRIGHT_RESULT', [])
    )
    for dep in self.scan_packages.dependencies.values():
      copyright_results.extend(dep.get('COPYRIGHT_RESULT', []))
    return copyright_results

  def get_keyword_results(self) -> list[ScanResultList]:
    """
    Get list of keywords scan results from the package list.
    """
    keyword_results = []
    keyword_results.extend(
      self.scan_packages.parent_package.get('KEYWORD_RESULT', [])
    )
    for dep in self.scan_packages.dependencies.values():
      keyword_results.extend(dep.get('KEYWORD_RESULT', []))
    return keyword_results

  def get_license_results(self) -> list[ScanResultList]:
    """
    Get list of license scan results from the package list.
    """
    scanner_results = []
    scanner_results.extend(
      self.scan_packages.parent_package.get('SCANNER_RESULTS', [])
    )
    for dep in self.scan_packages.dependencies.values():
      scanner_results.extend(dep.get('SCANNER_RESULTS', []))
    return scanner_results

  def results_are_allow_listed(
      self, whole: bool = False
  ) -> list[ScanResult] | list[ScanResultList]:
    """
    Get the formatted list of license scanner findings

    The list contains the merged result of nomos/ojo scanner based on
    cli_options passed
    """
    scanner_results = self.get_license_results()

    failed_licenses = self.get_non_allow_listed_results(
      scan_results_whole=scanner_results, whole=True
    )

    if whole:
      return failed_licenses
    else:
      # Convert ScanResultList to ScanResult for non-whole output
      return [
        ScanResult(
          item.file, item.path,
          {res['license'] for res in item.result if 'license' in res}
        ) for item in failed_licenses
      ]

  def set_scanner_results(self, whole: bool = False) -> None:
    """
    Set the key `SCANNER_RESULTS` for all components in scan_packages using
    nomos and ojo scanners (whichever is selected).
    """
    if self.cli_options.nomos:
      self.__set_license_nomos(whole)
    if self.cli_options.ojo:
      self.__set_license_ojo(whole)

    if self.cli_options.nomos and self.cli_options.ojo:
      # Handle parent package separately
      if whole:
        self.scan_packages.parent_package[
          'SCANNER_RESULTS'] = self.scan_packages.parent_package.get(
          'NOMOS_RESULT', []
        ) + self.scan_packages.parent_package.get('OJO_RESULT', [])
      else:
        self.scan_packages.parent_package[
          'SCANNER_RESULTS'] = self.__merge_nomos_ojo(
          self.scan_packages.parent_package.get('NOMOS_RESULT', []),
          self.scan_packages.parent_package.get('OJO_RESULT', [])
        )
      for purl in self.scan_packages.dependencies.keys():
        component = self.scan_packages.dependencies[purl]
        if whole:
          # Concatenate lists for whole results
          component['SCANNER_RESULTS'] = component.get(
            'NOMOS_RESULT', []
          ) + component.get(
            'OJO_RESULT', []
          )
        else:
          component['SCANNER_RESULTS'] = self.__merge_nomos_ojo(
            component.get('NOMOS_RESULT', []), component.get('OJO_RESULT', [])
          )
    else:
      scanner_key = 'NOMOS_RESULT' if self.cli_options.nomos else 'OJO_RESULT'
      # Handle parent package separately
      self.scan_packages.parent_package[
        'SCANNER_RESULTS'] = self.scan_packages.parent_package.get(
        scanner_key, []
      )
      for purl in self.scan_packages.dependencies.keys():
        component = self.scan_packages.dependencies[purl]
        component['SCANNER_RESULTS'] = component.get(scanner_key, [])
