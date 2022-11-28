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

foreach(SPDX_INSTALL spdx2 spdx2tv dep5 spdx2csv)
    install(FILES ${SPDX_INSTALL}.conf "${CMAKE_CURRENT_BINARY_DIR}/VERSION"
        DESTINATION ${FO_MODDIR}/${SPDX_INSTALL}
        COMPONENT spdx2)
    add_symlink(${SPDX_INSTALL} ${FO_MODDIR}/${SPDX_INSTALL})
endforeach()

if(TESTING)
    enable_testing()
    add_subdirectory(agent_tests)
endif(TESTING)
