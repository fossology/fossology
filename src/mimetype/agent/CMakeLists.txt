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

add_library(mimetype "")
add_library(mimetype_cov EXCLUDE_FROM_ALL "")
foreach(FO_MIME_TARGET mimetype mimetype_cov)
    target_compile_definitions(${FO_MIME_TARGET}
        PRIVATE
        _FILE_OFFSET_BITS=64
        VERSION_S="${FO_VERSION}"
        COMMIT_HASH_S="${FO_COMMIT_HASH}"
    )
    target_sources(${FO_MIME_TARGET}
        PRIVATE ${FO_CWD}/mimetype.c ${FO_CWD}/finder.c)
    target_link_libraries(mimetype PRIVATE fossology magic)
endforeach()
target_compile_options(mimetype_cov PRIVATE ${FO_COV_FLAGS})

add_executable(mimetype_exec "")
target_compile_definitions(mimetype_exec
    PRIVATE
    _FILE_OFFSET_BITS=64
    VERSION_S="${FO_VERSION}"
    COMMIT_HASH_S="${FO_COMMIT_HASH}"
)
target_sources(mimetype_exec PRIVATE ${FO_CWD}/mimetype.c)
target_link_libraries(mimetype_exec PRIVATE fossology magic mimetype)
set_target_properties(mimetype_exec PROPERTIES OUTPUT_NAME mimetype)

install(TARGETS mimetype_exec
    RUNTIME
    DESTINATION ${FO_MODDIR}/${PROJECT_NAME}/agent
    COMPONENT mimetype)
