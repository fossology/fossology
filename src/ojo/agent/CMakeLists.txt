#[=======================================================================[
SPDX-License-Identifier: GPL-2.0-only
SPDX-FileCopyrightText: © 2021 Avinal Kumar <avinal.xlvii@gmail.com>
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

add_library(ojo EXCLUDE_FROM_ALL "")
add_library(ojo_cov EXCLUDE_FROM_ALL "")
add_executable(ojo_exec "")
add_executable(ojo_cov_exec EXCLUDE_FROM_ALL "")
foreach(FO_OJO_TARGET ojo ojo_exec ojo_cov ojo_cov_exec)
    target_compile_definitions(${FO_OJO_TARGET}
        PRIVATE
        _FILE_OFFSET_BITS=64
        DATADIR="${FO_MODDIR}"
        VERSION_S="${FO_VERSION}"
        COMMIT_HASH_S="${FO_COMMIT_HASH}"
    )
    target_sources(${FO_OJO_TARGET}
        PRIVATE
        ${FO_CWD}/OjosDatabaseHandler.cc
        ${FO_CWD}/ojoregex.cc
        ${FO_CWD}/ojos.cc
        ${FO_CWD}/OjoAgent.cc
        ${FO_CWD}/OjoState.cc
        ${FO_CWD}/directoryScan.cc
        ${FO_CWD}/OjoUtils.cc)
    if(${FO_OJO_TARGET} MATCHES "^ojo_cov")
        target_compile_options(${FO_OJO_TARGET} PRIVATE ${FO_COV_FLAGS})
    endif()
    target_link_libraries(${FO_OJO_TARGET}
        PRIVATE fossologyCPP ${icu-uc_LIBRARIES} ${Boost_LIBRARIES} ${jsoncpp_LIBRARIES})
    if(${FO_OJO_TARGET} MATCHES "_exec$")
        string(REPLACE "_exec" "" FO_OJO_TARGET_R ${FO_OJO_TARGET})
        set_target_properties(${FO_OJO_TARGET}
            PROPERTIES OUTPUT_NAME ${FO_OJO_TARGET_R})
    endif()
endforeach()

install(TARGETS ojo_exec
    RUNTIME
    DESTINATION ${FO_MODDIR}/${PROJECT_NAME}/agent
    COMPONENT ojo)
