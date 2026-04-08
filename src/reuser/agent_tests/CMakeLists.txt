#[=======================================================================[
SPDX-License-Identifier: GPL-2.0-only
Author: Dietmar Helmut Leher <helmut.leher.ext@vaillant-group.com>
SPDX-FileCopyrightText: © 2026 Vaillant GmbH
#]=======================================================================]

set(FO_CWD ${CMAKE_CURRENT_SOURCE_DIR})

if(NOT TARGET phpunit)
    prepare_phpunit()
endif()

set(CMAKE_CXX_FLAGS "${CMAKE_CXX_FLAGS} ${FO_CXX_FLAGS} -Wall")

add_executable(test_reuser "")
target_sources(test_reuser
    PRIVATE
        ${FO_CWD}/Unit/run_tests.cc
        ${FO_CWD}/Unit/MockReuserDatabaseHandler.cc
        ${FO_CWD}/Unit/test_types.cc
        ${FO_CWD}/Unit/test_database_handler.cc
        ${FO_CWD}/Unit/test_worker.cc
        ${FO_CWD}/Unit/test_scenarios.cc
        ${FO_CWD}/Unit/test_helpers.cc
        # Agent sources under test (no main).
        ${FO_CWD}/../agent/ReuserDatabaseHandler.cc
        ${FO_CWD}/../agent/ReuserState.cc
        ${FO_CWD}/../agent/ReuserUtils.cc
)

target_compile_definitions(test_reuser
    PRIVATE
        _FILE_OFFSET_BITS=64
        DATADIR="${FO_MODDIR}"
        VERSION_S="${FO_VERSION}"
        COMMIT_HASH_S="${FO_COMMIT_HASH}"
)

target_include_directories(test_reuser
    PRIVATE
        ${glib_INCLUDE_DIRS}
        ${PostgreSQL_INCLUDE_DIRS}
        ${FO_CXXLIB_SRC}
        ${FO_CLIB_SRC}
        ${FO_CWD}/../agent
        ${FO_CWD}/Unit
)

target_link_libraries(test_reuser
    PRIVATE
        ${cppunit_LIBRARIES}
        fossologyCPP
        ${icu-uc_LIBRARIES}
)

add_test(reuser_unit_test test_reuser)

# The binary is built as 'reuser_exec' in the build tree (OUTPUT_NAME is NOT
# set to avoid a Ninja duplicate-rule conflict with the generate_version_php
# custom target named 'reuser'). Create a symlink 'reuser' in the source tree
# so SchedulerTestRunnerCli.php can invoke it with the correct argv[0] basename
# (fo_scheduler_connect looks up mods-enabled/<basename>/VERSION).
# Also symlink the generated VERSION file – mirrors the deciderjob pattern.
add_custom_target(prepare_reuser_test ALL
    COMMENT "Staging reuser binary and VERSION symlinks for functional tests"
    COMMAND ln -sf "${CMAKE_CURRENT_BINARY_DIR}/../VERSION"
    COMMAND ln -sf "${CMAKE_CURRENT_BINARY_DIR}/../agent/reuser_exec"
        "${CMAKE_CURRENT_SOURCE_DIR}/../agent/reuser"
    WORKING_DIRECTORY "${CMAKE_CURRENT_SOURCE_DIR}/..")
add_dependencies(prepare_reuser_test reuser_exec)

add_test(NAME reuser_functional_scheduler_test
    COMMAND ${PHPUNIT} --bootstrap ${PHPUNIT_BOOTSTRAP} ${FO_CWD}/Functional/schedulerTest.php)
