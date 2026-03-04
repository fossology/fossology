#[============================================================================[
SPDX-License-Identifier: GPL-2.0-only
SPDX-FileCopyrightText: © 2023 Sushant Kumar <sushantmishra02102002@gmail.com>
#]============================================================================]

cmake_minimum_required(VERSION 3.18 FATAL_ERROR)
project(cyclonedx)

include(${CMAKE_CURRENT_SOURCE_DIR}/../../cmake/SetDefaults.cmake)

add_subdirectory(${CMAKE_CURRENT_SOURCE_DIR}/agent)

generate_version()

install(DIRECTORY ui
    DESTINATION ${FO_MODDIR}/${PROJECT_NAME}
    COMPONENT cyclonedx
    FILES_MATCHING
    PATTERN *.php)

install(FILES cyclonedx.conf "${CMAKE_CURRENT_BINARY_DIR}/VERSION"
    DESTINATION ${FO_MODDIR}/${PROJECT_NAME}
    COMPONENT cyclonedx)

add_symlink()
