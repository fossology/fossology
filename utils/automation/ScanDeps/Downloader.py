#!/usr/bin/env python3

# SPDX-FileContributor: © Rajul Jha <rajuljha49@gmail.com>
# SPDX-FileContributor: Gaurav Mishra <mishra.gaurav@siemens.com>
# SPDX-FileCopyrightText: © 2025 Siemens AG

# SPDX-License-Identifier: GPL-2.0-only

import concurrent.futures
import os
import re
import tarfile
import threading
import zipfile

import requests

from .Parsers import DOWNLOAD_URL_KEY, Parser


class Downloader:
  """
  Class for parallely downloading dependencies from download urls.
  """

  def __init__(self):
    self.parser = None
    self.lock = threading.Lock()

  def __download_package(self, component: dict) -> None:
    download_url = component.get(DOWNLOAD_URL_KEY, None)
    if download_url is None:
      return None
    package_name = component['name']
    response = requests.get(download_url)
    package_folder = component['download_dir']
    if not os.path.exists(package_folder):
      os.makedirs(package_folder)

    match = re.search(
      r'\.tar\.gz$|\.tar\.bz2$|\.tar\.xz$|\.zip$|\.whl$|\.tar$',
      download_url
      )
    file_extension = match.group(0) if match else \
    os.path.splitext(download_url)[1]
    file_path = os.path.join(package_folder, package_name + file_extension)

    with open(file_path, 'wb') as f:
      f.write(response.content)

    print(f"Downloaded {package_name} to {file_path}")

    # Unpack the file based on its extension
    if file_extension == ".zip":
      with zipfile.ZipFile(file_path, 'r') as zip_ref:
        zip_ref.extractall(package_folder)
        purl = component['purl']
        with self.lock:
          if purl in self.parser.parsed_components:
            self.parser.parsed_components[purl]['base_dir'] = ""
    elif file_extension in [".tar.gz", ".tgz", ".tar"]:
      with tarfile.open(file_path, 'r:*') as tar_ref:
        tar_ref.extractall(package_folder)
        if tar_ref.getmembers()[0].isdir():
          base_dir = tar_ref.getmembers()[0].path
        else:
          base_dir = os.path.dirname(tar_ref.getmembers()[0].path)

        purl = component['purl']
        with self.lock:
          if purl in self.parser.parsed_components:
            self.parser.parsed_components[purl]['base_dir'] = base_dir
    else:
      print(f"Unsupported file format: {file_extension}")
      return None
    os.remove(file_path)
    print(f"Exported {package_name} to {package_folder}")

    return None

  def download_concurrently(self, parser: Parser):
    """
    Download files concurrently from a list of urls
    """
    self.parser = parser

    download_list = [
      component for component in parser.parsed_components.values()
      if component.get(DOWNLOAD_URL_KEY, None) is not None
    ]
    with concurrent.futures.ThreadPoolExecutor() as executor:
      futures = [
        executor.submit(self.__download_package, comp)
        for comp in download_list
      ]

      for future in concurrent.futures.as_completed(futures):
        try:
          future.result()
        except Exception as e:
          print(f"Error downloading package: {e}")
    return f"{len(download_list)} packages downloaded."
