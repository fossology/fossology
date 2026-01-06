#[=======================================================================[
SPDX-License-Identifier: GPL-2.0-only
SPDX-FileCopyrightText: © 2022 Gaurav Mishra <mishra.gaurav@siemens.com>
#]=======================================================================]

cmake_minimum_required(VERSION 3.18 FATAL_ERROR)
project(scancode)

include(${CMAKE_CURRENT_SOURCE_DIR}/../../cmake/SetDefaults.cmake)

pkg_check_modules(jsoncpp REQUIRED jsoncpp)

add_subdirectory(${CMAKE_CURRENT_SOURCE_DIR}/agent)
generate_version()

install(DIRECTORY ui
    DESTINATION ${FO_MODDIR}/${PROJECT_NAME}
    COMPONENT scancode
    FILES_MATCHING
    PATTERN *.php
    PATTERN *.twig)

install(FILES scancode.conf "${CMAKE_CURRENT_BINARY_DIR}/VERSION"
    DESTINATION ${FO_MODDIR}/${PROJECT_NAME}
    COMPONENT scancode)

add_symlink()

if(TESTING)
    enable_testing()
    add_subdirectory(agent_tests)
endif()

