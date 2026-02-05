#[=======================================================================[
SPDX-License-Identifier: GPL-2.0-only
SPDX-FileCopyrightText: © 2021 Avinal Kumar <avinal.xlvii@gmail.com>
#]=======================================================================]

set(FO_CWD ${CMAKE_CURRENT_SOURCE_DIR})
set(CMAKE_C_FLAGS "${CMAKE_C_FLAGS} ${FO_C_FLAGS}")

include_directories(
    ${glib_INCLUDE_DIRS}
    ${PostgreSQL_INCLUDE_DIRS})

if(NOT TARGET fossology)
    add_subdirectory(${FO_CLIB_SRC} buildc EXCLUDE_FROM_ALL)
endif()

## maintagent is defined in src/maintagent; only build maintagent2 here
add_executable(maintagent2 "")
target_sources(maintagent2
    PRIVATE
    ${FO_CWD}/maintagent.c
    ${FO_CWD}/utils.c
    ${FO_CWD}/process.c
    ${FO_CWD}/usage.c)
target_compile_definitions(maintagent2
    PRIVATE
    PROJECTSTATEDIR="${FO_PROJECTSTATEDIR}"
    DATADIR="${FO_DATADIR}"
    _FILE_OFFSET_BITS=64
    VERSION_S="${FO_VERSION}"
    COMMIT_HASH_S="${FO_COMMIT_HASH}")
target_link_libraries(maintagent2 PRIVATE fossology)
# Build a small adapter wrapper specific to maintagent2
target_sources(maintagent2 PRIVATE ${CMAKE_SOURCE_DIR}/src/lib/c/libfossdb_adapter.c)
install(TARGETS maintagent2
    RUNTIME
    DESTINATION ${FO_MODDIR}/${PROJECT_NAME}/agent
    COMPONENT maintagent2)

install(TARGETS maintagent
    RUNTIME
    DESTINATION ${FO_MODDIR}/${PROJECT_NAME}/agent
    COMPONENT maintagent)
