#[=======================================================================[
SPDX-License-Identifier: GPL-2.0-only
SPDX-FileCopyrightText: © 2021 Avinal Kumar <avinal.xlvii@gmail.com>
#]=======================================================================]

cmake_minimum_required(VERSION 3.13 FATAL_ERROR)
project(spdx3)

include(${CMAKE_CURRENT_SOURCE_DIR}/../../cmake/SetDefaults.cmake)

add_subdirectory(${CMAKE_CURRENT_SOURCE_DIR}/agent)

generate_version()

install(DIRECTORY ui
    DESTINATION ${FO_MODDIR}/${PROJECT_NAME}
    COMPONENT spdx3
    FILES_MATCHING
    PATTERN *.php
    PATTERN "SpdxThreeJsonAgentPlugin.php" EXCLUDE
    PATTERN "SpdxThreeRDFAgentPlugin.php" EXCLUDE
)

install(FILES ui/SpdxThreeJsonAgentPlugin.php
    DESTINATION ${FO_MODDIR}/spdx3json/ui
    COMPONENT spdx3)

install(FILES ui/SpdxThreeRDFAgentPlugin.php
    DESTINATION ${FO_MODDIR}/spdx3rdf/ui
    COMPONENT spdx3)

foreach(SPDX_INSTALL spdx3 spdx3json spdx3rdf)
    install(FILES ${SPDX_INSTALL}.conf "${CMAKE_CURRENT_BINARY_DIR}/VERSION"
        DESTINATION ${FO_MODDIR}/${SPDX_INSTALL}
        COMPONENT spdx3)
    add_symlink(${SPDX_INSTALL} ${FO_MODDIR}/${SPDX_INSTALL})
endforeach()

if(TESTING)
    configure_file(${FO_CMAKEDIR}/TestInstall.make.in ${CMAKE_CURRENT_BINARY_DIR}/TestInstall.make
        NEWLINE_STYLE LF @ONLY)
    enable_testing()
endif(TESTING)
