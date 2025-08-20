#!/usr/bin/env python3

# SPDX-FileCopyrightText: © 2023,2025 Siemens AG
# SPDX-FileContributor: Gaurav Mishra <mishra.gaurav@siemens.com>

# SPDX-License-Identifier: GPL-2.0-only

import fnmatch
import json
import multiprocessing
import os
from subprocess import Popen, PIPE
from typing import List, Set, Union

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
  result: Set[str] = None

  def __init__(self, file: str, path: str, result: Set[str]):
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
  result: List[dict] = None

  def __init__(self, file: str, path: str, result: List[dict]):
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

  def get_scan_packages(self) -> Packages:
    return self.scan_packages

  def is_excluded_path(self, path: str) -> bool:
    """
    Check if the path is allow listed

    The function used fnmatch to check if the path is in allow list or not.

    :param path: path to check
    :return: True if the path is in allow list, False otherwise
    """
    path_is_excluded = False
    for pattern in self.cli_options.allowlist['exclude']:
      if fnmatch.fnmatchcase(path, pattern):
        path_is_excluded = True
        break
    return path_is_excluded

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

  def __get_nomos_result(self, dir_to_scan: str) -> dict:
    """
    Get the raw results from nomos scanner

    :return: raw json from nomos
    """
    nomossa_process = Popen(
      [
        self.nomos_path, "-S", "-J", "-l", "-d", dir_to_scan, "-n",
        str(multiprocessing.cpu_count() - 1)
      ], stdout=PIPE
    )
    result = nomossa_process.communicate()[0]
    return json.loads(result.decode('UTF-8').strip())

  def __get_ojo_result(self, dir_to_scan: str) -> dict:
    """
    Get the raw results from ojo scanner

    :return: raw json from ojo
    """
    ojo_process = Popen(
      [self.ojo_path, "-J", "-d", dir_to_scan], stdout=PIPE
    )
    result = ojo_process.communicate()[0]
    return json.loads(result.decode('UTF-8').strip())

  def __get_copyright_results(self, dir_to_scan: str) -> dict:
    """
    Get the raw results from copyright scanner

    :return: raw json from copyright
    """
    copyright_process = Popen(
      [
        self.copyright_path, "-J", "-d", dir_to_scan
      ], stdout=PIPE
    )
    result = copyright_process.communicate()[0]
    return json.loads(result.decode('UTF-8').strip())

  def __get_keyword_results(self, dir_to_scan: str) -> dict:
    """
    Get the raw results from keyword scanner

    :return: raw json from keyword
    """
    keyword_process = Popen(
      [
        self.keyword_path, "-J", "-d", dir_to_scan
      ], stdout=PIPE
    )
    result = keyword_process.communicate()[0]
    return json.loads(result.decode('UTF-8').strip())

  def set_copyright_list(
    self, all_results: bool = False, whole: bool = False) -> None:
    """
    Set the formatted results from copyright scanner for the components.

    :param all_results: Get all results even excluded files?
    :type all_results: bool
    :param whole: return whole content from scanner
    :type whole: bool
    :return: list of findings
    :rtype: List[ScanResult] | List[ScanResultList]
    """
    if not self.cli_options.scan_only_deps:
      self.scan_packages.parent_package[
        'COPYRIGHT_RESULT'] = self.__process_single_copyright_package(
        component=self.scan_packages.parent_package, is_parent=True,
        all_results=all_results, whole=whole
      )
    for purl in self.scan_packages.dependencies.keys():
      component = self.scan_packages.dependencies[purl]
      component['COPYRIGHT_RESULT'] = self.__process_single_copyright_package(
        component=component, is_parent=False, all_results=all_results,
        whole=whole
      )

  def __process_single_copyright_package(
    self, component: dict, is_parent: bool, all_results: bool = False,
    whole: bool = False, ) -> Union[List[ScanResult], List[ScanResultList]]:
    """
    Scan a component for copyrights.

    :param component: Component to scan
    :param is_parent: Is a parent component?
    :param all_results: Get results even from excluded files?
    :param whole: Return scan result list
    :return: List of scan results
    """
    if is_parent:
      dir_to_scan = self.cli_options.diff_dir
    else:
      dir_to_scan = os.path.join(
        component['download_dir'], component['base_dir']
      )
    copyright_results = self.__get_copyright_results(dir_to_scan)
    copyright_list = list()
    for result in copyright_results:
      path = self.__normalize_path(result['file'], dir_to_scan)
      if (self.cli_options.repo is True and all_results is False and
        self.is_excluded_path(
          path
        ) is True):
        continue
      if result['results'] is not None and result['results'] != "Unable to " \
                                                                "read file":
        contents = set()
        json_copyright_info = list()
        for finding in result['results']:
          if whole:
            if finding is not None and finding['type'] == "statement" and \
              finding['content'] != "":
              json_copyright_info.append(finding)
          else:
            if finding is not None and finding['type'] == "statement":
              content = finding['content'].strip()
              if content != "":
                contents.add(content)
        if whole and len(json_copyright_info) > 0:
          copyright_list.append(
            ScanResultList(path, result['file'], json_copyright_info)
          )
        elif not whole and len(contents) > 0:
          copyright_list.append(ScanResult(path, result['file'], contents))
    return copyright_list

  def set_keyword_list(self, whole: bool = False) -> None:
    """
    Get the formatted results from keyword scanner

    :param whole: return whole content from scanner
    """
    if not self.cli_options.scan_only_deps:
      self.scan_packages.parent_package[
        'KEYWORD_RESULT'] = self.__process_single_keyword_package(
        component=self.scan_packages.parent_package, is_parent=True, whole=whole
      )
    for purl in self.scan_packages.dependencies.keys():
      component = self.scan_packages.dependencies[purl]
      component['KEYWORD_RESULT'] = self.__process_single_keyword_package(
        component=component, is_parent=False, whole=whole
      )

  def __process_single_keyword_package(
    self, component: dict, is_parent: bool, whole: bool = False) -> Union[
    List[ScanResult], List[ScanResultList]]:
    """
    Process a single package for scanning for keywords.

    :param component: Component to scan
    :param is_parent: Is a parent component?
    :param whole: Get the whole JSON result
    :return: List of scan result
    """
    if is_parent:
      dir_to_scan = self.cli_options.diff_dir
    else:
      dir_to_scan = os.path.join(
        component['download_dir'], component['base_dir']
      )
    keyword_results = self.__get_keyword_results(dir_to_scan)
    keyword_list = list()
    for result in keyword_results:
      path = self.__normalize_path(result['file'], dir_to_scan)
      if self.cli_options.repo is True and self.is_excluded_path(path) is True:
        continue
      if result['results'] is not None and result['results'] != "Unable to " \
                                                                "read file":
        contents = set()
        json_keyword_info = list()
        for finding in result['results']:
          if whole:
            if finding is not None and finding['content'] != "":
              json_keyword_info.append(finding)
          else:
            if finding is not None:
              content = finding['content'].strip()
              if content != "":
                contents.add(content)
        if whole and len(json_keyword_info) > 0:
          keyword_list.append(
            ScanResultList(path, result['file'], json_keyword_info)
          )
        elif not whole and len(contents) > 0:
          keyword_list.append(ScanResult(path, result['file'], contents))
    return keyword_list

  def __set_license_nomos(self, whole: bool = False) -> None:
    """
    Update the packages with formatted results of nomos scanner

    :param whole: return whole content from scanner
    """
    if not self.cli_options.scan_only_deps:
      self.scan_packages.parent_package[
        'NOMOS_RESULT'] = self.__process_single_nomos_package(
        component=self.scan_packages.parent_package, is_parent=True, whole=whole
      )
    for purl in self.scan_packages.dependencies.keys():
      component = self.scan_packages.dependencies[purl]
      component['NOMOS_RESULT'] = self.__process_single_nomos_package(
        component=component, is_parent=False, whole=whole
      )

  def __process_single_nomos_package(
    self, component: dict, is_parent: bool, whole: bool) -> Union[
    List[ScanResult], List[ScanResultList]]:
    """
    Get the formatted results from nomos scanner for a single package

    :param component: Component to process
    :param is_parent: Is parent component?
    :param whole: return whole content from scanner
    :return: list of findings
    :rtype: List[ScanResult] | List[ScanResultList]
    """
    if is_parent:
      dir_to_scan = self.cli_options.diff_dir
    else:
      dir_to_scan = os.path.join(
        component['download_dir'], component['base_dir']
      )
    nomos_result = self.__get_nomos_result(dir_to_scan)
    scan_result = list()
    for result in nomos_result[
      'results']:  # result is an item of list and is a dict
      path = self.__normalize_path(result['file'], dir_to_scan)
      licenses = set()
      json_license_info = list()
      for scan_license in result['licenses']:
        if whole:
          if scan_license['license'] != "No_license_found":
            json_license_info.append(scan_license)
        else:
          if scan_license['license'] != 'No_license_found':
            licenses.add(scan_license['license'])
      if whole and len(json_license_info) > 0:
        scan_result.append(
          ScanResultList(path, result['file'], json_license_info)
        )
      elif not whole and len(licenses) > 0:
        scan_result.append(ScanResult(path, result['file'], licenses))
    return scan_result

  def __set_license_ojo(self, whole: bool = False) -> None:
    """
    Update the packages with formatted results of ojo scanner

    :param whole: return whole content from scanner
    """
    if not self.cli_options.scan_only_deps:
      self.scan_packages.parent_package[
        'OJO_RESULT'] = self.__process_single_ojo_package(
        component=self.scan_packages.parent_package, is_parent=True, whole=whole
      )
    for purl in self.scan_packages.dependencies.keys():
      component = self.scan_packages.dependencies[purl]
      component['OJO_RESULT'] = self.__process_single_ojo_package(
        component=component, is_parent=False, whole=whole
      )

  def __process_single_ojo_package(
    self, component: dict, is_parent: bool, whole: bool) -> Union[
    List[ScanResult], List[ScanResultList]]:
    """
    Get the formatted results from ojo scanner for a single package

    :param component: Component to process
    :param is_parent: Is parent component?
    :param whole: return whole content from scanner
    :return: list of findings
    :rtype: List[ScanResult] | List[ScanResultList]
    """
    if is_parent:
      dir_to_scan = self.cli_options.diff_dir
    else:
      dir_to_scan = os.path.join(
        component['download_dir'], component['base_dir']
      )
    ojo_result = self.__get_ojo_result(dir_to_scan)
    scan_result = list()
    for result in ojo_result:
      if result['results'] is not None and result['results'] != 'Unable to ' \
                                                                'read file':
        path = self.__normalize_path(result['file'], dir_to_scan)
        licenses = set()
        json_license_info = list()
        for finding in result['results']:
          if whole:
            if finding['license'] is not None:
              json_license_info.append(finding)
          else:
            if finding['license'] is not None:
              licenses.add(finding['license'].strip())
        if len(licenses) > 0:
          scan_result.append(ScanResult(path, result['file'], licenses))
        elif len(json_license_info) > 0:
          scan_result.append(
            ScanResultList(path, result['file'], json_license_info)
          )
    return scan_result

  def __merge_nomos_ojo(
    self, nomos_licenses: List[ScanResult], ojo_licenses: List[ScanResult]) -> \
    List[ScanResult]:
    """
    Merge the results from nomos and ojo based on file name

    :param nomos_licenses: formatted result form nomos
    :param ojo_licenses: formatted result form ojo

    :return: merged list of scanner findings
    """
    for ojo_entry in ojo_licenses:
      for nomos_entry in nomos_licenses:
        if ojo_entry.file == nomos_entry.file:
          nomos_entry.result.update(ojo_entry.result)
          break
      else:
        nomos_licenses.append(ojo_entry)
    return nomos_licenses

  def get_non_allow_listed_results(
    self, scan_results: List[ScanResult] = None,
    scan_results_whole: List[ScanResultList] = None, whole: bool = False) -> \
    Union[List[ScanResult], List[ScanResultList]]:
    """
    Get results where license check failed.

    :param scan_results: Scan result from ojo/nomos
    :param scan_results_whole: Whole scan result from ojo/nomos
    :param whole: return whole content from scanner
    
    :return: List of results with only not allowed licenses
    :rtype: List[ScanResult] | List[ScanResultList]
    """
    final_results = []
    if whole and scan_results_whole is not None:
      for row in scan_results_whole:
        if self.cli_options.repo is True and self.is_excluded_path(
          row.file
        ) is True:
          continue
        license_info_list = row.result
        failed_licenses_list = list(
          [lic for lic in license_info_list if
           lic['license'] not in self.cli_options.allowlist['licenses']]
        )
        if len(failed_licenses_list) > 0:
          final_results.append(
            ScanResultList(row.file, row.path, failed_licenses_list)
          )
    elif not whole and scan_results is not None:
      for row in scan_results:
        if self.cli_options.repo is True and self.is_excluded_path(
          row.file
        ) is True:
          continue
        license_set = row.result
        failed_licenses = set(
          [lic for lic in license_set if
           lic not in self.cli_options.allowlist['licenses']]
        )
        if len(failed_licenses) > 0:
          final_results.append(ScanResult(row.file, row.path, failed_licenses))
    return final_results

  def get_non_allow_listed_copyrights(self) -> List[ScanResult]:
    """
    Get copyrights from files which are not allow listed.

    :return: List of scan results where copyrights found.
    """
    copyright_results = self.get_copyright_results()
    return [row for row in copyright_results if
            self.cli_options.repo is True and self.is_excluded_path(
              row.file
            ) is False]

  def get_copyright_results(self) -> list[ScanResultList]:
    """
    Get list of copyright scan results from the package list.

    :return: List of copyright scan results
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

    :return: List of keywork scan results
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

    :return: List of license scan results
    """
    scanner_results = []
    scanner_results.extend(
      self.scan_packages.parent_package.get('SCANNER_RESULTS', [])
    )
    for dep in self.scan_packages.dependencies.values():
      scanner_results.extend(dep.get('SCANNER_RESULTS', []))
    return scanner_results

  def results_are_allow_listed(self, whole: bool = False) -> Union[
    List[ScanResult], List[ScanResultList]]:
    """
    Get the formatted list of license scanner findings

    The list contains the merged result of nomos/ojo scanner based on
    cli_options passed

    :param whole: return whole content from scanner
    :return: merged list of scanner findings
    :rtype: List[ScanResult] | List[ScanResultList]
    """
    scanner_results = self.get_license_results()

    failed_licenses = self.get_non_allow_listed_results(
      scan_results_whole=scanner_results, whole=True
    )

    if whole:
      return failed_licenses
    else:
      return [ScanResult(
        item.file, item.path, {res['license'] for res in item.result}
      ) for item in failed_licenses]

  def set_scanner_results(self, whole: bool = False) -> None:
    """
    Set the key `SCANNER_RESULTS` for all components in scan_packages using
    nomos and ojo scanners (whichever is selected).

    :param whole: return whole content from scanner
    """
    if self.cli_options.nomos:
      self.__set_license_nomos(whole)
    if self.cli_options.ojo:
      self.__set_license_ojo(whole)

    if self.cli_options.nomos and self.cli_options.ojo:
      if whole:
        self.scan_packages.parent_package[
          'SCANNER_RESULTS'] = self.scan_packages.parent_package.get(
          'NOMOS_RESULT', []
        ) + self.scan_packages.parent_package.get('OJO_RESULT', [])
        for purl in self.scan_packages.dependencies.keys():
          component = self.scan_packages.dependencies[purl]
          component['SCANNER_RESULTS'] = component.get(
            'NOMOS_RESULT', []
          ) + component.get(
            'OJO_RESULT', []
          )
      else:
        self.scan_packages.parent_package[
          'SCANNER_RESULTS'] = self.__merge_nomos_ojo(
          self.scan_packages.parent_package.get('NOMOS_RESULT', []),
          self.scan_packages.parent_package.get('OJO_RESULT', [])
        )
        for purl in self.scan_packages.dependencies.keys():
          component = self.scan_packages.dependencies[purl]
          component['SCANNER_RESULTS'] = self.__merge_nomos_ojo(
            component.get('NOMOS_RESULT', []), component.get('OJO_RESULT', [])
          )
    else:
      scanner_key = 'NOMOS_RESULT' if self.cli_options.nomos else 'OJO_RESULT'
      self.scan_packages.parent_package[
        'SCANNER_RESULTS'] = self.scan_packages.parent_package.get(
        scanner_key, []
      )
      for purl in self.scan_packages.dependencies.keys():
        component = self.scan_packages.dependencies[purl]
        component['SCANNER_RESULTS'] = component.get(scanner_key, [])
