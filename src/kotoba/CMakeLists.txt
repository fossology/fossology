#[=======================================================================[
SPDX-License-Identifier: GPL-2.0-only
SPDX-FileCopyrightText: © 2025 Harshit Gandhi <gandhiharshit716@gmail.com>
#]=======================================================================]

cmake_minimum_required(VERSION 3.13 FATAL_ERROR)
project(kotoba LANGUAGES C)

include(${CMAKE_CURRENT_SOURCE_DIR}/../../cmake/SetDefaults.cmake)

add_subdirectory(${CMAKE_CURRENT_SOURCE_DIR}/agent)

install(DIRECTORY ui
    DESTINATION ${FO_MODDIR}/${PROJECT_NAME}
    COMPONENT kotoba
    FILES_MATCHING
    PATTERN *.php
    PATTERN "agent-kotoba-bulk.php" EXCLUDE)

install(FILES ui/agent-kotoba-bulk.php 
    DESTINATION ${FO_MODDIR}/kotobabulk/ui
    COMPONENT kotobabulk)

foreach(KOTOBA_INSTALL kotobabulk kotoba)
    set(PROJECT_NAME ${KOTOBA_INSTALL})
    generate_version(${KOTOBA_INSTALL} "VERSION-${KOTOBA_INSTALL}")
    
    install(FILES ${KOTOBA_INSTALL}.conf
        DESTINATION ${FO_MODDIR}/${KOTOBA_INSTALL}
        COMPONENT ${KOTOBA_INSTALL})

    install(FILES "${CMAKE_CURRENT_BINARY_DIR}/VERSION-${KOTOBA_INSTALL}"
        DESTINATION ${FO_MODDIR}/${KOTOBA_INSTALL}
        RENAME "VERSION"
        COMPONENT ${KOTOBA_INSTALL})
    add_symlink(${KOTOBA_INSTALL})
endforeach()

# To be uncommented when test cases are written
# if(TESTING)
#     set(VERSIONFILE "VERSION-kotoba")
#     configure_file(${FO_CMAKEDIR}/TestInstall.make.in ${CMAKE_CURRENT_BINARY_DIR}/TestInstall.make NEWLINE_STYLE LF @ONLY)
#    enable_testing()
#     add_subdirectory(agent_tests)
# endif(TESTING)
