#[=======================================================================[
SPDX-License-Identifier: GPL-2.0-only
SPDX-FileCopyrightText: © 2024 Siemens AG
#]=======================================================================]

cmake_minimum_required(VERSION 3.18 FATAL_ERROR)
project(compatibility LANGUAGES CXX)

include(${CMAKE_CURRENT_SOURCE_DIR}/../../cmake/SetDefaults.cmake)

pkg_check_modules(jsoncpp REQUIRED jsoncpp)

find_package(yaml-cpp REQUIRED
    NAMES
        yaml-cpp
)

add_subdirectory(${CMAKE_CURRENT_SOURCE_DIR}/agent)
generate_version()

install(DIRECTORY ui
        DESTINATION ${FO_MODDIR}/${PROJECT_NAME}
        COMPONENT compatibility
        FILES_MATCHING
        PATTERN *.php)

install(FILES compatibility.conf "${CMAKE_CURRENT_BINARY_DIR}/VERSION"
        DESTINATION ${FO_MODDIR}/${PROJECT_NAME}
        COMPONENT compatibility)

add_symlink()

if(TESTING)
    enable_testing()
    add_subdirectory(agent_tests)
endif(TESTING)
