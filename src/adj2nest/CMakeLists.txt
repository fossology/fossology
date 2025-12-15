#[=======================================================================[
SPDX-License-Identifier: GPL-2.0-only
SPDX-FileCopyrightText: © 2021 Avinal Kumar <avinal.xlvii@gmail.com>
#]=======================================================================]

cmake_minimum_required(VERSION 3.18 FATAL_ERROR)
project(adj2nest LANGUAGES C)

include(${CMAKE_CURRENT_SOURCE_DIR}/../../cmake/SetDefaults.cmake)

add_subdirectory(agent)

generate_version()

install(DIRECTORY ui
    DESTINATION ${FO_MODDIR}/${PROJECT_NAME}
    COMPONENT adj2nest
    FILES_MATCHING 
    PATTERN *.php)

install(FILES "${PROJECT_BINARY_DIR}/VERSION" adj2nest.conf 
    DESTINATION ${FO_MODDIR}/${PROJECT_NAME}
    COMPONENT adj2nest)

add_symlink()
