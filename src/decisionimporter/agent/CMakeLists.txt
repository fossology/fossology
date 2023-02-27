#[=======================================================================[
SPDX-License-Identifier: GPL-2.0-only
SPDX-FileCopyrightText: © 2022 Siemens AG
SPDX-FileContributor: Gaurav Mishra <mishra.gaurav@siemens.com>
#]=======================================================================]

generate_version_php()

install(DIRECTORY ${CMAKE_CURRENT_LIST_DIR}/
    DESTINATION ${FO_MODDIR}/${PROJECT_NAME}/agent
    COMPONENT decisionimporter
    FILES_MATCHING
    PATTERN *.php
    PATTERN version.php EXCLUDE
)

install(DIRECTORY ${CMAKE_CURRENT_BINARY_DIR}/gen/
    DESTINATION ${FO_MODDIR}/${PROJECT_NAME}/agent
    COMPONENT decisionimporter
    FILES_MATCHING
    PATTERN *.php
)

add_symlink(${PROJECT_NAME} ${FO_LIBEXECDIR}/fo_wrapper ${FO_MODDIR}/${PROJECT_NAME}/agent)
