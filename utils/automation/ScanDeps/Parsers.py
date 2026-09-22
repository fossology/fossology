#!/usr/bin/env python3

# SPDX-FileContributor: © Rajul Jha <rajuljha49@gmail.com>
# SPDX-FileContributor: Gaurav Mishra <mishra.gaurav@siemens.com>
# SPDX-FileCopyrightText: © 2025 Siemens AG

# SPDX-License-Identifier: GPL-2.0-only

import json
import logging
import os
from typing import Any
from urllib.parse import quote

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
    try:
      with open(sbom_file, 'r', encoding='utf-8') as file:
        self.sbom_data = json.load(file)
    except FileNotFoundError as e:
      logging.error(f"SBOM file not found: {sbom_file}")
      raise e
    except json.JSONDecodeError as e:
      logging.error(f"Invalid JSON in SBOM file: {sbom_file}")
      raise e
    except Exception as e:
      logging.error(
        f"An unexpected error occurred while reading SBOM file {sbom_file}: {e}"
      )
      raise e

    self.root_component_name = None
    self.parsed_components: dict[str, dict[str, Any]] = {}

  def classify_components(self, root_download_dir: str):
    """
    Classify components based on it's type

    :param root_download_dir: Download dir prefix. Will be used to create
    download dir.
    """
    self.root_component_name = (
      self.sbom_data.get('metadata', {})
      .get('component', {}).get('name', None))
    for component in self.sbom_data.get('components', []):
      purl = component.get('purl', '')
      if not purl:
        continue
      comp_type = self._extract_type(purl)

      # Ensure component has 'name' and 'version' for path construction
      comp_name = component.get('name', 'unknown_name')
      comp_version = component.get('version', 'unknown_version')

      component['download_dir'] = os.path.join(
        root_download_dir, comp_type or 'unclassified', comp_name, comp_version
      )
      component[COMPONENT_TYPE_KEY] = comp_type
      self.parsed_components[purl] = component

  def _extract_type(self, purl: str) -> str | None:
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
    except ValueError as e:
      logging.warning(f"Could not parse PURL '{purl}': {e}")
      return None
    except Exception as e:
      logging.error(
        "An unexpected error occurred while extracting PURL type for '"
        f"{purl}': {e}"
      )
      return None

  @property
  def python_components(self) -> list[dict[str, Any]]:
    return [comp for comp in self.parsed_components.values() if
            comp.get(COMPONENT_TYPE_KEY) == 'pypi']

  @property
  def npm_components(self) -> list[dict[str, Any]]:
    return [comp for comp in self.parsed_components.values() if
            comp.get(COMPONENT_TYPE_KEY) == 'npm']

  @property
  def php_components(self) -> list[dict[str, Any]]:
    return [comp for comp in self.parsed_components.values() if
            comp.get(COMPONENT_TYPE_KEY) == 'composer']

  @property
  def maven_components(self) -> list[dict[str, Any]]:
    return [comp for comp in self.parsed_components.values() if
            comp.get(COMPONENT_TYPE_KEY) == 'maven']

  @property
  def golang_components(self) -> list[dict[str, Any]]:
    return [comp for comp in self.parsed_components.values() if
            comp.get(COMPONENT_TYPE_KEY) == 'golang']

  @property
  def github_components(self) -> list[dict[str, Any]]:
    return [comp for comp in self.parsed_components.values() if
            comp.get(COMPONENT_TYPE_KEY) == 'github']

  @property
  def debian_components(self) -> list[dict[str, Any]]:
    return [comp for comp in self.parsed_components.values() if
            comp.get(COMPONENT_TYPE_KEY) == 'deb']

  @property
  def unsupported_components(self) -> list[dict[str, Any]]:
    return [comp for comp in self.parsed_components.values() if
            comp.get(COMPONENT_TYPE_KEY) not in [
              'pypi', 'npm', 'composer', 'maven', 'golang', 'github', 'deb'
            ]]


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

  def parse_components(self, parser: Parser) -> None:
    """
    Parse SBOM file for package name and download url of package.
    Return:
        None
    """
    for comp in parser.python_components:
      purl = comp.get('purl')
      if not purl:
        logging.warning(f"Python component missing PURL: {comp}. Skipping.")
        continue

      component = parser.parsed_components.get(purl)
      if not component:
        logging.warning(
          f"Component with PURL {purl} not found in parsed_components. "
          "Skipping."
        )
        continue

      package_name = component.get('name')
      version = component.get('version')
      if not package_name or not version:
        logging.warning(
          f"Python component {purl} missing name or version. Skipping."
        )
        continue

      api_endpoint = self._generate_api_endpoint(package_name, version)
      logging.info(f"API endpoint for {package_name} : {api_endpoint}")

      try:
        response = requests.get(
          api_endpoint, timeout=10
        )
        response.raise_for_status()  # Raise an exception for HTTP errors (
        # 4xx or 5xx)

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
          logging.warning(
            f"No suitable download URL found for {package_name} {version}"
          )

        # Extract VCS and homepage URLs
        project_urls = data.get('info', {}).get('project_urls') or {}
        for key, value in project_urls.items():
          if "source" in key.lower():
            component['vcs_url'] = value
          if "homepage" in key.lower():
            component['homepage_url'] = value

      except requests.exceptions.RequestException as e:
        logging.error(
          f"Failed to retrieve data for {package_name} {version} from "
          f"{api_endpoint}: {e}"
        )
      except json.JSONDecodeError:
        logging.error(
          f"Failed to decode JSON response from {api_endpoint} for "
          f"{package_name} {version}."
        )
      except Exception as e:
        logging.error(
          f"An unexpected error occurred while parsing Python component "
          f"{purl}: {e}"
        )


