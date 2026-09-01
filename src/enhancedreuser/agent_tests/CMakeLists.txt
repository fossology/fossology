#[=======================================================================[
SPDX-License-Identifier: GPL-2.0-only
SPDX-FileCopyrightText: © 2026 Saksham Mishra
#]=======================================================================]

set(FO_CWD ${CMAKE_CURRENT_SOURCE_DIR})

if(NOT TARGET phpunit)
    prepare_phpunit()
endif()

set(CMAKE_CXX_FLAGS "${CMAKE_CXX_FLAGS} ${FO_CXX_FLAGS} -Wall")

pkg_check_modules(jsoncpp REQUIRED jsoncpp)

add_executable(test_enhancedreuser "")
target_sources(test_enhancedreuser
    PRIVATE
        ${FO_CWD}/Unit/run_tests.cc
        ${FO_CWD}/Unit/MockEnhancedReuserDatabaseHandler.cc
        ${FO_CWD}/Unit/test_types.cc
        ${FO_CWD}/Unit/test_helpers.cc
        ${FO_CWD}/Unit/test_metrics.cc
        ${FO_CWD}/Unit/test_worker.cc
        ${FO_CWD}/Unit/test_diff_classifier.cc
        ${FO_CWD}/../agent/EnhancedReuserDatabaseHandler.cc
        ${FO_CWD}/../agent/EnhancedReuserState.cc
        ${FO_CWD}/../agent/EnhancedReuserUtils.cc
        ${FO_CWD}/../agent/CommentExtractor.cc
        ${FO_CWD}/../agent/DiffClassifier.cc
        ${FO_CWD}/../agent/KotobaChecker.cc
        ${FO_CWD}/../agent/NomosChecker.cc
        ${FO_CWD}/../agent/OjoChecker.cc
        ${FO_CWD}/Unit/test_nomos_checker.cc
        ${FO_CWD}/Unit/test_ojo_checker.cc
)

target_compile_definitions(test_enhancedreuser
    PRIVATE
        _FILE_OFFSET_BITS=64
        DATADIR="${FO_MODDIR}"
        VERSION_S="${FO_VERSION}"
        COMMIT_HASH_S="${FO_COMMIT_HASH}"
)

target_include_directories(test_enhancedreuser
    PRIVATE
        ${glib_INCLUDE_DIRS}
        ${PostgreSQL_INCLUDE_DIRS}
        ${FO_CXXLIB_SRC}
        ${FO_CLIB_SRC}
        ${jsoncpp_INCLUDE_DIRS}
        ${FO_CWD}/../agent
        ${FO_CWD}/Unit
)

target_link_libraries(test_enhancedreuser
    PRIVATE
        ${cppunit_LIBRARIES}
        fossologyCPP
        ${jsoncpp_LIBRARIES}
        ${icu-uc_LIBRARIES}
)

add_test(enhancedreuser_unit_test test_enhancedreuser)
