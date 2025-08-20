#[=======================================================================[
SPDX-License-Identifier: GPL-2.0-only
SPDX-FileCopyrightText: © 2025 Harshit Gandhi <gandhiharshit716@gmail.com>
#]=======================================================================]

cmake_minimum_required(VERSION 3.13 FATAL_ERROR)
project(kotoba)

include(${CMAKE_CURRENT_SOURCE_DIR}/../../cmake/SetDefaults.cmake)

# Create a dummy target for now to satisfy version generation requirements
add_custom_target(kotoba COMMENT "Dummy kotoba target for UI-only setup")

generate_version()

install(DIRECTORY ui
    DESTINATION ${FO_MODDIR}/${PROJECT_NAME}
    COMPONENT kotoba
    FILES_MATCHING
    PATTERN *.php)

install(FILES kotoba.conf "${CMAKE_CURRENT_BINARY_DIR}/VERSION"
    DESTINATION ${FO_MODDIR}/${PROJECT_NAME}
    COMPONENT kotoba)

add_symlink()

if(TESTING)
    enable_testing()
    # add_subdirectory(agent_tests)
endif()
