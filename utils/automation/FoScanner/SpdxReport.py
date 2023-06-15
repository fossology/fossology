#!/usr/bin/env python3

# SPDX-FileCopyrightText: © 2023 Siemens AG
# SPDX-FileContributor: Gaurav Mishra <mishra.gaurav@siemens.com>
#
# SPDX-License-Identifier: GPL-2.0-only

import hashlib
import logging
from datetime import datetime
from typing import List, Set, Dict, Tuple

from license_expression import get_spdx_licensing
from spdx_tools.spdx.model import (
  Actor,
  ActorType,
  Checksum,
  ChecksumAlgorithm,
  CreationInfo,
  Document,
  File,
  FileType,
  Package,
  PackageVerificationCode,
  Relationship,
  RelationshipType,
  SpdxNoAssertion
)
from spdx_tools.spdx.validation.document_validator import \
  validate_full_spdx_document
from spdx_tools.spdx.validation.validation_message import ValidationMessage
from spdx_tools.spdx.writer.write_anything import write_file

from .ApiConfig import ApiConfig
from .CliOptions import CliOptions
from .Scanners import ScanResult, Scanners


class SpdxReport:
  """
  Handle SPDX reports.

  :ivar cli_options: CliOptions object
  :ivar report_files: Dictionary of SPDX files with SPDX ID as key
  :ivar license_package_set: Set of licenses found in package
  :ivar creation_info: Report creation info
  :ivar document: Report document
  :ivar package: Report package
  """

  def __init__(self, cli_options: CliOptions, api_config: ApiConfig):
    """
    :param cli_options: CliOptions to use
    :param api_config:  ApiConfig to use
    """
    self.cli_options = cli_options
    self.report_files: Dict[str, File] = {}
    self.license_package_set: Set[str] = set()
    self.creation_info: CreationInfo = CreationInfo(
      spdx_version="SPDX-2.3",
      spdx_id="SPDXRef-DOCUMENT",
      name="FOSSology CI Report",
      data_license="CC0-1.0",
      document_namespace="https://fossology.org",
      creators=[Actor(ActorType.ORGANIZATION, "FOSSology",
                      "fossology@fossology.org")],
      created=datetime.now(),
    )
    self.document: Document = Document(self.creation_info)

    self.package: Package = Package(
      name=api_config.project_name,
      spdx_id="SPDXRef-Package",
      files_analyzed=True,
      download_location=SpdxNoAssertion(),
      release_date=datetime.now(),
    )
    if api_config.project_desc is not None:
      self.package.description = api_config.project_desc
    if api_config.project_orig is not None and api_config.project_orig != "":
      self.package.originator = Actor(ActorType.ORGANIZATION,
                                      api_config.project_orig)
    else:
      self.package.originator = SpdxNoAssertion()
    if api_config.project_url is not None and api_config.project_url != "":
      self.package.download_location = api_config.project_url
    else:
      self.package.download_location = SpdxNoAssertion()

    self.document.packages = [self.package]

    describes_relationship = Relationship("SPDXRef-DOCUMENT",
                                          RelationshipType.DESCRIBES,
                                          "SPDXRef-Package")
    self.document.relationships = [describes_relationship]

  def add_license_file(self, scan_result: ScanResult):
    """
    Add scan result from license scanner to report.

    :param scan_result: Scan result from license scanner.
    """
    all_allowed_licenses = all([lic in self.cli_options.allowlist['licenses']
                                for lic in scan_result.result]) is True
    spdx_id = self.__get_file_spdx_id(scan_result)

    if spdx_id in self.report_files:
      file = self.report_files[spdx_id]
    else:
      file = self.__get_new_spdx_file(scan_result, spdx_id)

    file.file_types = [FileType.SOURCE]
    if all_allowed_licenses:
      file.license_concluded = get_spdx_licensing().parse(" AND ".join([
        lic for lic in scan_result.result
      ]))
    else:
      file.license_concluded = SpdxNoAssertion()
    file.license_info_in_file = [
      get_spdx_licensing().parse(lic) for lic in scan_result.result
    ]
    self.report_files[spdx_id] = file
    self.license_package_set.update(scan_result.result)

  def __get_new_spdx_file(self, scan_result: ScanResult, spdx_id: str) -> File:
    """
    Create a new SPDX File for given scan result and populate common fields.

    :param scan_result: Scan result from scanner.
    :param spdx_id: SPDX ID to use for file.
    :return: New SPDX File
    """
    md5_hash, sha1_hash, sha256_hash = self.__get_file_info(scan_result)
    file = File(
      name=scan_result.file,
      spdx_id=spdx_id,
      checksums=[
        Checksum(ChecksumAlgorithm.MD5, md5_hash.hexdigest()),
        Checksum(ChecksumAlgorithm.SHA1, sha1_hash.hexdigest()),
        Checksum(ChecksumAlgorithm.SHA256, sha256_hash.hexdigest()),
      ],
      file_types=[FileType.SOURCE],
      license_concluded=SpdxNoAssertion()
    )
    return file

  def add_copyright_file(self, copyright_result: ScanResult):
    """
    Add scan result from copyright agent. If the file does not exist, creates a
    new one.

    :param copyright_result: Scan result from copyright scanner.
    """
    spdx_id = self.__get_file_spdx_id(copyright_result)
    if spdx_id in self.report_files:
      file = self.report_files[spdx_id]
    else:
      file = self.__get_new_spdx_file(copyright_result, spdx_id)
    file.copyright_text = "\n".join([
      cpy for cpy in copyright_result.result
    ])

  @staticmethod
  def __get_file_info(scan_result: ScanResult) -> Tuple:
    """
    Get different hash for the file in scan result.

    :param scan_result: Scan result from scanners.
    :return: Tuple of md5, sha1 and sha256 checksums.
    """
    md5_hash = hashlib.md5()
    sha1_hash = hashlib.sha1()
    sha256_hash = hashlib.sha256()
    with open(scan_result.path, "rb") as f:
      for byte_block in iter(lambda: f.read(4096), b""):
        md5_hash.update(byte_block)
        sha1_hash.update(byte_block)
        sha256_hash.update(byte_block)
    return md5_hash, sha1_hash, sha256_hash

  @staticmethod
  def __get_file_spdx_id(scan_result: ScanResult) -> str:
    """
    Generate SPDX ID for file in scan result.

    :param scan_result: Scan result from scanner.
    :return: SPDX ID for the file.
    """
    spdx_id = "SPDXRef-File" + hashlib.md5(
      scan_result.file.encode()).hexdigest()
    return spdx_id

  def write_report(self, file_name: str):
    """
    Validate the document and write the SPDX file.

    :param file_name: Location to store the report.
    """
    validation_messages: List[ValidationMessage] = \
      validate_full_spdx_document(self.document)
    for message in validation_messages:
      logging.warning(message.validation_message)
      logging.warning(message.context)
    assert validation_messages == []
    write_file(self.document, file_name)

  def finalize_document(self):
    """
    Finalize the document by setting relations between packages and files.
    At the same time, add all the licenses from files to the package and
    calculate the verification code, without the excluded files.
    """
    for spdx_id, file in self.report_files.items():
      contains_relationship = Relationship("SPDXRef-Package",
                                           RelationshipType.CONTAINS, spdx_id)
      self.document.relationships += [contains_relationship]
      self.document.files += [file]

    self.package.license_info_from_files = [
      get_spdx_licensing().parse(lic) for lic in self.license_package_set
    ]

    all_allowed_licenses = all([lic in self.cli_options.allowlist['licenses']
                                for lic in self.license_package_set]) is True
    if all_allowed_licenses:
      self.package.license_concluded = get_spdx_licensing().parse(" AND ".join([
        lic for lic in self.license_package_set
      ]))
    else:
      self.package.license_concluded = SpdxNoAssertion()
    templist = []
    scanner_obj = Scanners(self.cli_options)
    excluded_files: list[str] = []
    for f in self.document.files:
      if scanner_obj.is_excluded_path(f.name):
        excluded_files.append(f.name)
      else:
        for sum in f.checksums:
          if sum.algorithm == ChecksumAlgorithm.SHA1:
            templist.append(sum.value)
            break
    templist.sort()
    verificationcode = hashlib.sha1("".join(templist).encode()).hexdigest()

    self.package.verification_code = PackageVerificationCode(
      value=verificationcode, excluded_files=excluded_files
    )

  def add_license_results(self, scan_results: List[ScanResult]):
    """
    Helper function to add scan results to the report from license scanners.

    :param scan_results: List of scan results from the license scanners.
    """
    for result in scan_results:
      self.add_license_file(result)

  def add_copyright_results(self, copyright_results: List[ScanResult]):
    """
    Helper function to add scan results to the report from copyright scanner.

    :param copyright_results: List of scan results from the copyright scanner.
    """
    for result in copyright_results:
      self.add_copyright_file(result)
