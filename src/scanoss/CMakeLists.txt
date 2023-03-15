#[=======================================================================[
SPDX-License-Identifier: GPL-2.0-only
SPDX-FileCopyrightText: © 2023 SCANOSS.COM
#]=======================================================================]

cmake_minimum_required(VERSION 3.13 FATAL_ERROR)
project(scanoss LANGUAGES C)

include(${CMAKE_CURRENT_SOURCE_DIR}/../../cmake/SetDefaults.cmake)

add_subdirectory(${CMAKE_CURRENT_SOURCE_DIR}/agent)

generate_version()

install(DIRECTORY ui 
    DESTINATION ${FO_MODDIR}/${PROJECT_NAME}
    COMPONENT scanoss
    FILES_MATCHING
    PATTERN *.php
    PATTERN *.twig)

install(FILES scanoss.conf "${CMAKE_CURRENT_BINARY_DIR}/VERSION"
    DESTINATION ${FO_MODDIR}/${PROJECT_NAME}
    COMPONENT scanoss)
    
add_symlink()
