#[=======================================================================[
SPDX-License-Identifier: GPL-2.0-only
SPDX-FileCopyrightText: © 2021 Avinal Kumar <avinal.xlvii@gmail.com>
#]=======================================================================]

cmake_minimum_required(VERSION 3.18 FATAL_ERROR)
project(libcpp LANGUAGES CXX C)

set(FO_CWD ${CMAKE_CURRENT_SOURCE_DIR})

include(${CMAKE_CURRENT_SOURCE_DIR}/../../../cmake/SetDefaults.cmake)

set(CMAKE_CXX_FLAGS "${CMAKE_CXX_FLAGS} ${FO_CXX_FLAGS} -fPIC -Wextra -Weffc++")

include_directories(
    ${glib_INCLUDE_DIRS}
    ${PostgreSQL_INCLUDE_DIRS}
    ${FO_CLIB_SRC})

if(NOT TARGET fossology)
    add_subdirectory(${FO_CLIB_SRC} buildc EXCLUDE_FROM_ALL)
endif()

add_library(fossologyCPP "")
target_compile_definitions(fossologyCPP
    PRIVATE
    _FILE_OFFSET_BITS=64
    FOSSDB_CONF="${FO_SYSCONFDIR}/Db.conf"
    FOSSGROUP="${FO_PROJECTGROUP}"
    DEFAULT_SETUP="${FO_SYSCONFDIR}"
)
target_sources(fossologyCPP
    PRIVATE
        ${FO_CWD}/libfossAgentDatabaseHandler.cc
        ${FO_CWD}/libfossdbmanagerclass.cc
        ${FO_CWD}/files.cc
        ${FO_CWD}/libfossdbQueryResult.cc
        ${FO_CWD}/libfossUtils.cc)

target_link_libraries(fossologyCPP
    PRIVATE
        fossology
        ${icu-uc_LIBRARIES}
)

target_include_directories(fossologyCPP
    PUBLIC $<BUILD_INTERFACE:${CMAKE_CURRENT_LIST_DIR}>)
install(TARGETS fossologyCPP
    ARCHIVE
    DESTINATION ${FO_LIBEXECDIR}
    COMPONENT lib)

install(FILES ${FO_CWD}/libfossologyCPP.hpp
    DESTINATION ${FO_INCLUDEDIR}
    COMPONENT lib)

if(TESTING)
    enable_testing()
    add_subdirectory(tests)
endif()
