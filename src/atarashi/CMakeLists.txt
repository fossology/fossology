#[=======================================================================[
SPDX-License-Identifier: GPL-2.0-only
SPDX-FileCopyrightText: © 2025 Rajul Jha <rajuljha49@gmail.com>
#]=======================================================================]

cmake_minimum_required(VERSION 3.13 FATAL_ERROR)
project(atarashi LANGUAGES CXX)

include(${CMAKE_CURRENT_SOURCE_DIR}/../../cmake/SetDefaults.cmake)

find_package(jsoncpp REQUIRED
    NAMES
        jsoncpp jsoncpp_lib
)

add_subdirectory(${CMAKE_CURRENT_SOURCE_DIR}/agent)
configure_file(${FO_CMAKEDIR}/TestInstall.make.in ${CMAKE_CURRENT_BINARY_DIR}/TestInstall.make
    NEWLINE_STYLE LF @ONLY)
generate_version()

install(DIRECTORY ui
    DESTINATION ${FO_MODDIR}/${PROJECT_NAME}
    COMPONENT atarashi
    FILES_MATCHING
    PATTERN *.php
    PATTERN *.twig)

install(FILES atarashi.conf "${CMAKE_CURRENT_BINARY_DIR}/VERSION"
    DESTINATION ${FO_MODDIR}/${PROJECT_NAME}
    COMPONENT atarashi)

install(FILES ${CMAKE_CURRENT_SOURCE_DIR}/agent/atarashi.conf
    DESTINATION ${FO_MODDIR}/${PROJECT_NAME}/agent
    COMPONENT atarashi)

add_symlink()

if(TESTING)
    enable_testing()
    add_subdirectory(agent_tests)
endif()