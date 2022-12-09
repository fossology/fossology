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

set(CMAKE_C_FLAGS "${CMAKE_C_FLAGS} -Wall")

set(TEST_SUITE_LIMIT 120)

add_executable(test_scheduler "")
target_sources(test_scheduler
    PRIVATE
    ${FO_CWD}/Unit/testRun.c
    ${FO_CWD}/Unit/testHost.c
    ${FO_CWD}/Unit/testInterface.c
    ${FO_CWD}/Unit/testAgent.c
    ${FO_CWD}/Unit/testEvent.c
    ${FO_CWD}/Unit/testDatabase.c
    ${FO_CWD}/Unit/testScheduler.c
    ${FO_CWD}/Unit/testJob.c
    ${FO_CWD}/Unit/utils.c)
target_compile_definitions(test_scheduler
    PRIVATE
    _FILE_OFFSET_BITS=64
    AGENT_DIR="${CMAKE_CURRENT_BINARY_DIR}/.."
    LOG_DIR="${FO_LOGDIR}"
    FOSSDB_CONF="${FO_SYSCONFDIR}/Db.conf"
    PROJECT_USER="${FO_PROJECTUSER}"
    PROJECT_GROUP="${FO_PROJECTGROUP}"
    CLI_DIR="${FO_DESTDIR}${FO_BINDIR}"
    DEFAULT_SETUP="${FO_SYSCONFDIR}"
    PROCESS_NAME="test_scheduler"
)
target_include_directories(test_scheduler
    PRIVATE ${FO_TESTDIR}/lib/c ${FO_TESTDIR}/db/c ${glib_INCLUDE_DIRS}
    ${PostgreSQL_INCLUDE_DIRS} ${FO_CLIB_SRC} ${FO_CWD}/../agent ${FO_CWD}/Unit)
target_link_libraries(test_scheduler PRIVATE
    cunit focunit scheduler fodbreposysconf pcre)

configure_file("${FO_CWD}/agents/fossology.conf.in"
    ${CMAKE_CURRENT_BINARY_DIR}/gen/agents/fossology.conf
    NEWLINE_STYLE UNIX
    @ONLY)

add_custom_target(prepare_scheduler_functional_test ALL
    COMMAND ${CMAKE_COMMAND}
        -DIN_FILE_NAME="VERSION.in"
        -DINPUT_FILE_DIR="${FO_CMAKEDIR}"
        -DOUTPUT_FILE_DIR="${CMAKE_CURRENT_BINARY_DIR}/gen/agents/"
        -DOUT_FILE_NAME="VERSION"
        -DPROJECT_NAME="BUILD"
        -DFO_VERSION="${FO_VERSION}"
        -DFO_BRANCH="${FO_BRANCH}"
        -DFO_COMMIT_HASH="${FO_COMMIT_HASH}"
        -DFO_BUILD_DATE="${FO_BUILD_DATE}"
        -DFO_COMMIT_DATE="${FO_COMMIT_DATE}"
        -P ${FO_CMAKEDIR}/FoVersionFile.cmake
    DEPENDS "${FO_CMAKEDIR}/VERSION.in"
    BYPRODUCTS "${CMAKE_CURRENT_BINARY_DIR}/gen/agents/VERSION")

file(MAKE_DIRECTORY ${CMAKE_CURRENT_BINARY_DIR}/gen/agents/mods-enabled)

file(GLOB SCHEDULER_AGENTS ${FO_CWD}/agents/*.c)
foreach(SCHEDULER_AGENT ${SCHEDULER_AGENTS})
    get_filename_component(FILE_NAME ${SCHEDULER_AGENT} NAME_WE)
    add_executable(
        ${FILE_NAME}
        ${SCHEDULER_AGENT}
    )
    target_compile_definitions(
        ${FILE_NAME}
        PRIVATE
        _FILE_OFFSET_BITS=64 AGENT_DIR="${CMAKE_CURRENT_BINARY_DIR}/.."
    )
    target_include_directories(
        ${FILE_NAME}
        PRIVATE
        ${FO_TESTDIR}/lib/c ${FO_TESTDIR}/db/c ${glib_INCLUDE_DIRS}
        ${PostgreSQL_INCLUDE_DIRS} ${FO_CLIB_SRC} ${FO_CWD}/../agent ${FO_CWD}/Unit
    )
    target_link_libraries(
        ${FILE_NAME}
        PRIVATE fossology scheduler ${gobject_LIBRARIES}
        ${gthread_LIBRARIES} ${gio_LIBRARIES}
    )
    set_target_properties(${FILE_NAME} PROPERTIES
        RUNTIME_OUTPUT_DIRECTORY "${CMAKE_CURRENT_BINARY_DIR}/gen/agents/mods-enabled/${FILE_NAME}/agent"
    )
    file(GENERATE OUTPUT
        "${CMAKE_CURRENT_BINARY_DIR}/gen/agents/mods-enabled/${FILE_NAME}/${FILE_NAME}.conf"
        CONTENT "[default]\ncommand=${FILE_NAME}\nmax=-1\nspecial[]=\n"
    )
    file(GENERATE OUTPUT
        "${CMAKE_CURRENT_BINARY_DIR}/gen/agents/mods-enabled/${FILE_NAME}/VERSION"
        CONTENT "[${FILE_NAME}]\nVERSION=test\nCOMMIT_HASH=0000"
    )
endforeach()

add_test(scheduler_unit_test test_scheduler)

add_test(NAME scheduler_functional_test
    COMMAND /usr/bin/python3 -u ${CMAKE_CURRENT_LIST_DIR}/Functional/Functional.py
        --tests=${CMAKE_CURRENT_LIST_DIR}/Functional/Functional.xml
        --longest=${TEST_SUITE_LIMIT}
        --results=${CMAKE_CURRENT_BINARY_DIR}/gen/scheduler-Xunit.xml
)