class NPMParser:
  """
  NPM Parser to parse the python sboms to generate download urls from
  cyclonedx format sbom files.
  """

  def _get_download_url(self, purl: str) -> str:
    """
    Get download url from purl for NPM Packages
    Args:
        purl: str
    Return:
        download_url: str
    """
    return purl2url.get_download_url(purl)

  def parse_components(self, parser: Parser) -> None:
    """
    Parse the components to extract the tuple of (<package_name>,
    <download_url>)
    Return:
        None
    """
    for comp in parser.npm_components:
      purl = comp.get('purl')
      if not purl:
        logging.warning(f"NPM component missing PURL: {comp}. Skipping.")
        continue

      component = parser.parsed_components.get(purl)
      if not component:
        logging.warning(
          f"Component with PURL {purl} not found in parsed_components. "
          f"Skipping."
        )
        continue

      name = component.get('name', 'unknown_name')
      try:
        download_url = self._get_download_url(purl)
        component[DOWNLOAD_URL_KEY] = download_url
      except Exception as e:
        logging.error(
          f"Invalid Download URL for NPM package: {name} ({purl}) :: {e}"
        )


class ComposerParser:
  """
  Composer Parser to parse PHP sboms to generate download urls from
  cyclonedx format sbom files using the Packagist API.
  """

  def _generate_api_endpoint(self, vendor: str, package: str) -> str:
    """
    Generate Packagist API endpoint to fetch package metadata.
    Args:
        vendor: str Vendor/namespace of the package
        package: str Name of the package
    Return:
        Packagist API endpoint URL
    """
    return f"https://repo.packagist.org/p2/{vendor}/{package}.json"

  def parse_components(self, parser: Parser) -> None:
    """
    Parse SBOM file for PHP package name and download url.
    Return:
        None
    """
    for comp in parser.php_components:
      purl = comp.get('purl')
      if not purl:
        logging.warning(
          f"Composer component missing PURL: {comp}. Skipping."
        )
        continue

      component = parser.parsed_components.get(purl)
      if not component:
        logging.warning(
          f"Component with PURL {purl} not found in parsed_components. "
          "Skipping."
        )
        continue

      try:
        parsed_purl = PackageURL.from_string(purl)
      except ValueError as e:
        logging.warning(f"Could not parse PURL '{purl}': {e}. Skipping.")
        continue

      vendor = parsed_purl.namespace
      package_name = parsed_purl.name
      version = parsed_purl.version

      if not vendor or not package_name or not version:
        logging.warning(
          f"Composer component {purl} missing namespace, name, or version. "
          "Skipping."
        )
        continue

      api_endpoint = self._generate_api_endpoint(vendor, package_name)
      logging.info(
        f"API endpoint for {vendor}/{package_name} : {api_endpoint}"
      )

      try:
        response = requests.get(api_endpoint, timeout=10)
        response.raise_for_status()

        data = response.json()
        packages = data.get('packages', {}).get(
          f"{vendor}/{package_name}", []
        )

        download_url = None
        for release in packages:
          if release.get('version') == version:
            download_url = release.get('dist', {}).get('url')
            source_url = release.get('source', {}).get('url')
            if source_url:
              component['vcs_url'] = source_url
            break

        if download_url:
          component[DOWNLOAD_URL_KEY] = download_url
        else:
          logging.warning(
            f"No download URL found for {vendor}/{package_name} {version}"
          )

      except requests.exceptions.RequestException as e:
        logging.error(
          f"Failed to retrieve data for {vendor}/{package_name} {version} "
          f"from {api_endpoint}: {e}"
        )
      except json.JSONDecodeError:
        logging.error(
          f"Failed to decode JSON response from {api_endpoint} for "
          f"{vendor}/{package_name} {version}."
        )
      except Exception as e:
        logging.error(
          f"An unexpected error occurred while parsing Composer component "
          f"{purl}: {e}"
        )


