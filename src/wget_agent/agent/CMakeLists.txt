#[=======================================================================[
SPDX-License-Identifier: GPL-2.0-only
SPDX-FileCopyrightText: © 2021 Avinal Kumar <avinal.xlvii@gmail.com>
#]=======================================================================]

set(FO_CWD ${CMAKE_CURRENT_SOURCE_DIR})
set(CMAKE_C_FLAGS "${CMAKE_C_FLAGS} ${FO_C_FLAGS}")

include_directories(
    ${glib_INCLUDE_DIRS}
    ${PostgreSQL_INCLUDE_DIRS}
    ${FO_CLIB_SRC})

if(NOT TARGET fossology)
    add_subdirectory(${FO_CLIB_SRC} buildc EXCLUDE_FROM_ALL)
endif()

if(NOT TARGET checksum)
    add_subdirectory(${FO_SOURCEDIR}/ununpack buildchk EXCLUDE_FROM_ALL)
endif()

add_library(wget_agent EXCLUDE_FROM_ALL "")
add_library(wget_agent_cov EXCLUDE_FROM_ALL "")
add_executable(wget_agent_exec "")

foreach(FO_WGET_TARGET wget_agent wget_agent_exec wget_agent_cov)
    target_compile_definitions(${FO_WGET_TARGET}
        PRIVATE
        _FILE_OFFSET_BITS=64
        VERSION_S="${FO_VERSION}"
        COMMIT_HASH_S="${FO_COMMIT_HASH}"
    )
    if(${FO_WGET_TARGET} STREQUAL "wget_agent_exec")
        target_link_libraries(${FO_WGET_TARGET}
            PRIVATE fossology wget_agent gcrypt checksum)
        set_target_properties(${FO_WGET_TARGET}
            PROPERTIES OUTPUT_NAME wget_agent)
        set(WGET_XSRC "main.c")
    else()
        target_link_libraries(${FO_WGET_TARGET}
            PRIVATE fossology gcrypt checksum)
        set(WGET_XSRC "")
    endif()
    target_sources(${FO_WGET_TARGET}
        PRIVATE ${FO_CWD}/wget_agent.c ${FO_CWD}/${WGET_XSRC})
    if(${FO_WGET_TARGET} STREQUAL "wget_agent_cov")
        target_compile_options(${FO_WGET_TARGET} PRIVATE ${FO_COV_FLAGS})
    endif()
endforeach()

install(TARGETS wget_agent_exec
    RUNTIME
    DESTINATION ${FO_MODDIR}/${PROJECT_NAME}/agent
    COMPONENT wget_agent)
