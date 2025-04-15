#[=======================================================================[
SPDX-License-Identifier: GPL-2.0-only
SPDX-FileCopyrightText: © 2021 Avinal Kumar <avinal.xlvii@gmail.com>
#]=======================================================================]


set(FO_CWD ${CMAKE_CURRENT_SOURCE_DIR})
set(CMAKE_CXX_FLAGS "${CMAKE_CXX_FLAGS} ${FO_CXX_FLAGS}")

include_directories(
    ${glib_INCLUDE_DIRS}
    ${PostgreSQL_INCLUDE_DIRS}
    ${FO_CLIB_SRC}
    ${FO_CXXLIB_SRC})

if(NOT TARGET fossologyCPP)
    add_subdirectory(${FO_CXXLIB_SRC} buildcxx EXCLUDE_FROM_ALL)
endif()

add_library(ninka EXCLUDE_FROM_ALL "")
add_library(ninka_cov EXCLUDE_FROM_ALL "")
add_executable(ninka_exec "")
add_executable(ninka_cov_exec EXCLUDE_FROM_ALL "")
foreach(FO_NINKA_TARGET ninka ninka_exec ninka_cov ninka_cov_exec)
    target_compile_definitions(${FO_NINKA_TARGET}
        PRIVATE
        _FILE_OFFSET_BITS=64
        DATADIR="${FO_MODDIR}"
        VERSION_S="${FO_VERSION}"
        COMMIT_HASH_S="${FO_COMMIT_HASH}"
    )
    target_sources(${FO_NINKA_TARGET}
        PRIVATE
        ${FO_CWD}/databasehandler.cc
        ${FO_CWD}/licensematch.cc
        ${FO_CWD}/ninka.cc
        ${FO_CWD}/ninkawrapper.cc
        ${FO_CWD}/state.cc
        ${FO_CWD}/utils.cc)
    if(${FO_NINKA_TARGET} MATCHES "^ninka_cov")
        target_compile_options(${FO_NINKA_TARGET}
            PUBLIC ${FO_COV_FLAGS})
    endif()
    if(${FO_NINKA_TARGET} MATCHES "_exec$")
        target_link_libraries(${FO_NINKA_TARGET}
            PRIVATE fossologyCPP stdc++ icuuc icudata gcov OpenMP::OpenMP_CXX)
        string(REPLACE "_exec" "" FO_NINKA_TARGET_R ${FO_NINKA_TARGET})
        set_target_properties(${FO_NINKA_TARGET}
            PROPERTIES OUTPUT_NAME ${FO_NINKA_TARGET_R})
    endif()
endforeach()

install(TARGETS ninka_exec
    RUNTIME
    DESTINATION ${FO_MODDIR}/${PROJECT_NAME}/agent
    COMPONENT ninka)
