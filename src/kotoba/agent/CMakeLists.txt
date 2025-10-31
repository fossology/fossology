#[=======================================================================[
SPDX-License-Identifier: GPL-2.0-only
SPDX-FileCopyrightText: © 2025 Harshit Gandhi <gandhiharshit716@gmail.com>
#]=======================================================================]

set(FO_CWD ${CMAKE_CURRENT_SOURCE_DIR})
set(CMAKE_C_FLAGS "${CMAKE_C_FLAGS} ${FO_C_FLAGS} -std=c99 -Wextra -fopenmp")

include_directories(
    ${glib_INCLUDE_DIRS}
    ${PostgreSQL_INCLUDE_DIRS})

if(NOT TARGET fossology)
    add_subdirectory(${FO_CLIB_SRC} buildc EXCLUDE_FROM_ALL)
endif()

add_executable(kotobaSquareBuilder EXCLUDE_FROM_ALL buildSquareVisitor.c)
target_link_libraries(kotobaSquareBuilder PRIVATE fossology m magic)
add_custom_command(
    OUTPUT _squareVisitor.c _squareVisitor.h
    COMMAND ${CMAKE_COMMAND} -E copy_directory 
        ${FO_CWD}/generator $<TARGET_FILE_DIR:kotobaSquareBuilder>
    COMMAND ${CMAKE_COMMAND} -E copy $<TARGET_FILE:kotobaSquareBuilder> $<TARGET_FILE_DIR:kotobaSquareBuilder>/squareBuilder
    COMMAND $<TARGET_FILE_DIR:kotobaSquareBuilder>/genSquareVisitor
    WORKING_DIRECTORY $<TARGET_FILE_DIR:kotobaSquareBuilder>
    DEPENDS kotobaSquareBuilder)

add_library(kotoba EXCLUDE_FROM_ALL "")
add_library(kotoba_cov EXCLUDE_FROM_ALL "")
add_executable(kotoba_exec "")
add_executable(kotobabulk "")
foreach(FO_KOTOBA_TARGET kotoba kotoba_exec kotoba_cov kotobabulk)
    if(${FO_KOTOBA_TARGET} STREQUAL "kotoba")
        set(KOTOBA_XSRC "kotoba.c" )
    elseif(${FO_KOTOBA_TARGET} STREQUAL "kotobabulk")
        set(KOTOBA_XSRC "kotobabulk.c" )
    endif()
    target_sources(${FO_KOTOBA_TARGET}
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
        ${FO_CWD}/${KOTOBA_XSRC}
        $<TARGET_FILE_DIR:kotobaSquareBuilder>/_squareVisitor.c)
    target_link_libraries(${FO_KOTOBA_TARGET} PRIVATE fossology magic)
    target_compile_definitions(${FO_KOTOBA_TARGET} 
        PRIVATE _FILE_OFFSET_BITS=64
        VERSION_S="${FO_VERSION}"
        COMMIT_HASH_S="${FO_COMMIT_HASH}")
    target_include_directories(${FO_KOTOBA_TARGET} 
        PRIVATE $<TARGET_FILE_DIR:kotobaSquareBuilder>)
endforeach()

set_target_properties(kotoba_exec PROPERTIES OUTPUT_NAME kotoba)

install(TARGETS kotoba_exec
    RUNTIME
    DESTINATION ${FO_MODDIR}/${PROJECT_NAME}/agent 
    COMPONENT kotoba)

install(TARGETS kotobabulk
    RUNTIME
    DESTINATION ${FO_MODDIR}/kotobabulk/agent 
    COMPONENT kotobabulk)
