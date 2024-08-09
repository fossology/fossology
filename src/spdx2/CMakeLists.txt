#[=======================================================================[
SPDX-License-Identifier: GPL-2.0-only
SPDX-FileCopyrightText: © 2021 Avinal Kumar <avinal.xlvii@gmail.com>
#]=======================================================================]

cmake_minimum_required(VERSION 3.13 FATAL_ERROR)
project(spdx2)

include(${CMAKE_CURRENT_SOURCE_DIR}/../../cmake/SetDefaults.cmake)

add_subdirectory(${CMAKE_CURRENT_SOURCE_DIR}/agent)

generate_version()

install(DIRECTORY ui
    DESTINATION ${FO_MODDIR}/${PROJECT_NAME}
    COMPONENT spdx2
    FILES_MATCHING
    PATTERN *.php
    PATTERN "DepFiveAgentPlugin.php" EXCLUDE
    PATTERN "SpdxTwoTagValueAgentPlugin.php" EXCLUDE
    PATTERN "SpdxTwoCommaSeparatedValuesAgentPlugin.php" EXCLUDE
    PATTERN "SpdxThreeJsonldAgentPlugin.php" EXCLUDE
    PATTERN "SpdxThreeJsonAgentPlugin.php" EXCLUDE
    PATTERN "SpdxThreeRDFAgentPlugin.php" EXCLUDE
    PATTERN "SpdxThreeTagValueAgentPlugin.php" EXCLUDE
)

install(FILES ui/DepFiveAgentPlugin.php
    DESTINATION ${FO_MODDIR}/dep5/ui
    COMPONENT spdx2)

install(FILES ui/SpdxTwoTagValueAgentPlugin.php
    DESTINATION ${FO_MODDIR}/spdx2tv/ui
    COMPONENT spdx2)

install(FILES ui/SpdxTwoCommaSeparatedValuesAgentPlugin.php
    DESTINATION ${FO_MODDIR}/spdx2csv/ui
    COMPONENT spdx2)

install(FILES ui/SpdxThreeJsonldAgentPlugin.php
    DESTINATION ${FO_MODDIR}/spdx3jsonld/ui
    COMPONENT spdx2)

install(FILES ui/SpdxThreeJsonAgentPlugin.php
    DESTINATION ${FO_MODDIR}/spdx3json/ui
    COMPONENT spdx2)

install(FILES ui/SpdxThreeRDFAgentPlugin.php
    DESTINATION ${FO_MODDIR}/spdx3rdf/ui
    COMPONENT spdx2)

install(FILES ui/SpdxThreeTagValueAgentPlugin.php
    DESTINATION ${FO_MODDIR}/spdx3tv/ui
    COMPONENT spdx2)

foreach(SPDX_INSTALL spdx2 spdx2tv dep5 spdx2csv spdx3jsonld spdx3json spdx3rdf spdx3tv)
    install(FILES ${SPDX_INSTALL}.conf "${CMAKE_CURRENT_BINARY_DIR}/VERSION"
        DESTINATION ${FO_MODDIR}/${SPDX_INSTALL}
        COMPONENT spdx2)
    add_symlink(${SPDX_INSTALL} ${FO_MODDIR}/${SPDX_INSTALL})
endforeach()

if(TESTING)
    configure_file(${FO_CMAKEDIR}/TestInstall.make.in ${CMAKE_CURRENT_BINARY_DIR}/TestInstall.make
        NEWLINE_STYLE LF @ONLY)
    enable_testing()
    add_subdirectory(agent_tests)
endif(TESTING)
