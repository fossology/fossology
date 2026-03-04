#[=======================================================================[
SPDX-License-Identifier: GPL-2.0-only
SPDX-FileCopyrightText: © 2024 Siemens AG
#]=======================================================================]

set(FO_CWD ${CMAKE_CURRENT_SOURCE_DIR})
set(CMAKE_CXX_FLAGS "${CMAKE_CXX_FLAGS} ${FO_CXX_FLAGS} -fopenmp")

include_directories(
        ${glib_INCLUDE_DIRS}
        ${PostgreSQL_INCLUDE_DIRS}
        ${FO_CLIB_SRC}
        ${jsoncpp_INCLUDE_DIRS}
        ${yamlcpp_INCLUDE_DIRS}
        ${FO_CXXLIB_SRC}
)

if (NOT TARGET fossologyCPP)
    add_subdirectory(${FO_CXXLIB_SRC} buildcxx EXCLUDE_FROM_ALL)
endif ()

add_library(compatibility EXCLUDE_FROM_ALL "")
add_library(compatibility_cov EXCLUDE_FROM_ALL "")
add_executable(compatibility_exec "")
add_executable(compatibility_cov_exec EXCLUDE_FROM_ALL "")
foreach (FO_COMP_TARGET compatibility compatibility_exec compatibility_cov
        compatibility_cov_exec)
    target_compile_definitions(${FO_COMP_TARGET}
            PRIVATE
            _FILE_OFFSET_BITS=64
            DATADIR="${FO_MODDIR}"
            VERSION_S="${FO_VERSION}"
            COMMIT_HASH_S="${FO_COMMIT_HASH}"
            )
    target_sources(${FO_COMP_TARGET}
            PRIVATE
            ${FO_CWD}/CompatibilityAgent.cc
            ${FO_CWD}/CompatibilityDatabaseHandler.cc
            ${FO_CWD}/compatibility.cc
            ${FO_CWD}/CompatibilityState.cc
            ${FO_CWD}/CompatibilityUtils.cc
            ${FO_CWD}/CompatibilityStatus.hpp)
    if (${FO_COMP_TARGET} MATCHES "^compatibility_cov")
        target_compile_options(${FO_COMP_TARGET} PRIVATE ${FO_COV_FLAGS})
    endif ()
    target_link_libraries(${FO_COMP_TARGET}
            PRIVATE fossologyCPP ${icu-uc_LIBRARIES} ${Boost_LIBRARIES}
            ${jsoncpp_LIBRARIES} yaml-cpp)
    if (${FO_COMP_TARGET} MATCHES "_exec$")
        string(REPLACE "_exec" "" FO_COMP_TARGET_R ${FO_COMP_TARGET})
        set_target_properties(${FO_COMP_TARGET}
                PROPERTIES OUTPUT_NAME ${FO_COMP_TARGET_R})
    endif ()
endforeach ()

install(TARGETS compatibility_exec
        RUNTIME
        DESTINATION ${FO_MODDIR}/${PROJECT_NAME}/agent
        COMPONENT compatibility)
