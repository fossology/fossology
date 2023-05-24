#!/usr/bin/env python3

# SPDX-FileCopyrightText: © 2020 Siemens AG
# SPDX-FileCopyrightText: © anupam.ghosh@siemens.com
# SPDX-FileCopyrightText: © gaurav.mishra@siemens.com

# SPDX-License-Identifier: GPL-2.0-only

from subprocess import PIPE, Popen
import multiprocessing
import urllib.request
import tempfile
import fnmatch
import json
import ssl
import sys
import re
import os

RUNNING_ON = None
TRAVIS_REPO_SLUG = None
TRAVIS_PULL_REQUEST = None
API_URL = None
PROJECT_ID = None
MR_IID = None
API_TOKEN = None
GITHUB_TOKEN = None
GITHUB_REPOSITORY = None
GITHUB_PULL_REQUEST = None


def get_ci_name():
  '''
  Set the environment variables based on CI the job is running on
  '''
  global RUNNING_ON
  global TRAVIS_REPO_SLUG
  global TRAVIS_PULL_REQUEST
  global API_URL
  global PROJECT_ID
  global MR_IID
  global API_TOKEN
  global GITHUB_TOKEN
  global GITHUB_REPOSITORY
  global GITHUB_PULL_REQUEST

  if 'GITLAB_CI' in os.environ:
    RUNNING_ON = 'GITLAB'
    API_URL = os.environ['CI_API_V4_URL'] if 'CI_API_V4_URL' in os.environ else ''
    PROJECT_ID = os.environ['CI_PROJECT_ID'] if 'CI_PROJECT_ID' in os.environ else ''
    MR_IID = os.environ['CI_MERGE_REQUEST_IID'] if 'CI_MERGE_REQUEST_IID' in os.environ else ''
    API_TOKEN = os.environ['API_TOKEN'] if 'API_TOKEN' in os.environ else ''
  elif 'TRAVIS' in os.environ and os.environ['TRAVIS'] == 'true':
    RUNNING_ON = 'TRAVIS'
    TRAVIS_REPO_SLUG = os.environ['TRAVIS_REPO_SLUG']
    TRAVIS_PULL_REQUEST = os.environ['TRAVIS_PULL_REQUEST']
  elif 'GITHUB_ACTIONS' in os.environ and os.environ['GITHUB_ACTIONS'] == 'true':
    RUNNING_ON = 'GITHUB'
    GITHUB_TOKEN = os.environ['GITHUB_TOKEN']
    GITHUB_REPOSITORY = os.environ['GITHUB_REPOSITORY']
    GITHUB_PULL_REQUEST = os.environ['GITHUB_PULL_REQUEST']

class CliOptions(object):
  '''
  Hold the various shared flags and data

  :ivar nomos: run nomos scanner
  :ivar ojo: run ojo scanner
  :ivar copyright: run copyright scanner
  :ivar keyword: run keyword scanner
  :ivar repo: scan whole repo or just diff
  :ivar diff_dir: directory to scan
  :ivar whitelist: information from whitelist.json
  '''
  nomos = False
  ojo = False
  copyright = False
  keyword = False
  repo = False
  diff_dir = os.getcwd()
  whitelist = {
    'licenses': [],
    'exclude': []
  }


def get_white_list():
  """
  Decode json from `whitelist.json`

  :return: whilte list dictionary
  :rtype: dict()
  """
  with open('whitelist.json') as f:
    data = json.load(f)
  return data


