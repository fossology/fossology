#!/usr/bin/env python3

# SPDX-FileContributor: © Rajul Jha <rajuljha49@gmail.com>

# SPDX-License-Identifier: GPL-2.0-only

import requests
import json
from typing import Dict, Union


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
        self.python_components = []
        self.npm_components = []
        self.php_components = []
        self.unsupported_components = []
    
    def classify_components(self):
        """
        Classify components based on it's type
        """
        for component in self.sbom_data.get('components',[]):
            purl = component.get('purl')
            if not purl:
                continue
            type = self._extract_type(purl)

            if type == 'pypi':
                self.python_components.append(component)
            # elif type == 'npm':
            #     self.npm_components.append(component)
            # elif type == 'composer':
            #     self.php_components.append(component)
            else:
                self.unsupported_components.append(component)

    def _extract_type(self, purl: str) -> Union[str,None]:
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
                purl_type = purl.split(':')[1].split('/')[0]
                return purl_type
            return None
        except Exception as e:
            return None, str(e)


class PythonParser:
    """
    Python Parser to parse the python sboms to generate download urls from
    cyclonedx format sbom files.
    """

    def __process_components(self, components : list[Dict]) -> list[str,str]:
        """
        Returns list of package name and version from SBOM component.
        Args:
            components: list[Dict]
        Return:
            list[str, str]: Name and versions of packages from sbom file
        """
        return [(comp['name'], comp['version']) for comp in components]

    def __generate_api_endpoint(self, package_name: str, version: str) -> str:
        """
        Generate JSON REST API Endpoint to fetch download url.
        Args:
            package_name: str Name of package
            version: str Version of paclage
        Return:
            JSON REST API endpoint tp fetch metadata of package
        """
        return f"https://pypi.org/pypi/{package_name}/{version}/json"

    def parse_components(self, components: list[Dict]) -> Union[list[tuple[str,str]],None]:
        """
        Parse SBOM file for package name and download url of package.
        Args:
            sbom_file: str Path to sbom_file
        Return:
            list of tuples with package_name and download_url of that package
        """
        download_urls = []
        packages = self.__process_components(components)
        
        for package_name, version in packages:
            api_endpoint = self.__generate_api_endpoint(package_name, version)
            print(f"API endpoint for {package_name} : {api_endpoint}")            
            response = requests.get(api_endpoint)
            
            if response.status_code == 200:
                data = response.json()
                sdist_url = None
                wheel_url = None

                for url_info in data.get('urls', []):
                    if url_info.get('packagetype') == 'sdist':
                        sdist_url = url_info.get('url')
                    elif url_info.get('packagetype') == 'bdist_wheel':
                        wheel_url = url_info.get('url')

                # Prefer sdist, fallback to wheel if sdist is not available
                download_url = sdist_url if sdist_url else wheel_url
                if download_url:
                    download_urls.append((package_name, download_url))
                else:
                    print(f"No suitable download URL found for {package_name} {version}")
            else:
                print(f"Failed to retrieve data for {package_name} {version}")

        return download_urls if download_urls else None
