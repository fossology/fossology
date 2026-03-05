#[=======================================================================[
SPDX-License-Identifier: GPL-2.0-only
SPDX-FileCopyrightText: © 2026 Fossology contributors
#]=======================================================================]

set(FO_CWD ${CMAKE_CURRENT_SOURCE_DIR})
set(CMAKE_C_FLAGS "${CMAKE_C_FLAGS} ${FO_C_FLAGS}")

include_directories(
    ${glib_INCLUDE_DIRS}
    ${PostgreSQL_INCLUDE_DIRS}
    ${FO_CLIB_SRC}
)

find_package(PkgConfig REQUIRED)
pkg_check_modules(JSONC REQUIRED json-c)
include_directories(${JSONC_INCLUDE_DIRS})

pkg_check_modules(LIBARCHIVE libarchive)
if(LIBARCHIVE_FOUND)
    include_directories(${LIBARCHIVE_INCLUDE_DIRS})
    add_compile_definitions(HAVE_LIBARCHIVE=1)
endif()

if(NOT TARGET fossology)
    add_subdirectory(${FO_CLIB_SRC} buildc EXCLUDE_FROM_ALL)
endif()

add_executable(containeragent "")
target_sources(containeragent
    PRIVATE ${FO_CWD}/main.c ${FO_CWD}/containeragent.c ${FO_CWD}/containerpkg.c)

target_compile_definitions(containeragent
    PRIVATE
    _FILE_OFFSET_BITS=64
    VERSION_S="${FO_VERSION}"
    COMMIT_HASH_S="${FO_COMMIT_HASH}")

if(LIBARCHIVE_FOUND)
    target_link_libraries(containeragent
        PRIVATE fossology ${JSONC_LIBRARIES} ${LIBARCHIVE_LIBRARIES})
else()
    target_link_libraries(containeragent
        PRIVATE fossology ${JSONC_LIBRARIES})
endif()

install(TARGETS containeragent
    RUNTIME
    DESTINATION ${FO_MODDIR}/${PROJECT_NAME}/agent
    COMPONENT containeragent)
