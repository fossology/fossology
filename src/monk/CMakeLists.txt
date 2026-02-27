#[=======================================================================[
SPDX-License-Identifier: GPL-2.0-only
SPDX-FileCopyrightText: © 2021 Avinal Kumar <avinal.xlvii@gmail.com>
#]=======================================================================]

cmake_minimum_required(VERSION 3.18 FATAL_ERROR)
project(monk LANGUAGES C)

include(${CMAKE_CURRENT_SOURCE_DIR}/../../cmake/SetDefaults.cmake)

add_subdirectory(${CMAKE_CURRENT_SOURCE_DIR}/agent)

install(DIRECTORY ui
    DESTINATION ${FO_MODDIR}/${PROJECT_NAME}
    COMPONENT monk
    FILES_MATCHING
    PATTERN *.php
    PATTERN "agent-monk-bulk.php" EXCLUDE
    PATTERN "agent-kotoba.php" EXCLUDE)

install(FILES ui/agent-monk-bulk.php
    DESTINATION ${FO_MODDIR}/monkbulk/ui
    COMPONENT monkbulk)

install(FILES ui/agent-kotoba.php
    DESTINATION ${FO_MODDIR}/kotoba/ui
    COMPONENT kotoba)

foreach(MONK_INSTALL monkbulk kotoba monk)
    set(PROJECT_NAME ${MONK_INSTALL})
    generate_version(${MONK_INSTALL} "VERSION-${MONK_INSTALL}")
    
    install(FILES ${MONK_INSTALL}.conf
        DESTINATION ${FO_MODDIR}/${MONK_INSTALL}
        COMPONENT ${MONK_INSTALL})

    install(FILES "${CMAKE_CURRENT_BINARY_DIR}/VERSION-${MONK_INSTALL}"
        DESTINATION ${FO_MODDIR}/${MONK_INSTALL}
        RENAME "VERSION"
        COMPONENT ${MONK_INSTALL})
    add_symlink(${MONK_INSTALL})
endforeach()

if(TESTING)
    set(VERSIONFILE "VERSION-monk")
    configure_file(${FO_CMAKEDIR}/TestInstall.make.in ${CMAKE_CURRENT_BINARY_DIR}/TestInstall.make NEWLINE_STYLE LF @ONLY)
    enable_testing()
    add_subdirectory(agent_tests)
endif(TESTING)
