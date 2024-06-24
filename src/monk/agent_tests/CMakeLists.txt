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

set(CMAKE_C_FLAGS "${CMAKE_C_FLAGS} ${FO_C_FLAGS} -std=c99 -Wextra")

add_executable(test_monk "")
target_sources(test_monk
    PRIVATE
    ${FO_CWD}/Unit/run_tests.c
    ${FO_CWD}/Unit/test_string_operations.c
    ${FO_CWD}/Unit/test_file_operations.c
    ${FO_CWD}/Unit/test_license.c
    ${FO_CWD}/Unit/test_highlight.c
    ${FO_CWD}/Unit/test_hash.c
    ${FO_CWD}/Unit/test_match.c
    ${FO_CWD}/Unit/test_diff.c
    ${FO_CWD}/Unit/test_database.c
    ${FO_CWD}/Unit/test_encoding.c
    ${FO_CWD}/Unit/test_serialize.c)
target_compile_definitions(test_monk
    PRIVATE _FILE_OFFSET_BITS=64 AGENT_DIR="${CMAKE_CURRENT_BINARY_DIR}/..")
target_include_directories(test_monk
    PRIVATE ${FO_TESTDIR}/lib/c ${FO_TESTDIR}/db/c ${glib_INCLUDE_DIRS}
        ${PostgreSQL_INCLUDE_DIRS} ${FO_CLIB_SRC} ${FO_CWD}/../agent)
target_link_libraries(test_monk
    PRIVATE ${cunit_LIBRARIES} fodbreposysconf focunit monk)

add_test(monk_unit_test test_monk)

add_test(NAME monk_functional_scheduler_test
    COMMAND ${PHPUNIT} --log-junit monk-Xunit1.xml --bootstrap ${PHPUNIT_BOOTSTRAP} ${CMAKE_CURRENT_LIST_DIR}/Functional/schedulerTest.php)

add_test(NAME monk_functional_cli_test
    COMMAND ${PHPUNIT} --log-junit monk-Xunit2.xml --bootstrap ${PHPUNIT_BOOTSTRAP} ${CMAKE_CURRENT_LIST_DIR}/Functional/cliTest.php)

add_test(NAME monk_functional_bulk_test
    COMMAND ${PHPUNIT} --log-junit monk-Xunit3.xml --bootstrap ${PHPUNIT_BOOTSTRAP} ${CMAKE_CURRENT_LIST_DIR}/Functional/bulkTest.php)
