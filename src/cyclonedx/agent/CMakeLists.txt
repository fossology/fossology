#[============================================================================[
SPDX-License-Identifier: GPL-2.0-only
SPDX-FileCopyrightText: © 2023 Sushant Kumar <sushantmishra02102002@gmail.com>
#]============================================================================]

generate_version_php()

install(DIRECTORY ./ ${CMAKE_CURRENT_BINARY_DIR}/gen/
    DESTINATION ${FO_MODDIR}/${PROJECT_NAME}/agent
    COMPONENT cyclonedx
    FILES_MATCHING
    PATTERN *.php)

add_symlink(${PROJECT_NAME} ${FO_LIBEXECDIR}/fo_wrapper ${FO_MODDIR}/${PROJECT_NAME}/agent)
