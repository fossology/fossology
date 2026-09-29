#[=======================================================================[
SPDX-License-Identifier: GPL-2.0-only
SPDX-FileCopyrightText: © 2026 Siemens Healthineers AG
#]=======================================================================]

if(NOT TARGET focunit)
    add_subdirectory(${FO_TESTDIR}/lib/c ctestlib EXCLUDE_FROM_ALL)
endif()

add_executable(test_adj2nest Unit/test_walk_tree.c)
target_compile_features(test_adj2nest PRIVATE c_std_99)
target_compile_definitions(test_adj2nest PRIVATE _FILE_OFFSET_BITS=64)
target_compile_options(test_adj2nest PRIVATE -Wall -fno-optimize-sibling-calls)
target_include_directories(test_adj2nest PRIVATE
    ${FO_TESTDIR}/lib/c
    ${FO_CLIB_SRC}
    ${cunit_INCLUDE_DIRS}
    ${glib_INCLUDE_DIRS}
    ${PostgreSQL_INCLUDE_DIRS})
target_link_libraries(test_adj2nest PRIVATE focunit ${cunit_LIBRARIES} fossology)

add_test(NAME adj2nest_unit_test COMMAND test_adj2nest)
set_tests_properties(adj2nest_unit_test PROPERTIES TIMEOUT 60)