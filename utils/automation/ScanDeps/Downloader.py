#!/usr/bin/env python3

# SPDX-FileContributor: © Rajul Jha <rajuljha49@gmail.com>

# SPDX-License-Identifier: GPL-2.0-only

import os
import re
import requests
import concurrent.futures
import zipfile
import tarfile


class Downloader:
    """
    Class for parallely downloading dependencies from download urls.
    """
    
    def __download_package(self, package_name: str, download_url: str,\
                         save_dir: str) -> str:
        response = requests.get(download_url)
        package_folder = os.path.join(save_dir, package_name)
        if not os.path.exists(package_folder):
            os.makedirs(package_folder)

        match = re.search(r'\.tar\.gz$|\.tar\.bz2$|\.tar\.xz$|\.zip$|\.whl$|\.tar$',\
                           download_url)
        file_extension = match.group(0) if match else os.path.splitext(download_url)[1]
        file_path = os.path.join(package_folder, package_name + file_extension)

        with open(file_path, 'wb') as f:
            f.write(response.content)
        
        print(f"Downloaded {package_name} to {file_path}")

        # Unpack the file based on its extension
        if file_extension == ".zip":
            with zipfile.ZipFile(file_path, 'r') as zip_ref:
                zip_ref.extractall(package_folder)
        elif file_extension in [".tar.gz", ".tgz", ".tar"]:
            with tarfile.open(file_path, 'r:*') as tar_ref:
                tar_ref.extractall(package_folder)
        else:
            print(f"Unsupported file format: {file_extension}")
            return None
        os.remove(file_path)
        print(f"Exported {package_name} to {package_folder}")
        
        return file_path

    def download_concurrently(self, download_list: list[tuple[str,str]], \
                              save_dir: str):
        """
        Download files concurrently from a list of urls
        """
        with concurrent.futures.ThreadPoolExecutor() as executor:
            futures = [
                executor.submit(self.__download_package, name, url, save_dir)
                for name, url in download_list
            ]
            
            for future in concurrent.futures.as_completed(futures):
                try:
                    future.result()
                except Exception as e:
                    print(f"Error downloading package: {e}")
        return f"Packages downloaded in the folder {save_dir}"
