#[=======================================================================[
SPDX-License-Identifier: GPL-2.0-only
SPDX-FileCopyrightText: © 2021 Avinal Kumar <avinal.xlvii@gmail.com>
#]=======================================================================]

cmake_minimum_required(VERSION 3.18...3.31 FATAL_ERROR)
project(FOSSOLOGY)

# ignore Anaconda compilers, which are typically not compatible with the system
# Taken from: https://www.scivision.dev/cmake-ignore-anaconda-libs/
if(DEFINED ENV{CONDA_PREFIX})
    set(CMAKE_IGNORE_PATH $ENV{CONDA_PREFIX}/bin)
    list(APPEND CMAKE_IGNORE_PREFIX_PATH $ENV{CONDA_PREFIX})
    list(APPEND CMAKE_IGNORE_PATH $ENV{CONDA_PREFIX}/bin)
    # need CMAKE_IGNORE_PATH to ensure system env var PATH
    # doesn't interfere despite CMAKE_IGNORE_PREFIX_PATH
endif()

# set defaults for the project
include(${CMAKE_CURRENT_SOURCE_DIR}/cmake/SetDefaults.cmake)

# Check latest version info from git
message(STATUS "Current fossology version ${FO_VERSION}")
message(STATUS "Latest commit hash: ${FO_COMMIT_HASH}")
message(STATUS "Current Branch: ${FO_BRANCH}")
message(STATUS "Current Build Date: ${FO_BUILD_DATE}")
message(STATUS "Latest Commit Date: ${FO_COMMIT_DATE}")

if(TESTING)
    enable_testing()
endif()

# add source directories
add_subdirectory(src)
add_subdirectory(install)

# add packaging configurations
if(${CMAKE_VERSION} VERSION_LESS "3.19.0")
    message(AUTHOR_WARNING "Needs CMake version greater than 3.19.0 for packaging FOSSology. Skipping ...")
else()
    message(STATUS "Found CMake ${CMAKE_VERSION}. Adding FOSSology packaging configs ...")
    include(${CMAKE_CURRENT_SOURCE_DIR}/cmake/FoPackaging.cmake)
    file(MAKE_DIRECTORY ${CMAKE_BINARY_DIR}/pack)
    configure_file("${FO_DEBDIR}/changelog" "${CMAKE_BINARY_DIR}/pack/changelog.Debian"
        COPYONLY NO_SOURCE_PERMISSIONS)
    add_custom_target(changelog ALL
        COMMENT "Generating changelog for packages..."
        COMMAND ${CMAKE_COMMAND} -E tar czf changelog.Debian.gz changelog.Debian
        COMMAND ${CMAKE_COMMAND} -E tar czf README.md.gz "${CMAKE_CURRENT_SOURCE_DIR}/README.md"
        WORKING_DIRECTORY ${CMAKE_BINARY_DIR}/pack)
endif()

# uninstall target
if(NOT TARGET uninstall)
    configure_file(
        "${CMAKE_CURRENT_SOURCE_DIR}/cmake/cmake_uninstall.cmake.in"
        "${CMAKE_CURRENT_BINARY_DIR}/cmake_uninstall.cmake"
        IMMEDIATE @ONLY)

    add_custom_target(uninstall
        COMMAND ${CMAKE_COMMAND} -P ${CMAKE_CURRENT_BINARY_DIR}/cmake_uninstall.cmake)
endif()
