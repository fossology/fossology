#!/usr/bin/env python3

# SPDX-FileContributor: © Rajul Jha <rajuljha49@gmail.com>
# SPDX-FileContributor: Gaurav Mishra <mishra.gaurav@siemens.com>
# SPDX-FileCopyrightText: © 2025 Siemens AG

# SPDX-License-Identifier: GPL-2.0-only

import json
import os
from typing import Union

import requests
from packageurl import PackageURL
from packageurl.contrib import purl2url

DOWNLOAD_URL_KEY = 'fossology_download_url'
COMPONENT_TYPE_KEY = 'fossology_component_type'


class Parser:
  """
  Parser to classify each component based on it's type.
  Ex: If purl is pkg:pypi/django@1.11.1,
  it is a pypi package and should belong to python_components.
  """

  def __init__(self, sbom_file: str):
    """
    Initialize components list and load the sbom_data.
    Args:
        sbom_file: str | Path to sbom file
    """
    with open(sbom_file, 'r') as file:
      self.sbom_data = json.load(file)
    self.root_component_name = None
    self.parsed_components = {}

  def classify_components(self, root_download_dir: str):
    """
    Classify components based on it's type

    :param root_download_dir: Download dir prefix. Will be used to create
    download dir.
    """
    self.root_component_name = (self.sbom_data.get('metadata', {})
                                .get('component', {}).get('name', None))
    for component in self.sbom_data.get('components', []):
      purl = component.get('purl', '')
      if not purl or len(purl) == 0:
        continue
      comp_type = self._extract_type(purl)
      component['download_dir'] = os.path.join(
        root_download_dir, comp_type, component['name'], component['version']
        )
      component[COMPONENT_TYPE_KEY] = comp_type
      self.parsed_components[purl] = component

  def _extract_type(self, purl: str) -> Union[str, None]:
    """
    Extracts the package type from the purl.
    Example purl: pkg:pypi/django@1.11.1
    The type here is 'pypi'.
    Args:
        purl: str | Purl of the package to scan
    Return:
        purl_type: str | Type of component or None
    """
    # purl format: pkg:type/namespace/name@version?qualifiers#subpath
    try:
      if purl.startswith("pkg:"):
        parsed_purl = PackageURL.from_string(purl)
        return parsed_purl.type
      return None
    except Exception:
      return None

  @property
  def python_components(self):
    return [comp for comp in self.parsed_components.values() if
            comp[COMPONENT_TYPE_KEY] == 'pypi']

  @property
  def npm_components(self):
    return [comp for comp in self.parsed_components.values() if
            comp[COMPONENT_TYPE_KEY] == 'npm']

  @property
  def php_components(self):
    return [comp for comp in self.parsed_components.values() if
            comp[COMPONENT_TYPE_KEY] == 'composer']

  @property
  def unsupported_components(self):
    return [comp for comp in self.parsed_components.values() if
            comp[COMPONENT_TYPE_KEY] not in ['pypi', 'npm', 'composer']]


class PythonParser:
  """
  Python Parser to parse the python sboms to generate download urls from
  cyclonedx format sbom files.
  """

  PYPI_BINARY_DIST_WHEEL = 'bdist_wheel'
  PYPI_SOURCE_DIST = 'sdist'

  def _generate_api_endpoint(self, package_name: str, version: str) -> str:
    """
    Generate JSON REST API Endpoint to fetch download url.
    Args:
        package_name: str Name of package
        version: str Version of package
    Return:
        JSON REST API endpoint tp fetch metadata of package
    """
    return f"https://pypi.org/pypi/{package_name}/{version}/json"

  def parse_components(self, parser: Parser) -> Union[
    list[tuple[dict, str]], None]:
    """
    Parse SBOM file for package name and download url of package.
    Return:
        None
    """
    for comp in parser.python_components:
      component = parser.parsed_components[comp.get('purl')]
      package_name = component['name']
      version = component['version']
      api_endpoint = self._generate_api_endpoint(package_name, version)
      print(f"API endpoint for {package_name} : {api_endpoint}")
      response = requests.get(api_endpoint)

      if response.status_code == 200:
        data = response.json()
        sdist_url = None
        wheel_url = None

        for url_info in data.get('urls', []):
          if url_info.get('packagetype') == self.PYPI_SOURCE_DIST:
            sdist_url = url_info.get('url')
          elif url_info.get('packagetype') == self.PYPI_BINARY_DIST_WHEEL:
            wheel_url = url_info.get('url')

        # Prefer sdist, fallback to wheel if sdist is not available
        download_url = sdist_url if sdist_url else wheel_url
        if download_url:
          component[DOWNLOAD_URL_KEY] = download_url
        else:
          print(f"No suitable download URL found for {package_name} {version}")
        for key, value in data.get('info', {}).get('project_urls', {}).items():
          if "source" in key.lower():
            component['vcs_url'] = value
          if "homepage" in key.lower():
            component['homepage_url'] = value
      else:
        print(f"Failed to retrieve data for {package_name} {version}")


class NPMParser:
  """
  NPM Parser to parse the python sboms to generate download urls from
  cyclonedx format sbom files.
  """

  def _get_download_url(self, purl: str):
    """
    Get download url from purl for NPM Packages
    Args:
        purl: str
    Return:
        download_url: str
    """
    return purl2url.get_download_url(purl)

  def parse_components(self, parser: Parser) -> Union[
    list[tuple[dict, str]], None]:
    """
    Parse the components to extract the tuple of (<package_name>,
    <download_url>)
    Return:
        None
    """
    for comp in parser.npm_components:
      component = parser.parsed_components[comp.get('purl')]
      name = component['name']
      purl = component['purl']
      try:
        download_url = self._get_download_url(purl)
        component[DOWNLOAD_URL_KEY] = download_url
      except Exception as e:
        print(f"Invalid Download URL for NPM package: {name} :: {e}")
