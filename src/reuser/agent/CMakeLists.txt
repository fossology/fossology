#[=======================================================================[
SPDX-License-Identifier: GPL-2.0-only
SPDX-FileCopyrightText: © 2021 Avinal Kumar <avinal.xlvii@gmail.com>
#]=======================================================================]

generate_version_php()

# Install generated version.php for UI includes (ui/reuser-plugin.php, ui/agent-reuser.php)
install(DIRECTORY ${CMAKE_CURRENT_BINARY_DIR}/gen/
    DESTINATION ${FO_MODDIR}/${PROJECT_NAME}/agent
    COMPONENT reuser
    FILES_MATCHING
    PATTERN version.php)

# C++ Agent — mirrors the ojo agent pattern.
set(FO_CWD ${CMAKE_CURRENT_SOURCE_DIR})
set(CMAKE_CXX_FLAGS "${CMAKE_CXX_FLAGS} ${FO_CXX_FLAGS}")

include_directories(
    ${glib_INCLUDE_DIRS}
    ${PostgreSQL_INCLUDE_DIRS}
    ${FO_CLIB_SRC}
    ${FO_CXXLIB_SRC}
)

if(NOT TARGET fossologyCPP)
    add_subdirectory(${FO_CXXLIB_SRC} buildcxx EXCLUDE_FROM_ALL)
endif()

add_executable(reuser_exec
    ${FO_CWD}/reuser.cc
    ${FO_CWD}/ReuserDatabaseHandler.cc
    ${FO_CWD}/ReuserState.cc
    ${FO_CWD}/ReuserUtils.cc
)

target_compile_definitions(reuser_exec
    PRIVATE
    _FILE_OFFSET_BITS=64
    DATADIR="${FO_MODDIR}"
    VERSION_S="${FO_VERSION}"
    COMMIT_HASH_S="${FO_COMMIT_HASH}"
)

target_include_directories(reuser_exec
    PRIVATE ${FO_CWD}
)

target_link_libraries(reuser_exec
    PRIVATE fossologyCPP ${icu-uc_LIBRARIES}
)

# NOTE: do NOT set OUTPUT_NAME reuser here.
# generate_version_php() creates add_custom_target(reuser ALL ...) using
# ${PROJECT_NAME}.  If OUTPUT_NAME matches that custom target name, CMake's
# Ninja generator creates two rules for the same output path and ninja fails
# with "multiple rules generate src/reuser/agent/reuser".
# RENAME reuser in install() is used instead so the installed binary has the
# correct basename (needed because fo_scheduler_connect derives the module
# name from argv[0]).
install(TARGETS reuser_exec
    RUNTIME
    DESTINATION ${FO_MODDIR}/${PROJECT_NAME}/agent
    RENAME reuser
    COMPONENT reuser)
