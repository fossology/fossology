#[=======================================================================
# SPDX-License-Identifier: GPL-2.0-only
# SPDX-FileCopyrightText: © Fossology contributors
#]=======================================================================]

cmake_minimum_required(VERSION 3.18 FATAL_ERROR)
project(ossdetect LANGUAGES C CXX)

include(${CMAKE_CURRENT_SOURCE_DIR}/../../cmake/SetDefaults.cmake)

add_subdirectory(${CMAKE_CURRENT_SOURCE_DIR}/agent)

generate_version()

install(DIRECTORY ui 
    DESTINATION ${FO_MODDIR}/${PROJECT_NAME}
    COMPONENT ossdetect
    FILES_MATCHING
    PATTERN *.php
    PATTERN *.css)

install(FILES ossdetect.conf "${CMAKE_CURRENT_BINARY_DIR}/VERSION"
    DESTINATION ${FO_MODDIR}/${PROJECT_NAME}
    COMPONENT ossdetect)
    
add_symlink()
