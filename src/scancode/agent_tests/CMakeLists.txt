#[=======================================================================]
# SPDX-License-Identifier: GPL-2.0-only
# SPDX-FileCopyrightText: © 2026 Nakshatra Sharma <nakshatra.sharma3012@gmail.com>
#]=======================================================================]

set(FO_CWD ${CMAKE_CURRENT_SOURCE_DIR})

if(NOT TARGET phpunit)
    prepare_phpunit()
endif()

set(CMAKE_CXX_FLAGS "${CMAKE_CXX_FLAGS} ${FO_CXX_FLAGS} -fopenmp -Wextra")

# Placeholder tests - just need cppunit, not the full scancode library
add_executable(test_scancode "")
target_sources(test_scancode
    PRIVATE
        ${FO_CWD}/Unit/run_tests.cc
        ${FO_CWD}/Unit/test_scancode_wrapper.cc
        ${FO_CWD}/Unit/test_scancode_dbhandler.cc)
target_link_libraries(test_scancode
    PRIVATE
        ${cppunit_LIBRARIES})
target_include_directories(test_scancode 
    PRIVATE ${FO_CWD}/../agent)

add_test(scancode_unit_test test_scancode)

add_test(NAME scancode_functional_cli_test 
    COMMAND bash ${FO_CWD}/Functional/shunit2 ${FO_CWD}/Functional/cli_test.sh
    WORKING_DIRECTORY ${FO_CWD}/Functional)

add_test(NAME scancode_functional_scheduler_test
    COMMAND ${PHPUNIT} --bootstrap ${PHPUNIT_BOOTSTRAP} ${FO_CWD}/Functional/schedulerTest.php)

