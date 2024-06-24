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

add_executable(test_nomos "")
target_sources(test_nomos
    PRIVATE
    ${FO_CWD}/Unit/run_tests.c
    ${FO_CWD}/Unit/test_DoctoredBuffer.c
    ${FO_CWD}/Unit/test_nomos_gap.c)
target_compile_definitions(test_nomos
    PRIVATE _FILE_OFFSET_BITS=64
        TESTDATADIR="${FO_SOURCEDIR}/nomos/agent_tests/testdata")
target_include_directories(test_nomos
    PRIVATE ${FO_TESTDIR}/lib/c ${FO_TESTDIR}/db/c ${glib_INCLUDE_DIRS}
        $<TARGET_FILE_DIR:encode> ${PostgreSQL_INCLUDE_DIRS} ${FO_CLIB_SRC} ${FO_CWD}/../agent)
target_link_libraries(test_nomos PRIVATE ${cunit_LIBRARIES} focunit nomos OpenMP::OpenMP_C)

add_test(nomos_unit_test test_nomos)

add_test(NAME nomos_functional_test
    COMMAND ${PHPUNIT} --log-junit nomos-Xunit.xml --configuration ${FO_CWD}/Functional/tests.xml)
