#[=======================================================================[
SPDX-License-Identifier: GPL-2.0-only
SPDX-FileCopyrightText: © 2025 Harshit Gandhi <gandhiharshit716@gmail.com>
#]=======================================================================]

cmake_minimum_required(VERSION 3.13 FATAL_ERROR)
project(kotoba)

include(${CMAKE_CURRENT_SOURCE_DIR}/../../cmake/SetDefaults.cmake)

# Create a dummy target for now to satisfy version generation requirements
# add_custom_target(kotoba COMMENT "Dummy kotoba target for UI-only setup")

add_subdirectory(${CMAKE_CURRENT_SOURCE_DIR}/agent)

generate_version()

install(DIRECTORY ui
    DESTINATION ${FO_MODDIR}/${PROJECT_NAME}
    COMPONENT kotoba
    FILES_MATCHING
    PATTERN *.php)

# Install kotoba main agent files
install(FILES kotoba.conf "${CMAKE_CURRENT_BINARY_DIR}/VERSION"
    DESTINATION ${FO_MODDIR}/${PROJECT_NAME}
    COMPONENT kotoba)

# Install kotobabulk as separate module
generate_version(kotobabulk "VERSION-kotobabulk")
install(FILES kotobabulk.conf
    DESTINATION ${FO_MODDIR}/kotobabulk
    COMPONENT kotobabulk)

install(FILES "${CMAKE_CURRENT_BINARY_DIR}/VERSION-kotobabulk"
    DESTINATION ${FO_MODDIR}/kotobabulk
    RENAME "VERSION"
    COMPONENT kotobabulk)

# Install kotobabulk UI files
install(FILES ui/agent-kotoba-bulk.php
    DESTINATION ${FO_MODDIR}/kotobabulk/ui
    COMPONENT kotobabulk)

add_symlink()
add_symlink(kotobabulk)

if(TESTING)
    enable_testing()
    # add_subdirectory(agent_tests)
endif()
