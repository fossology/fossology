#[=======================================================================[
SPDX-License-Identifier: GPL-2.0-only
SPDX-FileCopyrightText: © 2021 Avinal Kumar <avinal.xlvii@gmail.com>
#]=======================================================================]

set(FO_CWD ${CMAKE_CURRENT_SOURCE_DIR})
set(CMAKE_C_FLAGS "${CMAKE_C_FLAGS} -Wall")

if(NOT TARGET fossology)
    add_subdirectory(${FO_CLIB_SRC} buildc EXCLUDE_FROM_ALL)
endif()

include_directories(
    ${FO_CWD}
    ${glib_INCLUDE_DIRS}
    ${PostgreSQL_INCLUDE_DIRS}
    ${FO_CLIB_SRC})

add_library(scheduler "")
add_library(scheduler_cov EXCLUDE_FROM_ALL "")
add_executable(fo_scheduler "")
add_executable(fo_cli ${FO_CWD}/fo_cli.c)
target_link_libraries(fo_cli PRIVATE fossology)
target_compile_definitions(fo_cli
    PRIVATE
    LOG_DIR="${FO_LOGDIR}"
    FOSSDB_CONF="${FO_SYSCONFDIR}/Db.conf"
    PROJECT_USER="${FO_PROJECTUSER}"
    PROJECT_GROUP="${FO_PROJECTGROUP}"
    CLI_DIR="${FO_DESTDIR}${FO_BINDIR}"
    DEFAULT_SETUP="${FO_SYSCONFDIR}"
    _FILE_OFFSET_BITS=64
)

foreach(FO_SCH_TARGET scheduler fo_scheduler scheduler_cov)
    target_compile_definitions(${FO_SCH_TARGET}
        PRIVATE
        LOG_DIR="${FO_LOGDIR}"
        FOSSDB_CONF="${FO_SYSCONFDIR}/Db.conf"
        PROJECT_USER="${FO_PROJECTUSER}"
        PROJECT_GROUP="${FO_PROJECTGROUP}"
        CLI_DIR="${FO_DESTDIR}${FO_BINDIR}"
        DEFAULT_SETUP="${FO_SYSCONFDIR}"
        _FILE_OFFSET_BITS=64
    )
    if(${FO_SCH_TARGET} STREQUAL "fo_scheduler")
        set(SCH_XSRC ${FO_SCH_TARGET}.c)
        target_link_libraries(${FO_SCH_TARGET}
            PRIVATE fossology scheduler ${gobject_LIBRARIES}
                ${gthread_LIBRARIES} ${gio_LIBRARIES})
        target_compile_options(${FO_SCH_TARGET}
            PRIVATE "-DPROCESS_NAME=${FO_SCH_TARGET}")
    else()
        set(SCH_XSRC "")
        target_link_libraries(${FO_SCH_TARGET}
            PRIVATE fossology ${gobject_LIBRARIES}
                ${gthread_LIBRARIES} ${gio_LIBRARIES})
    endif()
    target_sources(${FO_SCH_TARGET}
        PRIVATE
            ${FO_CWD}/scheduler.c
            ${FO_CWD}/event.c
            ${FO_CWD}/agent.c
            ${FO_CWD}/host.c
            ${FO_CWD}/database.c
            ${FO_CWD}/job.c
            ${FO_CWD}/logging.c
            ${FO_CWD}/emailformatter.c
            ${FO_CWD}/${SCH_XSRC}
            "${FO_CWD}/interface.c")
endforeach()

target_compile_options(scheduler_cov PRIVATE ${FO_COV_FLAGS})
target_include_directories(scheduler_cov PRIVATE ${FO_CWD})
target_include_directories(scheduler
    PUBLIC $<BUILD_INTERFACE:${CMAKE_CURRENT_LIST_DIR}>)
add_custom_target(initfile ALL
COMMAND ${CMAKE_COMMAND}
    -DIN_FILE_NAME="fossology.in"
    -DOUT_FILE_NAME="fossology"
    -DINPUT_FILE_DIR="${FO_CWD}/defconf/init.d"
    -DOUTPUT_FILE_DIR="${CMAKE_CURRENT_BINARY_DIR}/gen/init.d"
    -DFO_SYSCONFDIR="${FO_SYSCONFDIR}"
    -DFO_PROJECT="${FO_PROJECT}"
    -DFO_MODDIR="${FO_MODDIR}"
    -DFO_INITDIR="${FO_INITDIR}"
    -P ${FO_CMAKEDIR}/FoVersionFile.cmake
    COMMENT "Generating init script for the ${FO_PROJECT} scheduler"
    DEPENDS "${FO_CWD}/defconf/init.d/fossology.in"
    BYPRODUCTS ${CMAKE_CURRENT_BINARY_DIR}/gen/init.d)

# Compile scheduler with C standard C11
set_property(TARGET fo_scheduler PROPERTY C_STANDARD 11)
set_property(TARGET fo_cli PROPERTY C_STANDARD 11)
set_property(TARGET scheduler PROPERTY C_STANDARD 11)

install(TARGETS fo_cli fo_scheduler
    RUNTIME
    DESTINATION ${FO_MODDIR}/${PROJECT_NAME}/agent
    COMPONENT scheduler)

install(PROGRAMS ${CMAKE_CURRENT_BINARY_DIR}/gen/init.d/fossology
    DESTINATION ${FO_INITDIR}/init.d
    COMPONENT scheduler)

install(FILES ${FO_DEBDIR}/scheduler/fossology-scheduler.logrotate
    DESTINATION ${FO_INITDIR}/logrotate.d
    RENAME fossology-scheduler
    OPTIONAL
    COMPONENT scheduler)

add_symlink()
