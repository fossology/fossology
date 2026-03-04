#[=======================================================================[
SPDX-License-Identifier: GPL-2.0-only
SPDX-FileCopyrightText: © 2021 Avinal Kumar <avinal.xlvii@gmail.com>
#]=======================================================================]

cmake_minimum_required(VERSION 3.18 FATAL_ERROR)
project(libphp)

include(${CMAKE_CURRENT_SOURCE_DIR}/../../../cmake/SetDefaults.cmake)

foreach(PHP_GEN bootstrap.php services.xml)
    add_custom_target(${PHP_GEN} ALL
        COMMAND ${CMAKE_COMMAND}
        -DIN_FILE_NAME="${PHP_GEN}.in"
        -DOUT_FILE_NAME="${PHP_GEN}"
        -DINPUT_FILE_DIR="${CMAKE_CURRENT_SOURCE_DIR}"
        -DOUTPUT_FILE_DIR="${CMAKE_CURRENT_BINARY_DIR}/gen"
        -DFO_SYSCONFDIR="${FO_SYSCONFDIR}"
        -DFO_TWIG_CACHE="${FO_TWIG_CACHE}"
        -P ${FO_CMAKEDIR}/FoVersionFile.cmake
        COMMAND ln -sf "${CMAKE_CURRENT_BINARY_DIR}/gen/${PHP_GEN}" "${CMAKE_CURRENT_SOURCE_DIR}"
        COMMENT "Generating ${PHP_GEN}"
        DEPENDS "${CMAKE_CURRENT_SOURCE_DIR}/${PHP_GEN}.in"
        BYPRODUCTS "${CMAKE_CURRENT_BINARY_DIR}/gen/${PHP_GEN}")
endforeach()

install(DIRECTORY ./
    DESTINATION ${FO_MODDIR}/lib/php
    COMPONENT lib
    FILES_MATCHING
    PATTERN *.php
    PATTERN *.twig
    PATTERN *.xml
    REGEX "\/tests" EXCLUDE
    REGEX "\/test" EXCLUDE
    PATTERN "bootstrap.php" EXCLUDE
    PATTERN "services.xml" EXCLUDE)

install(DIRECTORY
    ${CMAKE_CURRENT_BINARY_DIR}/gen/
    DESTINATION ${FO_MODDIR}/lib/php
    COMPONENT lib
)

if(TESTING)
    enable_testing()
    add_subdirectory(tests)
endif(TESTING)

