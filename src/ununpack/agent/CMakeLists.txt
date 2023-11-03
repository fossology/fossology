#[=======================================================================[
SPDX-License-Identifier: GPL-2.0-only
SPDX-FileCopyrightText: © 2021 Avinal Kumar <avinal.xlvii@gmail.com>
#]=======================================================================]

set(FO_CWD ${CMAKE_CURRENT_SOURCE_DIR})
set(CMAKE_C_FLAGS "${CMAKE_C_FLAGS} ${FO_C_FLAGS}")
# Set to 1 if using upx-ucl package to support .upx files
set(UPX_INSTALLED 0)

include_directories(
    ${glib_INCLUDE_DIRS}
    ${PostgreSQL_INCLUDE_DIRS}
    ${FO_CLIB_SRC})

if(NOT TARGET fossology)
    add_subdirectory(${FO_CLIB_SRC} buildc EXCLUDE_FROM_ALL)
endif()

add_library(ununpack EXCLUDE_FROM_ALL "")
add_library(ununpack_cov EXCLUDE_FROM_ALL "")
add_executable(ununpack-sa EXCLUDE_FROM_ALL "")
foreach(FO_UNP_LIB ununpack ununpack_cov ununpack-sa)
    target_compile_definitions(${FO_UNP_LIB}
        PRIVATE
        _FILE_OFFSET_BITS=64
        VERSION_S="${FO_VERSION}"
        COMMIT_HASH_S="${FO_COMMIT_HASH}"
        UPX_SUPPORT=${UPX_INSTALLED}
    )
    target_sources(${FO_UNP_LIB}
        PRIVATE
        ${FO_CWD}/traverse.c
        ${FO_CWD}/ununpack-iso.c
        ${FO_CWD}/ununpack-ar.c
        ${FO_CWD}/ununpack-disk.c
        ${FO_CWD}/ununpack-zstd.c
        ${FO_CWD}/utils.c
        ${FO_CWD}/checksum.c)
    if(${FO_UNP_LIB} STREQUAL "ununpack-sa")
        target_sources(${FO_UNP_LIB}
            PRIVATE
            ${FO_CWD}/standalone.c
        )
        target_link_libraries(${FO_UNP_LIB} PRIVATE gcrypt)
        target_compile_definitions(${FO_UNP_LIB}
            PRIVATE
            STANDALONE=1
        )
    else()
        target_link_libraries(${FO_UNP_LIB} PRIVATE fossology gcrypt)
    endif()
endforeach()

add_executable(departition "")
add_executable(departition-coverage EXCLUDE_FROM_ALL "")
add_executable(ununpack_exec "")
add_executable(ununpack-coverage EXCLUDE_FROM_ALL "")
add_executable(departition-sa EXCLUDE_FROM_ALL "")

foreach(FO_UNP_EXEC departition departition-coverage ununpack_exec ununpack-coverage departition-sa ununpack-sa)
    target_compile_definitions(${FO_UNP_EXEC}
        PRIVATE
        _FILE_OFFSET_BITS=64
        VERSION_S="${FO_VERSION}"
        COMMIT_HASH_S="${FO_COMMIT_HASH}"
        UPX_SUPPORT=${UPX_INSTALLED}
    )
    if(${FO_UNP_EXEC} MATCHES "^departition")
        set(UNP_XSRC "${FO_CWD}/departition.c")
        set(UNP_XLIB "fossology")
    elseif(${FO_UNP_EXEC} MATCHES "^ununpack")
        set(UNP_XSRC "${FO_CWD}/ununpack.c")
        if(${FO_UNP_EXEC} STREQUAL "ununpack-coverage")
            set(UNP_XLIB "ununpack_cov" magic)
        elseif(${FO_UNP_EXEC} STREQUAL "ununpack-sa")
            set(UNP_XLIB magic)
        else()
            set(UNP_XLIB "ununpack" magic)
        endif()
    endif()
    target_sources(${FO_UNP_EXEC} PRIVATE ${UNP_XSRC})
    target_link_libraries(${FO_UNP_EXEC} PRIVATE ${UNP_XLIB})
endforeach()

foreach(FO_UNP_COV ununpack_cov ununpack-coverage departition-coverage)
    target_compile_options(${FO_UNP_COV} PRIVATE ${FO_COV_FLAGS})
endforeach()

set_target_properties(ununpack_exec PROPERTIES OUTPUT_NAME ununpack)

add_library(checksum OBJECT EXCLUDE_FROM_ALL
    ${FO_CWD}/checksum.h ${FO_CWD}/checksum.c)

install(TARGETS ununpack_exec departition
    RUNTIME
    DESTINATION ${FO_MODDIR}/${PROJECT_NAME}/agent
    COMPONENT ununpack)
