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

file(COPY testdata ${FO_CWD}/Unit/testInitDB.sh ${FO_CWD}/Unit/testInitRepo.sh
    DESTINATION ${CMAKE_CURRENT_BINARY_DIR})

file(MAKE_DIRECTORY ${CMAKE_CURRENT_BINARY_DIR}/testconf)
add_executable(test_delagent "")
target_sources(test_delagent
    PRIVATE
    ${FO_CWD}/Unit/testRun.c
    ${FO_CWD}/Unit/testListFolders.c
    ${FO_CWD}/Unit/testDeleteFolders.c
    ${FO_CWD}/../agent/util.c)
target_compile_definitions(test_delagent PRIVATE AGENT_DIR="${CMAKE_CURRENT_BINARY_DIR}/..")
target_include_directories(test_delagent
    PRIVATE ${FO_TESTDIR}/lib/c ${FO_TESTDIR}/db/c ${FO_CWD}/../agent
    ${glib_INCLUDE_DIRS} ${PostgreSQL_INCLUDE_DIRS} ${FO_CWD}/Unit ${FO_CLIB_SRC})
target_link_libraries(test_delagent
    PRIVATE fossology ${cunit_LIBRARIES} fodbreposysconf focunit gcrypt crypt
        ${glib_LIBRARIES} ${PostgreSQL_LIBRARIES})

# FIXME: Tests need to migrate to alternate framework for generating repo and db
# add_test(delagent_unit_test test_delagent)

# FIXME: Test does not run if database can not be created in Ubuntu 20-04
# And fails as it can not find delagent binary in Ubuntu 18-04 (exists in build folder)
# add_test(NAME delagent_functional_cli_test
#     COMMAND ${PHPUNIT} --log-junit delagent-Xunit.xml ${CMAKE_CURRENT_LIST_DIR}/Functional/ft_cliDelagentTest.php)
