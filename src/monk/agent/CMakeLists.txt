#[=======================================================================[
SPDX-License-Identifier: GPL-2.0-only
SPDX-FileCopyrightText: © 2021 Avinal Kumar <avinal.xlvii@gmail.com>
#]=======================================================================]

set(FO_CWD ${CMAKE_CURRENT_SOURCE_DIR})
set(CMAKE_C_FLAGS "${CMAKE_C_FLAGS} ${FO_C_FLAGS} -std=c99 -Wextra")

include_directories(
    ${glib_INCLUDE_DIRS}
    ${PostgreSQL_INCLUDE_DIRS})

if(NOT TARGET fossology)
    add_subdirectory(${FO_CLIB_SRC} buildc EXCLUDE_FROM_ALL)
endif()

add_executable(squareBuilder EXCLUDE_FROM_ALL buildSquareVisitor.c)
target_link_libraries(squareBuilder PRIVATE fossology m magic)
add_custom_command(
    OUTPUT _squareVisitor.c _squareVisitor.h
    COMMAND ${CMAKE_COMMAND} -E copy_directory
        ${FO_CWD}/generator $<TARGET_FILE_DIR:squareBuilder>
    COMMAND $<TARGET_FILE_DIR:squareBuilder>/genSquareVisitor
    WORKING_DIRECTORY $<TARGET_FILE_DIR:squareBuilder>
    DEPENDS squareBuilder)

add_library(monk EXCLUDE_FROM_ALL "")
add_library(monk_cov EXCLUDE_FROM_ALL "")
add_executable(monk_exec "")
add_executable(monkbulk "")
foreach(FO_MONK_TARGET monk monk_exec monk_cov monkbulk)
    if(${FO_MONK_TARGET} STREQUAL "monk")
        set(MONK_XSRC "monk.c" )
    elseif(${FO_MONK_TARGET} STREQUAL "monkbulk")
        set(MONK_XSRC "monkbulk.c" )
    endif()
    target_sources(${FO_MONK_TARGET}
        PRIVATE
        ${FO_CWD}/string_operations.c
        ${FO_CWD}/file_operations.c
        ${FO_CWD}/database.c
        ${FO_CWD}/encoding.c
        ${FO_CWD}/license.c
        ${FO_CWD}/match.c
        ${FO_CWD}/highlight.c
        ${FO_CWD}/hash.c
        ${FO_CWD}/diff.c
        ${FO_CWD}/common.c
        ${FO_CWD}/cli.c
        ${FO_CWD}/scheduler.c
        ${FO_CWD}/serialize.c
        ${FO_CWD}/${MONK_XSRC}
        $<TARGET_FILE_DIR:squareBuilder>/_squareVisitor.c)
    target_link_libraries(${FO_MONK_TARGET} PRIVATE fossology magic OpenMP::OpenMP_C)
    target_compile_definitions(${FO_MONK_TARGET}
        PRIVATE _FILE_OFFSET_BITS=64
        VERSION_S="${FO_VERSION}"
        COMMIT_HASH_S="${FO_COMMIT_HASH}")
    target_include_directories(${FO_MONK_TARGET}
        PRIVATE $<TARGET_FILE_DIR:squareBuilder>)
endforeach()

set_target_properties(monk_exec PROPERTIES OUTPUT_NAME monk)

install(TARGETS monk_exec
    RUNTIME
    DESTINATION ${FO_MODDIR}/${PROJECT_NAME}/agent
    COMPONENT monk)

install(TARGETS monkbulk
    RUNTIME
    DESTINATION ${FO_MODDIR}/${PROJECT_NAME}bulk/agent
    COMPONENT monkbulk)
