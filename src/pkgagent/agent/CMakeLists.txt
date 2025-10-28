#[=======================================================================[
SPDX-License-Identifier: GPL-2.0-only
SPDX-FileCopyrightText: © 2021 Avinal Kumar <avinal.xlvii@gmail.com>
#]=======================================================================]

set(FO_CWD ${CMAKE_CURRENT_SOURCE_DIR})
set(CMAKE_C_FLAGS "${CMAKE_C_FLAGS} ${FO_C_FLAGS}")

include_directories(
    ${glib_INCLUDE_DIRS}
    ${PostgreSQL_INCLUDE_DIRS}
    ${rpm_INCLUDE_DIRS}
)

if(NOT TARGET fossology)
    add_subdirectory(${FO_CLIB_SRC} buildc EXCLUDE_FROM_ALL)
endif()

add_executable(pkgagent "")
target_sources(pkgagent
    PRIVATE ${FO_CWD}/main.c ${FO_CWD}/pkgagent.c)
target_compile_definitions(pkgagent 
    PRIVATE 
    _FILE_OFFSET_BITS=64
    VERSION_S="${FO_VERSION}"
    COMMIT_HASH_S="${FO_COMMIT_HASH}")

if(FO_RPM_CRYPTO_H_HEADER)
  target_compile_definitions(pkgagent PRIVATE HAVE_RPM_CRYPTO_H=1)
endif()

target_link_libraries(pkgagent PRIVATE fossology ${rpm_LIBRARIES})

install(TARGETS pkgagent
    RUNTIME
    DESTINATION ${FO_MODDIR}/${PROJECT_NAME}/agent 
    COMPONENT pkgagent)
