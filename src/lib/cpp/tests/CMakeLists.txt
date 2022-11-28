#[=======================================================================[
SPDX-License-Identifier: GPL-2.0-only
SPDX-FileCopyrightText: © 2021 Avinal Kumar <avinal.xlvii@gmail.com>
#]=======================================================================]

set(FO_CWD ${CMAKE_CURRENT_SOURCE_DIR})

if(NOT TARGET fodbreposysconf)
    add_subdirectory(${FO_TESTDIR}/db/c ctestdb EXCLUDE_FROM_ALL)
endif()

if(NOT TARGET phpunit)
    prepare_phpunit()
endif()

add_executable(test_libcpp "")
target_sources(test_libcpp PRIVATE ${FO_CWD}/run_tests.cc ${FO_CWD}/test_fossdbmanagerclass.cc)
target_include_directories(test_libcpp PRIVATE ${FO_TESTDIR}/db/c)
target_compile_definitions(test_libcpp PRIVATE BUILDDIR="${FO_SOURCEDIR}/../build")
target_link_libraries(test_libcpp PRIVATE fossologyCPP cppunit fodbreposysconf)

add_test(cpplib_tests test_libcpp)
