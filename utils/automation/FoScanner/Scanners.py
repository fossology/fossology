#!/usr/bin/env python3

# SPDX-FileCopyrightText: © 2023 Siemens AG
# SPDX-FileContributor: Gaurav Mishra <mishra.gaurav@siemens.com>

# SPDX-License-Identifier: GPL-2.0-only

import fnmatch
import json
import multiprocessing
from subprocess import Popen, PIPE
from typing import List, Set, Union

from .CliOptions import CliOptions


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

  def __init__(self, cli_options: CliOptions):
    """
    Initialize the cli_options

    :param cli_options: CliOptions object to use
    :type cli_options: CliOptions
    """
    self.cli_options: CliOptions = cli_options

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

  def __normalize_path(self, path: str) -> str:
    """
    Normalize the given path to repository root

    :param path: path to normalize
    :return: Normalized path
    """
    return path.replace(f"{self.cli_options.diff_dir}/", '')

  def __get_nomos_result(self) -> dict:
    """
    Get the raw results from nomos scanner

    :return: raw json from nomos
    """
    nomossa_process = Popen([self.nomos_path, "-S", "-J", "-l", "-d",
                             self.cli_options.diff_dir, "-n",
                             str(multiprocessing.cpu_count() - 1)], stdout=PIPE)
    result = nomossa_process.communicate()[0]
    return json.loads(result.decode('UTF-8').strip())

  def __get_ojo_result(self) -> dict:
    """
    Get the raw results from ojo scanner

    :return: raw json from ojo
    """
    ojo_process = Popen([self.ojo_path, "-J", "-d", self.cli_options.diff_dir],
                        stdout=PIPE)
    result = ojo_process.communicate()[0]
    return json.loads(result.decode('UTF-8').strip())

  def __get_copyright_results(self) -> dict:
    """
    Get the raw results from copyright scanner

    :return: raw json from copyright
    """
    copyright_process = Popen([self.copyright_path, "-J", "-d",
                               self.cli_options.diff_dir], stdout=PIPE)
    result = copyright_process.communicate()[0]
    return json.loads(result.decode('UTF-8').strip())

  def __get_keyword_results(self) -> dict:
    """
    Get the raw results from keyword scanner

    :return: raw json from keyword
    """
    keyword_process = Popen([self.keyword_path, "-J", "-d",
                             self.cli_options.diff_dir], stdout=PIPE)
    result = keyword_process.communicate()[0]
    return json.loads(result.decode('UTF-8').strip())

  def get_copyright_list(self, all_results: bool = False, whole: bool = False) \
    -> Union[List[ScanResult],List[ScanResultList],bool]:
    """
    Get the formatted results from copyright scanner

    :param all_results: Get all results even excluded files?
    :type all_results: bool
    :param: whole: return whole content from scanner
    :return: list of findings
    :rtype: List[ScanResult] | List[ScanResultList]
    """
    copyright_results = self.__get_copyright_results()
    copyright_list = list()
    for result in copyright_results:
      path = self.__normalize_path(result['file'])
      if self.cli_options.repo is True and all_results is False and \
          self.is_excluded_path(path) is True:
        continue
      if result['results'] is not None and result['results'] != "Unable to " \
                                                                "read file":
        contents = set()
        json_copyright_info = list()
        for finding in result['results']:
          if whole:
            if finding is not None and finding['type'] == "statement" and finding['content'] != "":
              json_copyright_info.append(finding)              
          else:
            if finding is not None and finding['type'] == "statement":
              content = finding['content'].strip()
              if content != "":
                contents.add(content)
        if whole and len(json_copyright_info) > 0:
          copyright_list.append(ScanResultList(path, result['file'], json_copyright_info))
        elif not whole and len(contents) > 0:
          copyright_list.append(ScanResult(path, result['file'], contents))
    if len(copyright_list) > 0:
      return copyright_list
    return False
  
  def get_keyword_list(self, whole:bool = False) -> Union[List[ScanResult],List[ScanResultList],bool]:
    """
    Get the formatted results from keyword scanner

    :param: whole: return whole content from scanner
    :return: List of findings
    :rtype: List[ScanResult] | List[ScanResultList] | bool
    """
    keyword_results = self.__get_keyword_results()
    keyword_list = list()
    for result in keyword_results:
      path = self.__normalize_path(result['file'])
      if self.cli_options.repo is True and self.is_excluded_path(path) is \
          True:
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
          keyword_list.append(ScanResultList(path, result['file'], json_keyword_info))
        elif not whole and len(contents) > 0:
          keyword_list.append(ScanResult(path, result['file'], contents))
    if len(keyword_list) > 0:
      return keyword_list
    return False

  def __get_license_nomos(self, whole: bool = False) -> Union[List[ScanResult],List[ScanResultList]]:
    """
    Get the formatted results from nomos scanner

    :param: whole: return whole content from scanner
    :return: list of findings
    :rtype: List[ScanResult] | List[ScanResultList]
    """
    
    nomos_result = self.__get_nomos_result()
    scan_result = list()
    for result in nomos_result['results']: # result is an item of list and is a dict
      path = self.__normalize_path(result['file'])
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
        scan_result.append(ScanResultList(path,result['file'], json_license_info))
      elif not whole and len(licenses) > 0:
        scan_result.append(ScanResult(path, result['file'], licenses))
    return scan_result

  def __get_license_ojo(self, whole:bool = False) -> Union[List[ScanResult],List[ScanResultList]]:
    """
    Get the formatted results from ojo scanner

    :param: whole: return whole content from scanner
    :return: list of findings
    :rtype: List[ScanResult] | List[ScanResultList]
    """
    ojo_result = self.__get_ojo_result()
    scan_result = list()
    for result in ojo_result:
      path = self.__normalize_path(result['file'])
      if result['results'] is not None and result['results'] != 'Unable to ' \
                                                                'read file':
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
          scan_result.append(ScanResultList(path, result['file'], json_license_info))
    return scan_result

  def __merge_nomos_ojo(self, nomos_licenses: List[ScanResult],
                        ojo_licenses: List[ScanResult]) -> List[ScanResult]:
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

  def get_non_allow_listed_results(self, scan_results: List[ScanResult]= None, \
                                  scan_results_whole: List[ScanResultList]= None, \
                                  whole:bool = False) \
                                  -> Union[List[ScanResult],List[ScanResultList]]:
    """
    Get results where license check failed.

    :param scan_results: Scan result from ojo/nomos
    :param scan_results_whole: Whole scan result from ojo/nomos
    :param: whole: return whole content from scanner
    
    :return: List of results with only not allowed licenses
    :rtype: List[ScanResult] | List[ScanResultList]
    """
    final_results = []
    if whole and scan_results_whole is not None:
      for row in scan_results_whole:
        if self.cli_options.repo is True and self.is_excluded_path(row.file) \
          is True:
          continue
        license_info_list = row.result
        failed_licenses_list = list([lic for lic in license_info_list if lic['license'] not in
                              self.cli_options.allowlist['licenses']])
        if len(failed_licenses_list) > 0:
          final_results.append(ScanResultList(row.file, row.path, failed_licenses_list))
    elif not whole and scan_results is not None:
      for row in scan_results:
        if self.cli_options.repo is True and self.is_excluded_path(row.file) \
            is True:
          continue
        license_set = row.result
        failed_licenses = set([lic for lic in license_set if lic not in
                              self.cli_options.allowlist['licenses']])
        if len(failed_licenses) > 0:
          final_results.append(ScanResult(row.file, row.path, failed_licenses))
    return final_results

  def get_non_allow_listed_copyrights(self,
                                      copyright_results: List[ScanResult]) \
      -> List[ScanResult]:
    """
    Get copyrights from files which are not allow listed.

    :param copyright_results: Copyright results from copyright agent
    :return: List of scan results where copyrights found.
    """
    return [
      row for row in copyright_results if self.cli_options.repo is True and
                                          self.is_excluded_path(row.file) is
                                          False
    ]

  def results_are_allow_listed(self, whole:bool = False) \
    -> Union[List[ScanResult],List[ScanResultList],bool]:
    """
    Get the formatted list of license scanner findings

    The list contains the merged result of nomos/ojo scanner based on
    cli_options passed

    :param: whole: return whole content from scanner
    :return: merged list of scanner findings
    :rtype: List[ScanResult] | List[ScanResultList] | bool
    """
    failed_licenses = None
    nomos_licenses = []

    if self.cli_options.nomos:
      if whole is True:
        nomos_licenses = self.__get_license_nomos(whole=True)
      else:
        nomos_licenses = self.__get_license_nomos()
      if self.cli_options.ojo is False:
        if whole is True:
          failed_licenses = self.get_non_allow_listed_results(
          scan_results_whole=nomos_licenses, whole=True)
        else:
          failed_licenses = self.get_non_allow_listed_results(scan_results=nomos_licenses)
    if self.cli_options.ojo:
      if whole is True:
        ojo_licenses = self.__get_license_ojo(whole=True)
      else:
        ojo_licenses = self.__get_license_ojo()
      if self.cli_options.nomos is False:
        if whole is True:
          failed_licenses = self.get_non_allow_listed_results(
          scan_results_whole=ojo_licenses, whole=True)
        else:
          failed_licenses = self.get_non_allow_listed_results(scan_results=ojo_licenses)
      else:
        if whole is True:
          failed_licenses = self.get_non_allow_listed_results(
            scan_results_whole=nomos_licenses + ojo_licenses, whole=True)
        else:
          failed_licenses = self.get_non_allow_listed_results(
            scan_results=self.__merge_nomos_ojo(nomos_licenses, ojo_licenses))
    if len(failed_licenses) > 0:
      return failed_licenses
    return True

  def get_scanner_results(self, whole:bool = False) \
    -> Union[List[ScanResult],List[ScanResultList]]:
    """
    Get scan results from nomos and ojo scanners (whichever is selected).

    :param: whole: return whole content from scanner
    :return: List of scan results
    :rtype: List[ScanResult] | List[ScanResultList]
    """
    nomos_licenses = []
    ojo_licenses = []

    if self.cli_options.nomos:
      if whole:
        nomos_licenses = self.__get_license_nomos(whole=True)
      else:
        nomos_licenses = self.__get_license_nomos()
    if self.cli_options.ojo:
      if whole:
        ojo_licenses = self.__get_license_ojo(whole=True)
      else:
        ojo_licenses = self.__get_license_ojo()

    if self.cli_options.nomos and self.cli_options.ojo:
      if whole:
        return nomos_licenses + ojo_licenses
      else:
        return self.__merge_nomos_ojo(nomos_licenses, ojo_licenses)
    elif self.cli_options.nomos:
      return nomos_licenses
    else:
      return ojo_licenses