class MavenParser:
  """
  Maven Parser to parse Java/Kotlin sboms to generate download urls from
  cyclonedx format sbom files using purl2url and Maven Central.
  """

  MAVEN_CENTRAL_BASE = "https://repo1.maven.org/maven2"

  def _get_download_url(self, purl: str) -> str | None:
    """
    Get download url from purl for Maven packages.
    Tries purl2url first, falls back to constructing Maven Central URL.
    Args:
        purl: str
    Return:
        download_url: str | None
    """
    url = purl2url.get_download_url(purl)
    if url:
      return url

    # Fallback: construct Maven Central URL directly
    try:
      parsed_purl = PackageURL.from_string(purl)
      group_id = parsed_purl.namespace
      artifact_id = parsed_purl.name
      version = parsed_purl.version
      if not group_id or not artifact_id or not version:
        return None
      group_path = group_id.replace('.', '/')
      return (
        f"{self.MAVEN_CENTRAL_BASE}/{group_path}/{artifact_id}/{version}/"
        f"{artifact_id}-{version}-sources.jar"
      )
    except ValueError:
      return None

  def parse_components(self, parser: Parser) -> None:
    """
    Parse the components to extract download URLs for Maven packages.
    Prefers source JARs for license scanning.
    Return:
        None
    """
    for comp in parser.maven_components:
      purl = comp.get('purl')
      if not purl:
        logging.warning(f"Maven component missing PURL: {comp}. Skipping.")
        continue

      component = parser.parsed_components.get(purl)
      if not component:
        logging.warning(
          f"Component with PURL {purl} not found in parsed_components. "
          f"Skipping."
        )
        continue

      name = component.get('name', 'unknown_name')
      try:
        download_url = self._get_download_url(purl)
        if download_url:
          component[DOWNLOAD_URL_KEY] = download_url
        else:
          logging.warning(
            f"No download URL could be determined for Maven package: "
            f"{name} ({purl})"
          )
      except Exception as e:
        logging.error(
          f"Invalid Download URL for Maven package: {name} ({purl}) :: {e}"
        )