class RepoSetup:
  """
  Setup temp_dir using the diff or current MR
  """

  def __init__(self, cli_options):
    """
    Create a temp dir

    :param cli_options: CliOptions object to get whitelist from
    :type cli_options: CliOptions
    """
    self.temp_dir = tempfile.TemporaryDirectory()
    self.whitelist = cli_options.whitelist

  def __del__(self):
    """
    Clean the created temp dir
    """
    self.temp_dir.cleanup()

  def __is_excluded_path(self, path):
    """
    Check if the path is whitelisted

    The function used fnmatch to check if the path is in whiltelist or not.

    :param path: path to check
    :type path: string

    :return: True if the path is in white list, False otherwise
    :rtype: boolean
    """
    path_is_excluded = False
    for pattern in self.whitelist['exclude']:
      if fnmatch.fnmatchcase(path, pattern):
        path_is_excluded = True
        break
    return path_is_excluded

  def get_diff_dir(self):
    """
    Populate temp dir using the gitlab API `merge_requests`

    :return: temp dir path
    :rtype: string
    """
    change_response = None
    path_key = None
    change_key = None

    if RUNNING_ON == "GITLAB":
      api_req_url = f"{API_URL}/projects/{PROJECT_ID}/merge_requests" + \
        f"/{MR_IID}/changes"
      headers = {'Private-Token': API_TOKEN}
      path_key = "new_path"
      change_key = "diff"
    elif RUNNING_ON == "GITHUB":
      api_req_url = f"https://api.github.com/repos/{GITHUB_REPOSITORY}" + \
          f"/pulls/{GITHUB_PULL_REQUEST}/files"
      headers = {
          "Authorization": f"Bearer {GITHUB_TOKEN}",
          "X-GitHub-Api-Version": "2022-11-28",
          "Accept": "application/vnd.github+json"
      }
      path_key = "filename"
      change_key = "patch"
    else:
      api_req_url = f"https://api.github.com/repos/{TRAVIS_REPO_SLUG}" + \
        f"/pulls/{TRAVIS_PULL_REQUEST}/files"
      headers = {}
      path_key = "filename"
      change_key = "patch"

    context = ssl.create_default_context()
    context.check_hostname = False
    context.verify_mode = ssl.CERT_NONE

    req = urllib.request.Request(api_req_url, headers=headers)
    try:
      with urllib.request.urlopen(req, context=context) as response:
        change_response = response.read()
    except Exception as e:
      print(f"Unable to get URL {api_req_url}")
      raise e

    change_response = json.loads(change_response)
    if RUNNING_ON == "GITLAB":
      changes = change_response['changes']
    else:
      changes = change_response

    remove_diff_regex = re.compile(r"^([ +-])(.*)$", re.MULTILINE)

    for change in changes:
      if path_key in change and change_key in change:
        path_to_be_excluded = self.__is_excluded_path(change[path_key])
        if path_to_be_excluded == False:
          curr_file = os.path.join(self.temp_dir.name, change[path_key])
          curr_dir = os.path.dirname(curr_file)
          if curr_dir != self.temp_dir.name:
            os.makedirs(name=curr_dir, exist_ok=True)
          curr_file = open(file=curr_file, mode='w+', encoding='UTF-8')
          print(re.sub(remove_diff_regex, r"\2", change[change_key]),
                file=curr_file)

    return self.temp_dir.name


