#[=======================================================================[
SPDX-License-Identifier: GPL-2.0-only
SPDX-FileCopyrightText: © 2026 Harshit Gandhi <gandhiharshit716@gmail.com>
#]=======================================================================]

generate_version_php()

install(DIRECTORY ./ ${CMAKE_CURRENT_BINARY_DIR}/gen/
    DESTINATION ${FO_MODDIR}/${PROJECT_NAME}/agent
    COMPONENT reportaggregator
    FILES_MATCHING
    PATTERN *.php
    PATTERN *.py)

add_symlink(${PROJECT_NAME} ${FO_LIBEXECDIR}/fo_wrapper ${FO_MODDIR}/${PROJECT_NAME}/agent)
