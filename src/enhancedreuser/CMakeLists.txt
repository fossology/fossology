#[=======================================================================[
SPDX-License-Identifier: GPL-2.0-only
SPDX-FileCopyrightText: © 2026 Saksham Mishra
#]=======================================================================]

cmake_minimum_required(VERSION 3.18 FATAL_ERROR)
project(enhancedreuser LANGUAGES CXX)

include(${CMAKE_CURRENT_SOURCE_DIR}/../../cmake/SetDefaults.cmake)

add_subdirectory(${CMAKE_CURRENT_SOURCE_DIR}/agent)

generate_version()

install(DIRECTORY ui
    DESTINATION ${FO_MODDIR}/${PROJECT_NAME}
    COMPONENT enhancedreuser
    FILES_MATCHING
    PATTERN *.php)

install(FILES enhancedreuser.conf "${CMAKE_CURRENT_BINARY_DIR}/VERSION"
    DESTINATION ${FO_MODDIR}/${PROJECT_NAME}
    COMPONENT enhancedreuser)

add_symlink()

if(TESTING)
    enable_testing()
    add_subdirectory(agent_tests)
endif()
