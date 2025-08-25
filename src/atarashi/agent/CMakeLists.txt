#[=======================================================================[
SPDX-License-Identifier: GPL-2.0-only
SPDX-FileCopyrightText: © 2025 Rajul Jha <rajuljha49@gmail.com>
#]=======================================================================]

set(FO_CWD ${CMAKE_CURRENT_SOURCE_DIR})
set(CMAKE_CXX_FLAGS "${CMAKE_CXX_FLAGS} ${FO_CXX_FLAGS} -fopenmp")

include_directories(
    ${glib_INCLUDE_DIRS}
    ${PostgreSQL_INCLUDE_DIRS}
    ${FO_CLIB_SRC}
    ${jsoncpp_INCLUDE_DIRS}
    ${FO_CXXLIB_SRC})

if(NOT TARGET fossologyCPP)
    add_subdirectory(${FO_CXXLIB_SRC} buildcxx EXCLUDE_FROM_ALL)
endif()

add_library(atarashi EXCLUDE_FROM_ALL "")
add_library(atarashi_cov EXCLUDE_FROM_ALL "")
add_executable(atarashi_exec "")
add_executable(atarashi_cov_exec EXCLUDE_FROM_ALL "")

foreach(FO_ATARASHI_TARGET atarashi atarashi_exec atarashi_cov atarashi_cov_exec)
    target_compile_definitions(${FO_ATARASHI_TARGET}
        PRIVATE
        _FILE_OFFSET_BITS=64
        DATADIR="${FO_MODDIR}"
        VERSION_S="${FO_VERSION}"
        COMMIT_HASH_S="${FO_COMMIT_HASH}"
    )
    target_sources(${FO_ATARASHI_TARGET}
        PRIVATE
        ${FO_CWD}/atarashi.cc
        ${FO_CWD}/atarashiwrapper.cc
        ${FO_CWD}/confighelper.cc
        ${FO_CWD}/databasehandler.cc
        ${FO_CWD}/licensematch.cc
        ${FO_CWD}/state.cc
        ${FO_CWD}/utils.cc)
    if(${FO_ATARASHI_TARGET} MATCHES "^atarashi_cov")
        target_compile_options(${FO_ATARASHI_TARGET} PRIVATE ${FO_COV_FLAGS})
    endif()
    target_link_libraries(${FO_ATARASHI_TARGET}
        PRIVATE fossologyCPP ${icu-uc_LIBRARIES} ${Boost_LIBRARIES} jsoncpp_lib)
    if(${FO_ATARASHI_TARGET} MATCHES "_exec$")
        string(REPLACE "_exec" "" FO_ATARASHI_TARGET_R ${FO_ATARASHI_TARGET})
        set_target_properties(${FO_ATARASHI_TARGET}
            PROPERTIES OUTPUT_NAME ${FO_ATARASHI_TARGET_R})
    endif()
endforeach()

install(TARGETS atarashi_exec
    RUNTIME
    DESTINATION ${FO_MODDIR}/${PROJECT_NAME}/agent
    COMPONENT atarashi)

install(FILES
    run_atarashi_scan_on_files.py
    DESTINATION ${FO_MODDIR}/${PROJECT_NAME}/agent
    COMPONENT atarashi)