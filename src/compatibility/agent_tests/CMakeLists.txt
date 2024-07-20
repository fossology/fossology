#[=======================================================================[
SPDX-License-Identifier: GPL-2.0-only
SPDX-FileCopyrightText: © 2024 Siemens AG
#]=======================================================================]

set(FO_CWD ${CMAKE_CURRENT_SOURCE_DIR})

if(NOT TARGET phpunit)
    prepare_phpunit()
endif()

set(CMAKE_CXX_FLAGS "${CMAKE_CXX_FLAGS} ${FO_CXX_FLAGS} -fopenmp -Wextra")

add_executable(test_compatibility "")
target_sources(test_compatibility
    PRIVATE
        ${FO_CWD}/Unit/run_tests.cc
        ${FO_CWD}/Unit/test_compatibility.cc
        ${FO_CWD}/Unit/test_utils.cc
)

target_compile_definitions(test_compatibility
    PRIVATE
        TESTDATADIR="${FO_CWD}/testdata"
)

target_include_directories(test_compatibility
    PRIVATE
        ${FO_CWD}/../agent
        ${glib_INCLUDE_DIRS}
        ${jsoncpp_INCLUDE_DIRS}
        ${yamlcpp_INCLUDE_DIRS}
        ${PostgreSQL_INCLUDE_DIRS}
        ${FO_CLIB_SRC}
        ${FO_CXXLIB_SRC}
)

target_link_libraries(test_compatibility
    PRIVATE
        compatibility
        ${cppunit_LIBRARIES}
        ${icu-uc_LIBRARIES}
        ${Boost_LIBRARIES}
        jsoncpp_lib
        yaml-cpp
)

add_test(compatibility_unit_test test_compatibility)

add_test(NAME compatibility_functional_cli_test
    COMMAND bash ${FO_CWD}/Functional/shunit2 ${FO_CWD}/Functional/cli_test.sh
    WORKING_DIRECTORY ${FO_CWD}/Functional)

add_test(NAME compatibility_functional_scheduler_test
    COMMAND ${PHPUNIT} --bootstrap ${PHPUNIT_BOOTSTRAP} ${FO_CWD}/Functional/schedulerTest.php)