class Scanners:
  """
  Handle all the data from different scanners

  :ivar nomos_path: path to nomos bin
  :ivar copyright_path: path to copyright bin
  :ivar keywrod_path: path to keyword bin
  :ivar ojo_path: path to ojo bin
  :ivar cli_options: CliOptions object
  """
  nomos_path = '/bin/nomossa'
  copyright_path = '/bin/copyright'
  keyword_path = '/bin/keyword'
  ojo_path = '/bin/ojo'

  def __init__(self, cli_options):
    """
    Initialize the cli_options

    :param cli_options: CliOptions object to use
    :type cli_options: CliOptions
    """
    self.cli_options = cli_options

  def __is_excluded_path(self, path):
    """
    Check if the path is whitelisted

    The function used fnmatch to check if the path is in whiltelist or not.

    :param path: path to check
    :type path: string

    :return: True if the path is in white list, False otherwise
    :rtype: boolean
    """
    path_is_excluded = False
    for pattern in self.cli_options.whitelist['exclude']:
      if fnmatch.fnmatchcase(path, pattern):
        path_is_excluded = True
        break
    return path_is_excluded

  def __normalize_path(self, path):
    """
    Noramalize the given path to repository root

    :param path: path to normalize
    :type path: string

    :return: False if the path is white listed, normalized path otherwise
    :rtype: string
    """
    path = path.replace(f"{self.cli_options.diff_dir}/", '')
    if self.cli_options.repo == True:
      path_is_excluded = self.__is_excluded_path(path)
      if path_is_excluded == True:
        return False
    return path

  def __get_nomos_result(self):
    """
    Get the raw results from nomos scanner

    :return: raw json from nomos
    :rtype: dict()
    """
    nomossa_process = Popen([self.nomos_path, "-J", "-l", "-d",
                             self.cli_options.diff_dir, "-n",
                             str(multiprocessing.cpu_count() - 1)], stdout=PIPE)
    result = nomossa_process.communicate()[0]
    return json.loads(result.decode('UTF-8').strip())

  def __get_ojo_result(self):
    """
    Get the raw results from ojo scanner

    :return: raw json from ojo
    :rtype: dict()
    """
    ojo_process = Popen([self.ojo_path, "-J", "-d", self.cli_options.diff_dir],
                        stdout=PIPE)
    result = ojo_process.communicate()[0]
    return json.loads(result.decode('UTF-8').strip())

  def __get_copyright_results(self):
    """
    Get the raw results from copyright scanner

    :return: raw json from copyright
    :rtype: dict()
    """
    copyright_process = Popen([self.copyright_path, "-J", "-d",
                               self.cli_options.diff_dir], stdout=PIPE)
    result = copyright_process.communicate()[0]
    return json.loads(result.decode('UTF-8').strip())

  def __get_keyword_results(self):
    """
    Get the raw results from keyword scanner

    :return: raw json from keyword
    :rtype: dict()
    """
    keyword_process = Popen([self.keyword_path, "-J", "-d",
                             self.cli_options.diff_dir], stdout=PIPE)
    result = keyword_process.communicate()[0]
    return json.loads(result.decode('UTF-8').strip())

  def get_copyright_list(self):
    """
    Get the formated results from copyright scanner

    :return: list of findings
    :rtype: list()
    """
    copyright_results = self.__get_copyright_results()
    copyright_list = list()
    for result in copyright_results:
      path = self.__normalize_path(result['file'])
      if path == False:
        continue
      if result['results'] != None and result['results'] != "Unable to read file":
        contents = list()
        for finding in result['results']:
          if finding is not None and finding['type'] == "statement":
            content = finding['content'].strip()
            if content != "":
              contents.append(content)
        if len(contents) > 0:
          copyright_list.append({
            'file': path,
            'result': contents
          })
    if len(copyright_list) > 0:
      return copyright_list
    return False

  def get_keyword_list(self):
    """
    Get the formated results from keyword scanner

    :return: list of findings
    :rtype: list()
    """
    keyword_results = self.__get_keyword_results()
    keyword_list = list()
    for result in keyword_results:
      path = self.__normalize_path(result['file'])
      if path == False:
        continue
      if result['results'] != None and result['results'] != "Unable to read file":
        contents = list()
        for finding in result['results']:
          if finding is not None:
            content = finding['content'].strip()
            if content != "":
              contents.append(content)
        if len(contents) > 0:
          keyword_list.append({
            'file': path,
            'result': contents
          })
    if len(keyword_list) > 0:
      return keyword_list
    return False

  def __get_non_whitelisted_license_nomos(self):
    """
    Get the formated results from nomos scanner

    :return: list of findings
    :rtype: list()
    """
    nomos_result = self.__get_nomos_result()
    failed_licenses = list()
    for result in nomos_result['results']:
      path = self.__normalize_path(result['file'])
      if path == False:
        continue
      if result['licenses'] != None and result['licenses'][0] != 'No_license_found':
        licenses = set()
        for license in result['licenses']:
          if license not in self.cli_options.whitelist['licenses'] and license != 'No_license_found':
            licenses.add(license.strip())
        if len(licenses) > 0:
          failed_licenses.append({
            'file': path,
            'result': licenses
          })
    return failed_licenses

  def __get_non_whitelisted_license_ojo(self):
    """
    Get the formated results from ojo scanner

    :return: list of findings
    :rtype: list()
    """
    ojo_result = self.__get_ojo_result()
    failed_licenses = list()
    for result in ojo_result:
      path = self.__normalize_path(result['file'])
      if path == False:
        continue
      if result['results'] != None and result['results'] != 'Unable to read file':
        licenses = set()
        for finding in result['results']:
          if finding['license'] not in self.cli_options.whitelist['licenses'] and finding['license'] != None:
            licenses.add(finding['license'].strip())
        if len(licenses) > 0:
          failed_licenses.append({
            'file': path,
            'result': licenses
          })
    return failed_licenses

  def __merge_nomos_ojo(self, nomos_licenses, ojo_licenses):
    """
    Merge the results from nomos and ojo based on file name

    :param nomos_licenses: formatted result form nomos
    :type nomos_licenses: list()
    :param ojo_licenses: formatted result form ojo
    :type ojo_licenses: list()

    :return: merged list of scanner findings
    :rtype: list()
    """
    for ojo_entry in ojo_licenses:
      for nomos_entry in nomos_licenses:
        if ojo_entry['file'] == nomos_entry['file']:
          nomos_entry['result'].update(ojo_entry['result'])
          break
      else:
        nomos_licenses.append(ojo_entry)
    return nomos_licenses

  def results_are_whitelisted(self):
    """
    Get the formatted list of license scanner findings

    The list contains the merged result of nomos/ojo scanner based on
    cli_options passed

    :return: merged list of scanner findings
    :rtype: list()
    """
    failed_licenses = None
    if self.cli_options.nomos:
      nomos_licenses = self.__get_non_whitelisted_license_nomos()
      if self.cli_options.ojo == False:
        failed_licenses = nomos_licenses
    if self.cli_options.ojo:
      ojo_licenses = self.__get_non_whitelisted_license_ojo()
      if self.cli_options.nomos == False:
        failed_licenses = ojo_licenses
      else:
        failed_licenses = self.__merge_nomos_ojo(nomos_licenses,
                             ojo_licenses)
    if len(failed_licenses) > 0:
      return failed_licenses
    return True


