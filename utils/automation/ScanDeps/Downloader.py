#!/usr/bin/env python3

# SPDX-FileContributor: © Rajul Jha <rajuljha49@gmail.com>
# SPDX-FileContributor: Gaurav Mishra <mishra.gaurav@siemens.com>
# SPDX-FileCopyrightText: © 2025 Siemens AG

# SPDX-License-Identifier: GPL-2.0-only

import concurrent.futures
import logging
import os
import tarfile
import threading
import urllib.parse
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
    self.download_timeout = 30  # Seconds for network requests

  def __get_archive_base_dir(self, archive_path: str) -> str:
    """
    Determines the base directory within an extracted archive.
    Assumes a common pattern where archives may contain a single top-level
    directory or none.
    """
    base_dir = ""
    try:
      if zipfile.is_zipfile(archive_path):
        with zipfile.ZipFile(archive_path, 'r') as zip_ref:
          members = zip_ref.namelist()
          if members:
            # Check if all members start with a common prefix (single root
            # directory)
            first_part = members[0].split(os.sep)[0]
            if all(
                m.startswith(first_part + os.sep) or m == first_part for m in
                members
            ):
              base_dir = first_part
            # else: archive extracts directly into
            # package_folder or has multiple roots
      elif tarfile.is_tarfile(archive_path):
        with tarfile.open(archive_path, 'r:*') as tar_ref:
          members = tar_ref.getnames()
          if members:
            first_part = members[0].split(os.sep)[0]
            if all(
                m.startswith(first_part + os.sep) or m == first_part for m in
                members
            ):
              base_dir = first_part
            # else: archive extracts directly into
            # package_folder or has multiple roots
    except (
        zipfile.BadZipFile, tarfile.ReadError, tarfile.FilterError, IOError
    ) as e:
      logging.warning(
        f"Could not inspect archive {archive_path} for base directory: {e}"
      )
    return base_dir

  def __download_package(self, component: dict) -> None:
    download_url = component.get(DOWNLOAD_URL_KEY)
    if not download_url:
      logging.warning(
        "No download URL found for component: "
        f"{component.get('name', 'N/A')}. Skipping."
      )
      return

    package_name = component.get('name', 'unknown_package')
    package_folder = component.get('download_dir')

    if not package_folder:
      logging.error(
        f"Download directory not specified for {package_name}. Skipping."
      )
      return

    os.makedirs(package_folder, exist_ok=True)

    parsed_url = urllib.parse.urlparse(download_url)
    filename = os.path.basename(parsed_url.path)
    if not filename:  # Fallback if basename is empty (e.g., URL ends with /)
      filename = f"{package_name}_download"

    # Try to determine a common archive extension or use a generic one
    # This list is more specific for common archive types
    archive_extensions = [
      '.tar.gz', '.tgz', '.tar.bz2', '.tbz', '.tar.xz', '.txz', '.zip', '.whl',
      '.tar'
    ]
    file_extension = ''
    for ext in archive_extensions:
      if filename.lower().endswith(ext):
        file_extension = ext
        break
    if not file_extension:
      # Fallback to simple splitext if no known archive extension is found
      _, file_extension = os.path.splitext(filename)
      if not file_extension:  # Ensure there's at least some extension
        file_extension = '.bin'  # Unable to determine. Ignore

    file_path = os.path.join(package_folder, f"{package_name}{file_extension}")

    temp_archive_path = file_path  # Use this path for download and extraction

    try:
      logging.info(
        f"Downloading {package_name} from {download_url} to {temp_archive_path}"
      )
      response = requests.get(
        download_url, stream=True, timeout=self.download_timeout
      )
      response.raise_for_status()  # Raise HTTPError for bad responses (4xx
      # or 5xx)

      with open(temp_archive_path, 'wb') as f:
        for chunk in response.iter_content(chunk_size=8192):
          f.write(chunk)
      logging.info(f"Downloaded {package_name} to {temp_archive_path}")

      if temp_archive_path.lower().endswith('.zip'):
        with zipfile.ZipFile(temp_archive_path, 'r') as zip_ref:
          zip_ref.extractall(package_folder)
        base_dir = self.__get_archive_base_dir(
          temp_archive_path
        )
      elif temp_archive_path.lower().endswith(
          ('.tar.gz', '.tgz', '.tar.bz2', '.tbz', '.tar.xz', '.txz', '.tar')
      ):
        with tarfile.open(temp_archive_path, 'r:*') as tar_ref:
          tar_ref.extractall(package_folder)
        base_dir = self.__get_archive_base_dir(
          temp_archive_path
        )
      else:
        logging.warning(
          f"Unsupported file format for extraction: {file_extension} for "
          f"{package_name}. File downloaded but not extracted."
        )
        return

      # Update base_dir in parser's components, ensuring thread safety
      purl = component.get('purl')
      if purl and self.parser and purl in self.parser.parsed_components:
        with self.lock:
          self.parser.parsed_components[purl]['base_dir'] = base_dir

      logging.info(
        f"Exported {package_name} to {package_folder} (base_dir: '{base_dir}')"
      )

    except requests.exceptions.Timeout:
      logging.error(
        f"Timeout occurred while downloading {package_name} from {download_url}"
      )
    except requests.exceptions.HTTPError as e:
      logging.error(
        f"HTTP error {e.response.status_code} while downloading "
        f"{package_name} from {download_url}: {e}"
      )
    except requests.exceptions.RequestException as e:
      logging.error(
        f"Network error while downloading {package_name} from {download_url}: "
        f"{e}"
      )
    except (zipfile.BadZipFile, tarfile.ReadError, tarfile.FilterError) as e:
      logging.error(
        f"Error extracting archive for {package_name} from "
        f"{temp_archive_path}: {e}"
      )
    except IOError as e:
      logging.error(
        f"File I/O error during download or extraction for {package_name}: {e}"
      )
    except Exception as e:
      logging.error(
        f"An unexpected error occurred during download or extraction for "
        f"{package_name}: {e}"
      )
    finally:
      # Clean up the downloaded archive file
      if os.path.exists(temp_archive_path):
        try:
          os.remove(temp_archive_path)
        except OSError as e:
          logging.warning(
            f"Could not remove temporary archive file {temp_archive_path}: {e}"
          )

  def download_concurrently(self, parser: Parser):
    """
    Download files concurrently from a list of urls
    """
    self.parser = parser

    download_list = [
      component for component in parser.parsed_components.values()
      if component.get(DOWNLOAD_URL_KEY, None)
    ]

    if not download_list:
      logging.info("No packages with download URLs found to download.")
      return "0 packages downloaded."

    logging.info(
      f"Attempting to download {len(download_list)} packages concurrently..."
    )

    with concurrent.futures.ThreadPoolExecutor(
        max_workers=os.cpu_count() or 4
    ) as executor:
      futures = [
        executor.submit(self.__download_package, comp)
        for comp in download_list
      ]

      for future in concurrent.futures.as_completed(futures):
        try:
          future.result()
        except Exception as e:
          logging.error(f"Error downloading package: {e}")

    logging.info(
      f"Finished concurrent download process for {len(download_list)} packages."
    )
    return f"{len(download_list)} packages downloaded."
