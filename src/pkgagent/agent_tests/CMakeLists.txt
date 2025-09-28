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

file(COPY ${FO_CWD}/testdata DESTINATION ${CMAKE_CURRENT_BINARY_DIR})

set(CMAKE_C_FLAGS "${CMAKE_C_FLAGS} ${FO_C_FLAGS} -Wextra -fopenmp")

add_executable(test_pkgagent "")
target_sources(test_pkgagent 
    PRIVATE 
    ${FO_CWD}/Unit/testRun.c
    ${FO_CWD}/Unit/testGetFieldValue.c
    ${FO_CWD}/Unit/testGetMetadata.c
    ${FO_CWD}/Unit/testRecordMetadataRPM.c
    ${FO_CWD}/Unit/testRecordMetadataDEB.c
    ${FO_CWD}/Unit/testGetMetadataDebSource.c
    ${FO_CWD}/Unit/testGetMetadataDebBinary.c
    ${FO_CWD}/../agent/pkgagent.c)
target_compile_definitions(test_pkgagent PRIVATE _FILE_OFFSET_BITS=64)

if(FO_RPM_CRYPTO_H_HEADER)
  target_compile_definitions(test_pkgagent PRIVATE HAVE_RPM_CRYPTO_H=1)
endif()

target_include_directories(test_pkgagent 
    PRIVATE ${FO_TESTDIR}/lib/c ${FO_TESTDIR}/db/c ${glib_INCLUDE_DIRS}
        ${PostgreSQL_INCLUDE_DIRS} ${FO_CLIB_SRC} ${FO_CWD}/../agent
        ${rpm_INCLUDE_DIRS})
target_link_libraries(test_pkgagent 
    PRIVATE fossology cunit focunit ${rpm_LIBRARIES} fodbreposysconf pthread
        ${rpm_LIBRARIES})

add_test(pkgagent_unit_test test_pkgagent)

add_test(NAME pkgagent_functional_cli_test
    COMMAND ${PHPUNIT} --log-junit pkgagent-Xunit.xml ${FO_CWD}/Functional/ft_cliPkgagentTest.php
    WORKING_DIRECTORY ${FO_CWD})
