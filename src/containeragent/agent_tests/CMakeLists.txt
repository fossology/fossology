#[=======================================================================[
SPDX-License-Identifier: GPL-2.0-only
SPDX-FileCopyrightText: © 2026 Fossology contributors
#]=======================================================================]

set(FO_CWD ${CMAKE_CURRENT_SOURCE_DIR})

if(NOT TARGET fodbreposysconf)
    add_subdirectory(${FO_TESTDIR}/db/c ctestdb EXCLUDE_FROM_ALL)
endif()

if(NOT TARGET focunit)
    add_subdirectory(${FO_TESTDIR}/lib/c ctestlib EXCLUDE_FROM_ALL)
endif()

file(COPY ${FO_CWD}/testdata DESTINATION ${CMAKE_CURRENT_BINARY_DIR})

set(CMAKE_C_FLAGS "${CMAKE_C_FLAGS} ${FO_C_FLAGS} -Wextra -fopenmp")

find_package(PkgConfig REQUIRED)
pkg_check_modules(JSONC REQUIRED json-c)

add_executable(test_containeragent "")
target_sources(test_containeragent
    PRIVATE
    ${FO_CWD}/Unit/testRun.c
    ${FO_CWD}/Unit/testContainerAgent.c
    ${FO_CWD}/../agent/containeragent.c
    ${FO_CWD}/../agent/containerpkg.c)

target_compile_definitions(test_containeragent
    PRIVATE
    _FILE_OFFSET_BITS=64
    VERSION_S="${FO_VERSION}"
    COMMIT_HASH_S="${FO_COMMIT_HASH}")

target_include_directories(test_containeragent
    PRIVATE
    ${FO_TESTDIR}/lib/c
    ${FO_TESTDIR}/db/c
    ${glib_INCLUDE_DIRS}
    ${PostgreSQL_INCLUDE_DIRS}
    ${JSONC_INCLUDE_DIRS}
    ${FO_CLIB_SRC}
    ${FO_CWD}/../agent)

target_link_libraries(test_containeragent
    PRIVATE
    fossology
    cunit
    focunit
    fodbreposysconf
    pthread
    ${JSONC_LIBRARIES})

add_test(containeragent_unit_test test_containeragent)
