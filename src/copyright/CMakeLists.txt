#[=======================================================================[
SPDX-License-Identifier: GPL-2.0-only
SPDX-FileCopyrightText: © 2021 Avinal Kumar <avinal.xlvii@gmail.com>
#]=======================================================================]

cmake_minimum_required(VERSION 3.18 FATAL_ERROR)
project(copyright LANGUAGES CXX C)
include(${CMAKE_CURRENT_SOURCE_DIR}/../../cmake/SetDefaults.cmake)

pkg_check_modules(jsoncpp REQUIRED jsoncpp)

add_subdirectory(${CMAKE_CURRENT_SOURCE_DIR}/agent)

install(DIRECTORY ui
    DESTINATION ${FO_MODDIR}/${PROJECT_NAME}
    COMPONENT copyright
    FILES_MATCHING
    PATTERN *.php
    PATTERN *.twig
    PATTERN "agent-ecc.php" EXCLUDE
    PATTERN "agent-keyword.php" EXCLUDE
    PATTERN "agent-ipra.php" EXCLUDE)

foreach(COP ecc keyword copyright ipra)
    set(PROJECT_NAME ${COP})
    generate_version(${COP} "VERSION-${COP}")
    install(FILES ui/agent-${COP}.php
        DESTINATION ${FO_MODDIR}/${COP}/ui
        COMPONENT ${COP})
    install(FILES ${COP}.conf
        DESTINATION ${FO_MODDIR}/${COP}
        COMPONENT ${COP})
    install(FILES "${CMAKE_CURRENT_BINARY_DIR}/VERSION-${COP}"
        DESTINATION ${FO_MODDIR}/${COP}
        RENAME "VERSION"
        COMPONENT ${COP})
    add_symlink(${COP})
endforeach()

if(TESTING)
    set(VERSIONFILE "VERSION-copyright")
    configure_file(${FO_CMAKEDIR}/TestInstall.make.in ${CMAKE_CURRENT_BINARY_DIR}/TestInstall.make
    NEWLINE_STYLE LF @ONLY)
    enable_testing()
    add_subdirectory(agent_tests)
endif(TESTING)
