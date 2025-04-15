#[=======================================================================[
SPDX-License-Identifier: GPL-2.0-only
SPDX-FileCopyrightText: © 2022 Gaurav Mishra <mishra.gaurav@siemens.com>
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

add_library(scancode EXCLUDE_FROM_ALL "")
add_library(scancode_cov EXCLUDE_FROM_ALL "")
add_executable(scancode_exec "")
add_executable(scancode_cov_exec EXCLUDE_FROM_ALL "")
foreach(FO_SCANCODE_TARGET scancode scancode_exec scancode_cov scancode_cov_exec)
    target_compile_definitions(${FO_SCANCODE_TARGET}
        PRIVATE
        _FILE_OFFSET_BITS=64
        DATADIR="${FO_MODDIR}"
        VERSION_S="${FO_VERSION}"
        COMMIT_HASH_S="${FO_COMMIT_HASH}"
    )
    target_sources(${FO_SCANCODE_TARGET}
        PRIVATE
        ${FO_CWD}/match.cc
        ${FO_CWD}/scancode_dbhandler.cc
        ${FO_CWD}/scancode_state.cc
        ${FO_CWD}/scancode_utils.cc
        ${FO_CWD}/scancode_wrapper.cc
        ${FO_CWD}/scancode.cc)
    if(${FO_SCANCODE_TARGET} MATCHES "^scancode_cov")
        target_compile_options(${FO_SCANCODE_TARGET} PRIVATE ${FO_COV_FLAGS})
    endif()
    target_link_libraries(${FO_SCANCODE_TARGET}
        PRIVATE fossologyCPP ICU::uc ICU::i18n ${Boost_LIBRARIES} jsoncpp_lib)
    if(${FO_SCANCODE_TARGET} MATCHES "_exec$")
        string(REPLACE "_exec" "" FO_SCANCODE_TARGET_R ${FO_SCANCODE_TARGET})
        set_target_properties(${FO_SCANCODE_TARGET}
            PROPERTIES OUTPUT_NAME ${FO_SCANCODE_TARGET_R})
    endif()
endforeach()

install(TARGETS scancode_exec
    RUNTIME
    DESTINATION ${FO_MODDIR}/${PROJECT_NAME}/agent
    COMPONENT scancode)

install(FILES
    runscanonfiles.py
    DESTINATION ${FO_MODDIR}/${PROJECT_NAME}/agent
    COMPONENT scancode)
