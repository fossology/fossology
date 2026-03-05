#[=======================================================================[
SPDX-License-Identifier: GPL-2.0-only
SPDX-FileCopyrightText: © 2021 Avinal Kumar <avinal.xlvii@gmail.com>
#]=======================================================================]

cmake_minimum_required(VERSION 3.18 FATAL_ERROR)
project(libc LANGUAGES C)

include(${CMAKE_CURRENT_SOURCE_DIR}/../../../cmake/SetDefaults.cmake)

set(CMAKE_C_FLAGS "${CMAKE_C_FLAGS} -fPIC -Wall")

include_directories(
    ${fossology_SOURCE_DIR}
    ${glib_INCLUDE_DIRS}
    ${PostgreSQL_INCLUDE_DIRS})

# sqlite3 — required for RPM sqlite database parsing in fosspkg
find_package(PkgConfig REQUIRED)
pkg_check_modules(SQLITE3 sqlite3)
if(SQLITE3_FOUND)
    include_directories(${SQLITE3_INCLUDE_DIRS})
endif()

add_library(fossology "")
add_library(fossology_cov EXCLUDE_FROM_ALL "")
set(FO_CWD ${CMAKE_CURRENT_SOURCE_DIR})
foreach(FO_CLIB_TARGET fossology fossology_cov)
    target_compile_definitions(${FO_CLIB_TARGET}
        PRIVATE
        _FILE_OFFSET_BITS=64
        FOSSDB_CONF="${FO_SYSCONFDIR}/Db.conf"
        FOSSGROUP="${FO_PROJECTGROUP}"
        DEFAULT_SETUP="${FO_SYSCONFDIR}"
    )
    target_sources(${FO_CLIB_TARGET}
        PRIVATE
            ${FO_CWD}/libfossscheduler.c
            ${FO_CWD}/libfossdb.c
            ${FO_CWD}/libfossagent.c
            ${FO_CWD}/libfossrepo.c
            ${FO_CWD}/libfossdbmanager.c
            ${FO_CWD}/fossconfig.c
            ${FO_CWD}/sqlCopy.c
            ${FO_CWD}/libfossagentref.h
            ${FO_CWD}/fosspkg.c)
    target_link_libraries(${FO_CLIB_TARGET}
        PRIVATE ${glib_LIBRARIES} ${PostgreSQL_LIBRARIES})
    if(SQLITE3_FOUND)
        target_compile_definitions(${FO_CLIB_TARGET}
            PUBLIC FOSSPKG_HAVE_SQLITE3=1)
        target_link_libraries(${FO_CLIB_TARGET}
            PUBLIC ${SQLITE3_LIBRARIES})
    endif()
endforeach()

target_compile_options(fossology_cov PRIVATE ${FO_COV_FLAGS})

foreach(FO_CLIB_EXEC sqlCopyTest fossconfigTest reppath)
    add_executable(${FO_CLIB_EXEC} "")
    target_sources(${FO_CLIB_EXEC} PRIVATE ${FO_CLIB_EXEC}.c)
    target_link_libraries(${FO_CLIB_EXEC} PRIVATE fossology)
    target_compile_definitions(${FO_CLIB_EXEC}
        PRIVATE
        _FILE_OFFSET_BITS=64
        FOSSDB_CONF="${FO_SYSCONFDIR}/Db.conf"
        FOSSGROUP="${FO_PROJECTGROUP}"
        DEFAULT_SETUP="${FO_SYSCONFDIR}"
    )
endforeach()

target_include_directories(fossology
    PUBLIC $<BUILD_INTERFACE:${CMAKE_CURRENT_LIST_DIR}>)

install(TARGETS fossology sqlCopyTest fossconfigTest reppath
    DESTINATION ${FO_LIBEXECDIR}
    COMPONENT lib)

install(FILES ${FO_CWD}/libfossology.h
    DESTINATION ${FO_INCLUDEDIR}
    COMPONENT lib)

if(TESTING)
    enable_testing()
    add_subdirectory(tests)
endif()
