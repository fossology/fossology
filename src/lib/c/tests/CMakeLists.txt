#[=======================================================================[
SPDX-License-Identifier: GPL-2.0-only
SPDX-FileCopyrightText: © 2021 Avinal Kumar <avinal.xlvii@gmail.com>
#]=======================================================================]

set(FO_CWD ${CMAKE_CURRENT_SOURCE_DIR})

if(NOT TARGET phpunit)
    prepare_phpunit()
endif()

if(NOT TARGET fodbreposysconf)
    add_subdirectory(${FO_TESTDIR}/db/c ctestdb EXCLUDE_FROM_ALL)
endif()

if(NOT TARGET focunit)
    add_subdirectory(${FO_TESTDIR}/lib/c ctestlib EXCLUDE_FROM_ALL)
endif()

file(COPY confdata DESTINATION ${CMAKE_CURRENT_BINARY_DIR})

add_executable(test_clibs "")
target_sources(test_clibs 
    PRIVATE 
    ${FO_CWD}/testlibs.c
    ${FO_CWD}/test_fossconfig.c
    ${FO_CWD}/test_fossscheduler.c
    ${FO_CWD}/test_libfossdb.c
    ${FO_CWD}/test_libfossdbmanager.c)
target_compile_definitions(test_clibs PRIVATE)
target_include_directories(test_clibs PRIVATE 
    ${FO_TESTDIR}/lib/c ${FO_TESTDIR}/db/c)
target_link_libraries(test_clibs 
    PRIVATE fossology ${cunit_LIBRARIES} fodbreposysconf focunit)

file(COPY ${CMAKE_CURRENT_LIST_DIR}/testClib.php DESTINATION ${CMAKE_CURRENT_BINARY_DIR})

add_test(clib_tests test_clibs)

add_test(NAME clib_php_tests 
    COMMAND ${PHPUNIT} --bootstrap ${PHPUNIT_BOOTSTRAP} ${CMAKE_CURRENT_LIST_DIR}/testClib.php)
