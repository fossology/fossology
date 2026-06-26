#[=======================================================================[
SPDX-License-Identifier: GPL-2.0-only
SPDX-FileCopyrightText: © 2021 Avinal Kumar <avinal.xlvii@gmail.com>
#]=======================================================================]

generate_version_php(reuser_version_php)

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

add_executable(reuser
    ${FO_CWD}/reuser.cc
    ${FO_CWD}/ReuserDatabaseHandler.cc
    ${FO_CWD}/ReuserState.cc
    ${FO_CWD}/ReuserUtils.cc
)

target_compile_definitions(reuser
    PRIVATE
    _FILE_OFFSET_BITS=64
    DATADIR="${FO_MODDIR}"
    VERSION_S="${FO_VERSION}"
    COMMIT_HASH_S="${FO_COMMIT_HASH}"
)

target_include_directories(reuser
    PRIVATE ${FO_CWD}
)

target_link_libraries(reuser
    PRIVATE fossologyCPP ${icu-uc_LIBRARIES}
)

install(TARGETS reuser
    RUNTIME
    DESTINATION ${FO_MODDIR}/${PROJECT_NAME}/agent
    COMPONENT reuser)
