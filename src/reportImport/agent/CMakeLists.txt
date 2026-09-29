#[=======================================================================[
SPDX-License-Identifier: GPL-2.0-only
SPDX-FileCopyrightText: © 2021 Avinal Kumar <avinal.xlvii@gmail.com>
#]=======================================================================]

generate_version_php()

set(php_scripts
  ReportImportAgent.php
  ImportSource.php
  SpdxTwoImportSource.php
  SpdxThreeImportSource.php
  XmlImportSource.php
  CycloneDxImportSource.php
  ReportImportConfiguration.php
  ReportImportData.php
  ReportImportDataItem.php
  ReportImportSink.php
  ReportImportHelper.php)

install(DIRECTORY ./ ${CMAKE_CURRENT_BINARY_DIR}/gen/
    DESTINATION ${FO_MODDIR}/${PROJECT_NAME}/agent
    COMPONENT reportImport
    FILES_MATCHING
    PATTERN *.php)

add_symlink(${PROJECT_NAME} ${FO_LIBEXECDIR}/fo_wrapper ${FO_MODDIR}/${PROJECT_NAME}/agent)
