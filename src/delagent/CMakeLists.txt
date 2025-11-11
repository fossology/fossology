#[=======================================================================[
SPDX-License-Identifier: GPL-2.0-only
SPDX-FileCopyrightText: © 2021 Avinal Kumar <avinal.xlvii@gmail.com>
#]=======================================================================]

cmake_minimum_required(VERSION 3.18 FATAL_ERROR)
project(delagent LANGUAGES C)

include(${CMAKE_CURRENT_SOURCE_DIR}/../../cmake/SetDefaults.cmake)

add_subdirectory(${CMAKE_CURRENT_SOURCE_DIR}/agent)

generate_version()

install(DIRECTORY ui 
    DESTINATION ${FO_MODDIR}/${PROJECT_NAME}
    COMPONENT delagent
    FILES_MATCHING
    PATTERN *.php
    PATTERN *.twig)

install(FILES delagent.conf mod_deps "${CMAKE_CURRENT_BINARY_DIR}/VERSION"
    DESTINATION ${FO_MODDIR}/${PROJECT_NAME}
    COMPONENT delagent)

add_symlink()
add_symlink(${PROJECT_NAME}-page ${FO_MODDIR}/${PROJECT_NAME}/ui/Page)

if(TESTING)
    set(VERSIONFILE "VERSION")
    configure_file(${FO_CMAKEDIR}/TestInstall.make.in
        ${CMAKE_CURRENT_BINARY_DIR}/TestInstall.make NEWLINE_STYLE LF @ONLY)
    enable_testing()
    add_subdirectory(agent_tests)
endif(TESTING)
