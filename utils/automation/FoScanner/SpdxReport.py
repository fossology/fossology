#!/usr/bin/env python3

# SPDX-FileCopyrightText: © 2023,2025 Siemens AG
# SPDX-FileContributor: Gaurav Mishra <mishra.gaurav@siemens.com>
#
# SPDX-License-Identifier: GPL-2.0-only

import hashlib
import logging
import re
from datetime import datetime

from license_expression import (
  get_spdx_licensing, LicenseExpression, combine_expressions
)
from spdx_tools.spdx.model import (
  Actor, ActorType, Checksum, ChecksumAlgorithm, CreationInfo, Document, File,
  FileType, Package, PackageVerificationCode, Relationship, RelationshipType,
  SpdxNoAssertion, ExternalPackageRef, ExternalPackageRefCategory, SpdxNone,
  ExtractedLicensingInfo
)
from spdx_tools.spdx.validation.document_validator import \
  validate_full_spdx_document
from spdx_tools.spdx.validation.validation_message import ValidationMessage
from spdx_tools.spdx.writer.write_anything import write_file

from .CliOptions import CliOptions
from .Scanners import Scanners, ScanResultList


class SpdxReport:
  """
  Handle SPDX reports.

  :ivar cli_options: CliOptions object
  :ivar report_files: Dictionary of SPDX files with SPDX ID as key
  :ivar license_package_set: Set of licenses found in package
  :ivar creation_info: Report creation info
  :ivar document: Report document
  :ivar package: Report package
  :ivar scanner: Scanners object
  """

  def __init__(self, cli_options: CliOptions, scanner: Scanners):
    """
    :param cli_options: CliOptions to use
    :param scanner:     Scanners to use
    """
    self.cli_options = cli_options
    self._allowed_licenses_set = set(
      self.cli_options.allowlist.get('licenses', [])
    )
    self._license_cache: dict[str, LicenseExpression] = {}
    self._spdx_lic_cache = get_spdx_licensing()
    self.scanner = scanner
    self.report_files: dict[str, File] = {}
    self.license_package_set: set[str] = set()
    self.package_verification_set: dict[str, dict[str, list[str]]] = {}
    self.creation_info: CreationInfo = CreationInfo(
      spdx_version="SPDX-2.3",
      spdx_id="SPDXRef-DOCUMENT",
      name="FOSSology CI Report",
      data_license="CC0-1.0",
      document_namespace="https://fossology.org",
      creators=[
        Actor(
          ActorType.ORGANIZATION, "FOSSology",
          "fossology@fossology.org"
        )
      ], created=datetime.now(),
    )
    self.document: Document = Document(self.creation_info)

    parent_package = self.scanner.get_scan_packages().parent_package
    project_name = parent_package.get('name', '').strip()
    if not project_name:
      project_name = self.cli_options.parser.root_component_name
    if not project_name:
      project_name = "NA"

    self.package: Package = Package(
      name=project_name,
      spdx_id="SPDXRef-Package",
      files_analyzed=True,
      download_location=SpdxNoAssertion(),
      release_date=datetime.now(),
    )
    if parent_package.get('description') is not None:
      self.package.description = parent_package['description']

    author = parent_package.get('author')
    if author and author != "":
      self.package.originator = Actor(
        ActorType.ORGANIZATION,
        author
      )
    else:
      self.package.originator = SpdxNoAssertion()

    url = parent_package.get('url')
    if url and url != "":
      self.package.download_location = url
    else:
      self.package.download_location = SpdxNoAssertion()

    self.document.packages = [self.package]
    self.dependent_packages: dict[str, Package] = {}
    self.extracted_licenses: dict[str, ExtractedLicensingInfo] = {}

  def __get_license_or_ref(self, lic: str) -> LicenseExpression:
    if lic in self._license_cache:
      return self._license_cache[lic]
    license_spdx = lic
    if self._spdx_lic_cache.validate(lic).invalid_symbols:
      license_spdx = re.sub(
        r'[^\da-zA-Z.\-_]', '-',
        f"LicenseRef-fossology-{lic}"
      )
      if license_spdx not in self.extracted_licenses:
        self.extracted_licenses[license_spdx] = ExtractedLicensingInfo(
          license_id=license_spdx,
          license_name=lic,
          extracted_text=f"The license text for {license_spdx} has to be "
                         "entered."
        )
    lic_expression = self._spdx_lic_cache.parse(license_spdx)
    self._license_cache[lic] = lic_expression
    return lic_expression

  def __add_license_file(self, package: Package, scan_result: ScanResultList):
    """
    Add scan result from license scanner to report.

    :param package: Package to which the file belongs.
    :param scan_result: Scan result from license scanner.
    """
    raw_licenses_strings = [lic['license'] for lic in scan_result.result]
    parsed_expressions_set = {self.__get_license_or_ref(lic_str) for lic_str in
                              raw_licenses_strings}
    parsed_expressions_list = list(parsed_expressions_set)

    all_allowed_licenses = all(
      lic_str in self._allowed_licenses_set for lic_str in raw_licenses_strings
    )

    file = self.__get_spdx_file(scan_result, package)

    if all_allowed_licenses:
      file.license_concluded = combine_expressions(
        expressions=parsed_expressions_list, relation='AND', unique=False
      )
    else:
      file.license_concluded = SpdxNoAssertion()

    file.license_info_in_file = parsed_expressions_list

    # Update licenses found in the files of the package
    package.license_info_from_files = list(
      set(package.license_info_from_files) | parsed_expressions_set
    )

    # Update package.license_concluded
    if file.license_concluded != SpdxNoAssertion():
      if package.license_concluded in (SpdxNoAssertion(), SpdxNone()):
        package.license_concluded = file.license_concluded
      else:
        package.license_concluded = (
            package.license_concluded & file.license_concluded).simplify()

  def __get_spdx_file(
      self, scan_result: ScanResultList, package: Package
  ) -> File:
    """
    Create a new SPDX File for given scan result and populate common fields.

    :param scan_result: Scan result from scanner.
    :param package: Package to which the file belongs.
    :return: New SPDX File
    """
    md5_hash, sha1_hash, sha256_hash = self.__get_file_info(scan_result)
    file_spdx_id = self.__get_file_spdx_id(sha256_hash, package.name)
    if file_spdx_id not in self.report_files:
      spdx_file = File(
        name=scan_result.file,
        spdx_id=file_spdx_id,
        checksums=[
          Checksum(ChecksumAlgorithm.MD5, md5_hash),
          Checksum(ChecksumAlgorithm.SHA1, sha1_hash),
          Checksum(ChecksumAlgorithm.SHA256, sha256_hash),
        ],
        file_types=[FileType.SOURCE],
        license_concluded=SpdxNoAssertion()
      )
      self.report_files[file_spdx_id] = spdx_file
      contains_relationship = Relationship(
        package.spdx_id,
        RelationshipType.CONTAINS,
        file_spdx_id
      )
      self.document.relationships.append(contains_relationship)

      pkg_verification_data = self.package_verification_set.setdefault(
        package.spdx_id, {
          'checksums': [], 'excluded_files': []
        }
      )

      if self.scanner.is_excluded_path(spdx_file.name):
        pkg_verification_data['excluded_files'].append(spdx_file.name)
      else:
        pkg_verification_data['checksums'].append(sha1_hash)

    return self.report_files[file_spdx_id]

  def __add_copyright_file(
      self, package: Package, copyright_result: ScanResultList
  ):
    """
    Add scan result from copyright agent. If the file does not exist, creates a
    new one.

    :param copyright_result: Scan result from copyright scanner.
    """
    file = self.__get_spdx_file(copyright_result, package)
    file.copyright_text = "\n".join(
      [
        cpy.get('content', '') for cpy in copyright_result.result
      ]
    )

  @staticmethod
  def __get_file_info(scan_result: ScanResultList) -> tuple[str, str, str]:
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
    return md5_hash.hexdigest(), sha1_hash.hexdigest(), sha256_hash.hexdigest()

  @staticmethod
  def __get_file_spdx_id(sha256_hash: str, pkg_name: str) -> str:
    """
    Generate SPDX ID for file in scan result.

    :param sha256_hash: SHA 256 checksum of the file
    :param pkg_name: Package to which the file belongs.
    :return: SPDX ID for the file.
    """
    return f"SPDXRef-File-{pkg_name}-{sha256_hash}"

  @staticmethod
  def __get_package_spdx_id(component: dict) -> str:
    """
    Generate SPDX ID for a package/component.

    :param component: Package/component to get SPDX ID for.
    :return: SPDX ID for the package.
    """
    pkg_name = component.get('name', '')
    pkg_version = component.get('version', '')
    return "SPDXRef-Package-" + hashlib.md5(
      f"{pkg_name}_{pkg_version}".encode('utf-8', errors='ignore')
    ).hexdigest()

  def write_report(self, file_name: str):
    """
    Validate the document and write the SPDX file.

    :param file_name: Location to store the report.
    """
    validation_messages: list[ValidationMessage] = validate_full_spdx_document(
      self.document
    )

    if validation_messages:
      for message in validation_messages:
        logging.warning(
          f"SPDX Validation Warning: {message.validation_message}\n"
          f"Context: {message.context}"
        )
      raise RuntimeError(
        "SPDX document validation failed. See logs for details."
      )
    else:
      logging.info("SPDX document validated successfully.")

    # validate=False here as we validated above
    write_file(
      self.document, file_name, validate=False
    )

  def finalize_document(self):
    """
    Finalize the document by setting relations between packages and files.
    At the same time, add all the licenses from files to the package and
    calculate the verification code, without the excluded files.
    """
    self.__create_packages()
    self.__create_license_files()
    self.__create_copyright_files()
    self.__add_files_to_document()
    self.__add_extracted_licenses()
    self.__update_package_verification_code()

  def __create_packages(self) -> None:
    parent_name = self.scanner.get_scan_packages().parent_package.get(
      'name', ''
      )
    if parent_name:
      self.package.spdx_id = re.sub(
        r'[^A-Za-z0-9\-_.]', '-',
        f"SPDXRef-Package-{parent_name}"
      )
    describes_relationship = Relationship(
      "SPDXRef-DOCUMENT",
      RelationshipType.DESCRIBES,
      self.package.spdx_id
    )
    self.document.relationships.append(describes_relationship)

    for purl, component in (
        self.scanner.get_scan_packages().dependencies.items()
    ):
      package = self.__get_package_for_component(component)
      self.document.packages.append(package)
      depends_on_relationship = Relationship(
        self.package.spdx_id,
        RelationshipType.DEPENDS_ON,
        package.spdx_id
      )
      self.document.relationships.append(depends_on_relationship)

  def __get_package_for_component(self, component: dict) -> Package:
    """
    For a given component, create a package and add it to the list.

    :param component: Component to create package for.
    :return: Create or get existing package.
    """
    pkg_spdx_id = self.__get_package_spdx_id(component)
    if pkg_spdx_id not in self.dependent_packages:
      self.dependent_packages[pkg_spdx_id] = Package(
        spdx_id=pkg_spdx_id,
        name=component.get('name', 'UNKNOWN'),
        version=component.get('version', 'UNKNOWN'),
        download_location=component.get(
          'fossology_download_url', SpdxNoAssertion()
        ),
        license_info_from_files=[],
        license_concluded=SpdxNone(),
        files_analyzed=True
      )
      purl_ref = ExternalPackageRef(
        category=ExternalPackageRefCategory.PACKAGE_MANAGER,
        reference_type='purl',
        locator=component.get('purl')
      )
      self.dependent_packages[pkg_spdx_id].external_references.append(purl_ref)

      vcs_url = component.get('vcs_url')
      if vcs_url:
        vcs_ref = ExternalPackageRef(
          category=ExternalPackageRefCategory.OTHER, reference_type='vcs',
          locator=vcs_url
        )
        self.dependent_packages[pkg_spdx_id].external_references.append(vcs_ref)

      homepage_url = component.get('homepage_url')
      if homepage_url:
        homepage_ref = ExternalPackageRef(
          category=ExternalPackageRefCategory.OTHER, reference_type='homepage',
          locator=homepage_url
        )
        self.dependent_packages[pkg_spdx_id].external_references.append(
          homepage_ref
        )
    return self.dependent_packages[pkg_spdx_id]

  def __create_license_files(self) -> None:
    self.__create_license_file_from_component(
      self.scanner.get_scan_packages().parent_package, self.package
    )
    for component in self.scanner.get_scan_packages().dependencies.values():
      self.__create_license_file_from_component(
        component, self.__get_package_for_component(
          component
        )
      )

  def __create_copyright_files(self) -> None:
    self.__create_copyright_file_from_component(
      self.scanner.get_scan_packages().parent_package, self.package
    )
    for component in self.scanner.get_scan_packages().dependencies.values():
      self.__create_copyright_file_from_component(
        component, self.__get_package_for_component(
          component
        )
      )

  def __create_license_file_from_component(
      self, component: dict, package: Package
  ) -> None:
    for result in component.get('SCANNER_RESULTS', []):
      self.__add_license_file(package, result)

  def __create_copyright_file_from_component(
      self, component: dict, package: Package
  ) -> None:
    for result in component.get('COPYRIGHT_RESULT', []):
      self.__add_copyright_file(package, result)

  def __add_files_to_document(self) -> None:
    self.document.files = list(self.report_files.values())

  def __add_extracted_licenses(self) -> None:
    self.document.extracted_licensing_info = list(
      self.extracted_licenses.values()
    )

  def __update_package_verification_code(self) -> None:
    for package in self.document.packages:
      code = self.__calculate_verification_code(package.spdx_id)
      if code is not None:
        package.verification_code = code

  def __calculate_verification_code(
      self, package_spdx_id: str
  ) -> PackageVerificationCode | None:
    """
    Calculate package verification code for the list of checksums and return it.

    :param package_spdx_id: Package SPDX ID to calculate the verification
    code for.
    :return: Package Verification Code based on SPDX specification.
    """
    pkg_data = self.package_verification_set.get(package_spdx_id)
    if not pkg_data:
      return None

    checksums = pkg_data.get('checksums', [])
    excluded_files = pkg_data.get('excluded_files', [])

    checksums.sort()

    verification_code = hashlib.sha1(
      "".join(checksums).encode('utf-8', errors='ignore')
    ).hexdigest()

    return PackageVerificationCode(
      value=verification_code,
      excluded_files=excluded_files
    )
