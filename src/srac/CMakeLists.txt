#[============================================================================[
SPDX-License-Identifier: GPL-2.0-only
SPDX-FileCopyrightText: © 2026 Fossology contributors
#]============================================================================]

cmake_minimum_required(VERSION 3.18 FATAL_ERROR)
project(srac)

include(${CMAKE_CURRENT_SOURCE_DIR}/../../cmake/SetDefaults.cmake)

add_subdirectory(${CMAKE_CURRENT_SOURCE_DIR}/agent)

if(TESTING)
    add_subdirectory(${CMAKE_CURRENT_SOURCE_DIR}/agent_tests)
endif()

generate_version()

install(DIRECTORY ui
    DESTINATION ${FO_MODDIR}/${PROJECT_NAME}
    COMPONENT srac
    FILES_MATCHING
    PATTERN *.php)

install(FILES srac.conf "${CMAKE_CURRENT_BINARY_DIR}/VERSION"
    DESTINATION ${FO_MODDIR}/${PROJECT_NAME}
    COMPONENT srac)

add_symlink()
