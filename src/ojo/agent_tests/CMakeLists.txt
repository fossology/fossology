#[=======================================================================[
SPDX-License-Identifier: GPL-2.0-only
SPDX-FileCopyrightText: © 2021 Avinal Kumar <avinal.xlvii@gmail.com>
#]=======================================================================]

set(FO_CWD ${CMAKE_CURRENT_SOURCE_DIR})

if(NOT TARGET phpunit)
    prepare_phpunit()
endif()

set(CMAKE_CXX_FLAGS "${CMAKE_CXX_FLAGS} ${FO_CXX_FLAGS} -Wall")

add_executable(test_ojo "")
target_sources(test_ojo
    PRIVATE
    ${FO_CWD}/Unit/run_tests.cc
    ${FO_CWD}/Unit/test_regex.cc
    ${FO_CWD}/Unit/test_scanners.cc)
target_compile_definitions(test_ojo PRIVATE _FILE_OFFSET_BITS=64)
target_include_directories(test_ojo
    PRIVATE ${glib_INCLUDE_DIRS} ${PostgreSQL_INCLUDE_DIRS} ${FO_CXXLIB_SRC}
        ${FO_CLIB_SRC} ${FO_CWD}/../agent)
target_link_libraries(test_ojo
    PRIVATE ${cppunit_LIBRARIES} ojo ${Boost_LIBRARIES} fossologyCPP
        ICU::uc ICU::i18n)

add_test(ojo_unit_test test_ojo)

add_test(NAME ojo_functional_scheduler_test
    COMMAND ${PHPUNIT} --bootstrap ${PHPUNIT_BOOTSTRAP} ${FO_CWD}/Functional/schedulerTest.php)

add_test(NAME ojo_functional_cli_test
    COMMAND ${FO_CWD}/Functional/shunit2 ${FO_CWD}/Functional/cli_test.sh
    WORKING_DIRECTORY ${FO_CWD}/Functional)
