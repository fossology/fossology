#[=======================================================================[
SPDX-License-Identifier: GPL-2.0-only
SPDX-FileCopyrightText: © 2021 Avinal Kumar <avinal.xlvii@gmail.com>
#]=======================================================================]

cmake_minimum_required(VERSION 3.18 FATAL_ERROR)
project(genvendor)

include(${CMAKE_CURRENT_SOURCE_DIR}/../../cmake/SetDefaults.cmake)

file(COPY ../composer.json ../composer.lock ComposerInstall
    DESTINATION ${CMAKE_CURRENT_BINARY_DIR}/vendor)

set(FO_VENDOR ${CMAKE_CURRENT_BINARY_DIR}/vendor/vendor CACHE INTERNAL "vendor dir")

add_custom_target(vendor ALL
    COMMAND bash ${CMAKE_CURRENT_BINARY_DIR}/vendor/ComposerInstall ${PHPUNIT}
    WORKING_DIRECTORY "${CMAKE_CURRENT_BINARY_DIR}/vendor"
    COMMENT "Generating vendor"
    DEPENDS ${CMAKE_CURRENT_BINARY_DIR}/vendor/ComposerInstall
    BYPRODUCTS ${CMAKE_CURRENT_BINARY_DIR}/vendor/vendor)

# Make sure composer vendor is built before www
add_dependencies(www vendor)

install(DIRECTORY ${CMAKE_CURRENT_BINARY_DIR}/vendor/
    DESTINATION ${FO_MODDIR}
    COMPONENT vendor
    PATTERN "ComposerInstall" EXCLUDE)
