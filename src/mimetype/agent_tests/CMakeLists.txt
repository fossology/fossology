#[=======================================================================[
SPDX-License-Identifier: GPL-2.0-only
SPDX-FileCopyrightText: © 2021 Avinal Kumar <avinal.xlvii@gmail.com>
#]=======================================================================]

set(FO_CWD ${CMAKE_CURRENT_SOURCE_DIR})

if(NOT TARGET fodbreposysconf)
    add_subdirectory(${FO_TESTDIR}/db/c ctestdb EXCLUDE_FROM_ALL)
endif()

if(NOT TARGET focunit)
    add_subdirectory(${FO_TESTDIR}/lib/c ctestlib EXCLUDE_FROM_ALL)
endif()

if(NOT TARGET phpunit)
    prepare_phpunit()
endif()

add_executable(test_mimetype "")
target_sources(test_mimetype 
    PRIVATE 
    ${FO_CWD}/Unit/testRun.c
    ${FO_CWD}/Unit/finder/testDBCheckMime.c
    ${FO_CWD}/Unit/finder/testDBLoadMime.c
    ${FO_CWD}/Unit/finder/testDBFindMime.c
    ${FO_CWD}/Unit/finder/testOtheFunctions.c)
target_compile_definitions(test_mimetype PRIVATE _FILE_OFFSET_BITS=64 AGENT_DIR="${CMAKE_CURRENT_BINARY_DIR}/..")
target_include_directories(test_mimetype 
    PRIVATE ${FO_TESTDIR}/lib/c ${FO_TESTDIR}/db/c ${glib_INCLUDE_DIRS}
        ${PostgreSQL_INCLUDE_DIRS} ${FO_CLIB_SRC} ${FO_CWD}/../agent)
target_link_libraries(test_mimetype
    PRIVATE ${cunit_LIBRARIES} fodbreposysconf focunit mimetype)

add_test(mimetype_unit_test test_mimetype)

add_test(NAME mimetype_functional_test 
    COMMAND ${PHPUNIT} --log-junit mimetype-Xunit.xml ${CMAKE_CURRENT_LIST_DIR}/Functional/cliParamsTest4Mimetype.php)
