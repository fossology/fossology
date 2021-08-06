#[=======================================================================[
SPDX-License-Identifier: GPL-2.0-only
SPDX-FileCopyrightText: © 2021 Avinal Kumar <avinal.xlvii@gmail.com>
#]=======================================================================]

set(FO_CWD ${CMAKE_CURRENT_SOURCE_DIR})

set(CMAKE_C_FLAGS "${CMAKE_C_FLAGS} ${FO_C_FLAGS} -fPIC")

include_directories(
    ${glib_INCLUDE_DIRS}
    ${PostgreSQL_INCLUDE_DIRS})

if(NOT TARGET fossology)
    add_subdirectory(${FO_CLIB_SRC} buildc EXCLUDE_FROM_ALL)
endif()

add_executable(demomod "")
target_sources(demomod
    PRIVATE
    ${FO_CWD}/usage.c
    ${FO_CWD}/demomod.c
    ${FO_CWD}/utils.c
    ${FO_CWD}/process.c)
    
target_compile_definitions(demomod PRIVATE _FILE_OFFSET_BITS=64)
target_link_libraries(demomod PRIVATE fossology)

install(TARGETS demomod
    RUNTIME
    DESTINATION ${FO_MODDIR}/${PROJECT_NAME}/agent 
    COMPONENT demomod)
