#[=======================================================================[
SPDX-License-Identifier: GPL-2.0-only
SPDX-FileCopyrightText: © 2022 Siemens AG
SPDX-FileContributor: Gaurav Mishra <mishra.gaurav@siemens.com>
#]=======================================================================]

cmake_minimum_required(VERSION 3.18 FATAL_ERROR)
project(decisionimporter)

include(${CMAKE_CURRENT_SOURCE_DIR}/../../cmake/SetDefaults.cmake)

add_subdirectory(${CMAKE_CURRENT_SOURCE_DIR}/agent)
generate_version()

install(DIRECTORY ui
    DESTINATION ${FO_MODDIR}/${PROJECT_NAME}
    COMPONENT decisionimporter
    FILES_MATCHING
    PATTERN *.php
    PATTERN *.twig)

install(FILES decisionimporter.conf "${CMAKE_CURRENT_BINARY_DIR}/VERSION"
    DESTINATION ${FO_MODDIR}/${PROJECT_NAME}
    COMPONENT decisionimporter)

add_symlink()