def parse_argv(argv):
  """
  Parse the arguments passed and translate them to CliOptions object

  :return: CliOptions object
  :rtype: CliOptions
  """
  cli_options = CliOptions
  if "nomos" in argv:
    cli_options.nomos = True
  if "copyright" in argv:
    cli_options.copyright = True
  if "keyword" in argv:
    cli_options.keyword = True
  if "ojo" in argv:
    cli_options.ojo = True
  if "repo" in argv:
    cli_options.repo = True
  if cli_options.nomos == False and cli_options.ojo == False and cli_options.copyright == False and cli_options.keyword == False:
    cli_options.nomos = True
    cli_options.ojo = True
    cli_options.copyright = True
    cli_options.keyword = True
  return cli_options


def print_results(name, failed_results, result_file):
  """
  Print the formatted scanner results

  :param name: Name of the scanner
  :type name: string
  :param failed_results: formatted scanner results to be printed
  :type failed_results: list()
  :param result_file: File to write results to
  :type result_file: TextIOWrapper
  """
  for files in failed_results:
    print(f"File: {files['file']}")
    result_file.write(f"File: {files['file']}\n")
    plural = ""
    if len(files['result']) > 1:
      plural = "s"
    print(f"{name}{plural}:")
    result_file.write(f"{name}{plural}:\n")
    for result in files['result']:
      print("\t" + result)
      result_file.write("\t" + result + "\n")


def main(argv):
  get_ci_name()
  cli_options = parse_argv(argv)

  try:
    cli_options.whitelist = get_white_list()
  except FileNotFoundError:
    print("Unable to find whitelist.json in current dir\n" +
          "Continuing without it.", file=sys.stderr)

  repo_setup = RepoSetup(cli_options)
  if cli_options.repo == False:
    cli_options.diff_dir = repo_setup.get_diff_dir()

  scanner = Scanners(cli_options)
  return_val = 0

  # Create result dir
  result_dir = "results"
  os.makedirs(name=result_dir, exist_ok=True)

  if cli_options.nomos or cli_options.ojo:
    license_file = open(f"{result_dir}/licenses.txt", 'w')
    failed_licenses = scanner.results_are_whitelisted()
    if failed_licenses != True:
      print("\u2718 Following licenses found which are not whitelisted:")
      license_file.write("Following licenses found which are not whitelisted:\n")
      print_results("License", failed_licenses, license_file)
      return_val = return_val | 2
    else:
      print("\u2714 No license violation found")
      license_file.write("No license violation found")
    print()
    license_file.close()
  if cli_options.copyright:
    copyright_file = open(f"{result_dir}/copyrights.txt", 'w')
    copyright_results = scanner.get_copyright_list()
    if copyright_results != False:
      print("\u2718 Following copyrights found:")
      copyright_file.write("Following copyrights found:\n")
      print_results("Copyright", copyright_results, copyright_file)
      return_val = return_val | 4
    else:
      print("\u2714 No copyright violation found")
      copyright_file.write("No copyright violation found")
    print()
    copyright_file.close()
  if cli_options.keyword:
    keyword_file = open(f"{result_dir}/keywords.txt", 'w')
    keyword_results = scanner.get_keyword_list()
    if keyword_results != False:
      print("\u2718 Following keywords found:")
      keyword_file.write("Following keywords found:\n")
      print_results("Keyword", keyword_results, keyword_file)
      return_val = return_val | 8
    else:
      print("\u2714 No keyword violation found")
      keyword_file.write("No keyword violation found")
    print()
    keyword_file.close()
  return return_val


if __name__ == "__main__":
   sys.exit(main(sys.argv))