class GoParser:
  """
  Resolve Go module source archives through the public Go module proxy.
  """

  GO_PROXY_BASE = 'https://proxy.golang.org'

  @staticmethod
  def _escape_module_path(module_path: str) -> str:
    """Apply the escaping required by the Go module proxy."""
    return ''.join(
      f'!{character.lower()}' if character.isupper() else character
      for character in module_path
    )

  def _get_download_url(self, purl: str) -> str | None:
    try:
      parsed_purl = PackageURL.from_string(purl)
      if not parsed_purl.name or not parsed_purl.version:
        return None
      module_path = '/'.join(
        filter(None, [parsed_purl.namespace, parsed_purl.name])
      )
      return (
        f'{self.GO_PROXY_BASE}/'
        f'{quote(self._escape_module_path(module_path), safe="/")}/@v/'
        f'{quote(parsed_purl.version, safe="")}.zip'
      )
    except ValueError:
      return None

  def parse_components(self, parser: Parser) -> None:
    for component in parser.golang_components:
      purl = component.get('purl')
      if not purl:
        logging.warning(f'Go component missing PURL: {component}. Skipping.')
        continue
      download_url = self._get_download_url(purl)
      if download_url:
        component[DOWNLOAD_URL_KEY] = download_url
      else:
        logging.warning(
          f'No download URL could be determined for Go package: {purl}'
        )


class GitHubParser:
  """
  Resolve GitHub package URLs to source archive downloads.
  """

  def parse_components(self, parser: Parser) -> None:
    for component in parser.github_components:
      purl = component.get('purl')
      if not purl:
        logging.warning(
          f'GitHub component missing PURL: {component}. Skipping.'
        )
        continue
      try:
        download_url = purl2url.get_download_url(purl)
      except (ValueError, TypeError) as error:
        logging.warning(
          f'Could not resolve GitHub package {purl}: {error}'
        )
        continue
      if download_url:
        component[DOWNLOAD_URL_KEY] = download_url
      else:
        logging.warning(
          f'No download URL could be determined for GitHub package: {purl}'
        )


class DebianParser:
  """
  Resolve Debian binary PURLs to immutable package files in Debian Snapshot.
  """

  SNAPSHOT_API = 'https://snapshot.debian.org/mr/binary'
  SNAPSHOT_FILE_BASE = 'https://snapshot.debian.org/file'

  def _get_download_url(self, purl: str) -> str | None:
    try:
      parsed_purl = PackageURL.from_string(purl)
    except ValueError:
      return None

    package_name = parsed_purl.name
    package_version = parsed_purl.version
    architecture = parsed_purl.qualifiers.get(
      'arch', parsed_purl.qualifiers.get('architecture')
    )
    if not package_name or not package_version or not architecture:
      return None

    endpoint = (
      f'{self.SNAPSHOT_API}/{quote(package_name, safe="")}/'
      f'{quote(package_version, safe="")}/binfiles'
    )
    try:
      response = requests.get(endpoint, timeout=10)
      response.raise_for_status()
      files = response.json().get('result', [])
    except (requests.exceptions.RequestException, json.JSONDecodeError) as error:
      logging.error(
        f'Failed to resolve Debian package {package_name} '
        f'{package_version}: {error}'
      )
      return None

    for package_file in files:
      if package_file.get('architecture') == architecture:
        file_hash = package_file.get('hash')
        if file_hash:
          return f'{self.SNAPSHOT_FILE_BASE}/{file_hash}'
    return None

  def parse_components(self, parser: Parser) -> None:
    for component in parser.debian_components:
      purl = component.get('purl')
      if not purl:
        logging.warning(
          f'Debian component missing PURL: {component}. Skipping.'
        )
        continue
      download_url = self._get_download_url(purl)
      if download_url:
        component[DOWNLOAD_URL_KEY] = download_url
      else:
        logging.warning(
          f'No download URL could be determined for Debian package: {purl}'
        )
