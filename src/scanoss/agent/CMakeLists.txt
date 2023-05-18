#[=======================================================================[
SPDX-License-Identifier: GPL-2.0-only
SPDX-FileCopyrightText: © 2023 SCANOSS.COM
#]=======================================================================]
set(FO_CWD ${CMAKE_CURRENT_SOURCE_DIR})

set(CMAKE_C_FLAGS "${CMAKE_C_FLAGS} ${FO_C_FLAGS}")

cmake_minimum_required(VERSION 3.13)

include_directories(
    ${glib_INCLUDE_DIRS}
    ${PostgreSQL_INCLUDE_DIRS}
    ${FO_CLIB_SRC})

if(NOT TARGET fossology)
    add_subdirectory(${FO_CLIB_SRC} buildc EXCLUDE_FROM_ALL)
endif()

set(AGENT_SOURCES ${FO_CWD}/main.c ${FO_CWD}/snippet_scan.c)

add_executable(scanoss ${AGENT_SOURCES})

target_compile_definitions(scanoss
    PRIVATE
    _FILE_OFFSET_BITS=64
    VERSION_S="${FO_VERSION}"
    COMMIT_HASH_S="${FO_COMMIT_HASH}"
    FO_USER_S="${FO_PROJECTUSER}"
    )

    target_link_libraries(scanoss PRIVATE fossology
            ${json-c_LIBRARIES})

install(TARGETS scanoss
    RUNTIME
    DESTINATION ${FO_MODDIR}/${PROJECT_NAME}/agent
    COMPONENT scanoss)

