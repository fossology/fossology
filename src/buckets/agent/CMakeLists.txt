#[=======================================================================[
SPDX-License-Identifier: GPL-2.0-only
SPDX-FileCopyrightText: © 2021 Avinal Kumar <avinal.xlvii@gmail.com>
#]=======================================================================]

set(FO_CWD ${CMAKE_CURRENT_SOURCE_DIR})
set(CMAKE_C_FLAGS "${CMAKE_C_FLAGS} ${FO_C_FLAGS} -fPIC")

include_directories(
    ${glib_INCLUDE_DIRS}
    ${PostgreSQL_INCLUDE_DIRS}
)
if(NOT TARGET fossology)
    add_subdirectory(${FO_CLIB_SRC} buildc EXCLUDE_FROM_ALL)
endif()

add_executable(buckets "")
target_sources(buckets
    PRIVATE
    ${FO_CWD}/buckets.c
    ${FO_CWD}/validate.c
    ${FO_CWD}/inits.c
    ${FO_CWD}/liccache.c
    ${FO_CWD}/walk.c
    ${FO_CWD}/leaf.c
    ${FO_CWD}/match.c
    ${FO_CWD}/container.c
    ${FO_CWD}/child.c
    ${FO_CWD}/write.c)
target_compile_definitions(buckets
    PRIVATE
    _FILE_OFFSET_BITS=64
    PROJECTSTATEDIR="${FO_PROJECTSTATEDIR}"
    DATADIR="${FO_DATADIR}"
    VERSION_S="${FO_VERSION}"
    COMMIT_HASH_S="${FO_COMMIT_HASH}")
target_link_libraries(buckets PRIVATE fossology)

install(TARGETS buckets
    RUNTIME
    DESTINATION ${FO_MODDIR}/${PROJECT_NAME}/agent 
    COMPONENT buckets)
