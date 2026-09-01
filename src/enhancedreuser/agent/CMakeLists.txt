#[=======================================================================[
SPDX-License-Identifier: GPL-2.0-only
SPDX-FileCopyrightText: © 2026 Saksham Mishra
#]=======================================================================]

generate_version_php(enhancedreuser_version_php)

install(DIRECTORY ${CMAKE_CURRENT_BINARY_DIR}/gen/
    DESTINATION ${FO_MODDIR}/${PROJECT_NAME}/agent
    COMPONENT enhancedreuser
    FILES_MATCHING
    PATTERN version.php)

set(FO_CWD ${CMAKE_CURRENT_SOURCE_DIR})
set(CMAKE_CXX_FLAGS "${CMAKE_CXX_FLAGS} ${FO_CXX_FLAGS}")

pkg_check_modules(jsoncpp REQUIRED jsoncpp)

include_directories(
    ${glib_INCLUDE_DIRS}
    ${PostgreSQL_INCLUDE_DIRS}
    ${FO_CLIB_SRC}
    ${FO_CXXLIB_SRC}
    ${jsoncpp_INCLUDE_DIRS}
)

if(NOT TARGET fossologyCPP)
    add_subdirectory(${FO_CXXLIB_SRC} buildcxx EXCLUDE_FROM_ALL)
endif()

add_executable(enhancedreuser
    ${FO_CWD}/enhancedreuser.cc
    ${FO_CWD}/EnhancedReuserDatabaseHandler.cc
    ${FO_CWD}/EnhancedReuserState.cc
    ${FO_CWD}/EnhancedReuserUtils.cc
    ${FO_CWD}/CommentExtractor.cc
    ${FO_CWD}/DiffClassifier.cc
    ${FO_CWD}/KotobaChecker.cc
    ${FO_CWD}/NomosChecker.cc
    ${FO_CWD}/OjoChecker.cc
)

target_compile_definitions(enhancedreuser
    PRIVATE
    _FILE_OFFSET_BITS=64
    DATADIR="${FO_MODDIR}"
    VERSION_S="${FO_VERSION}"
    COMMIT_HASH_S="${FO_COMMIT_HASH}"
)

target_include_directories(enhancedreuser
    PRIVATE ${FO_CWD} ${jsoncpp_INCLUDE_DIRS}
)

target_link_libraries(enhancedreuser
    PRIVATE fossologyCPP ${jsoncpp_LIBRARIES} ${icu-uc_LIBRARIES}
)

install(TARGETS enhancedreuser
    RUNTIME
    DESTINATION ${FO_MODDIR}/${PROJECT_NAME}/agent
    COMPONENT enhancedreuser)
