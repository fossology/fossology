#[=======================================================================[
SPDX-License-Identifier: GPL-2.0-only
SPDX-FileCopyrightText: © 2021 Avinal Kumar <avinal.xlvii@gmail.com>
#]=======================================================================]

set(FO_CWD ${CMAKE_CURRENT_SOURCE_DIR})
set(CMAKE_CXX_FLAGS "${CMAKE_CXX_FLAGS} ${FO_CXX_FLAGS} -fopenmp -Wextra")

include_directories(
    ${glib_INCLUDE_DIRS}
    ${jsoncpp_INCLUDE_DIRS}
    ${PostgreSQL_INCLUDE_DIRS}
    ${FO_CLIB_SRC})

if(NOT TARGET fossologyCPP)
    add_subdirectory(${FO_CXXLIB_SRC} buildcpp EXCLUDE_FROM_ALL)
endif()

foreach(COMMON_OBJ copyright regscan scanners cleanEntries regexConfProvider
    regexConfParser directoryScan)
    add_library(${COMMON_OBJ}.cc.o OBJECT EXCLUDE_FROM_ALL
        ${FO_CWD}/${COMMON_OBJ}.cc)
    target_link_libraries(${COMMON_OBJ}.cc.o
        PRIVATE fossologyCPP m ${icu-uc_LIBRARIES} stdc++
        ${Boost_LIBRARIES} ${jsoncpp_LIBRARIES})
    list(APPEND COMMON_COPY_OBJECTS ${COMMON_OBJ}.cc.o)
endforeach()

add_library(copyright_lib EXCLUDE_FROM_ALL "")
add_library(copyright_cov_lib EXCLUDE_FROM_ALL "")
add_executable(copyright "")
add_executable(ecc "")
add_executable(keyword "")
add_executable(ipra "")
add_executable(fo_unicode_clean "")
add_executable(copyright_cov EXCLUDE_FROM_ALL "")
foreach(FO_COPY_TARGET copyright_lib copyright copyright_cov_lib copyright_cov
    ecc keyword ipra fo_unicode_clean)
    target_compile_definitions(${FO_COPY_TARGET}
        PRIVATE
        DATADIR="${FO_MODDIR}"
        _FILE_OFFSET_BITS=64
        VERSION_S="${FO_VERSION}"
        COMMIT_HASH_S="${FO_COMMIT_HASH}"
    )
    if(${FO_COPY_TARGET} MATCHES "^copyright")
        target_compile_definitions(${FO_COPY_TARGET} PRIVATE IDENTITY_COPYRIGHT)
    elseif(${FO_COPY_TARGET} STREQUAL "ecc")
        target_compile_definitions(${FO_COPY_TARGET} PRIVATE IDENTITY_ECC)
    elseif(${FO_COPY_TARGET} STREQUAL "keyword")
        target_compile_definitions(${FO_COPY_TARGET} PRIVATE IDENTITY_KW)
    elseif(${FO_COPY_TARGET} STREQUAL "ipra")
        target_compile_definitions(${FO_COPY_TARGET} PRIVATE IDENTITY_IPRA)
    endif()
    if(NOT ${FO_COPY_TARGET} STREQUAL "fo_unicode_clean")
        if(${FO_COPY_TARGET} MATCHES "^copyright")
            set(COP_XSRC copyscan.cc)
        endif()
        target_sources(${FO_COPY_TARGET}
            PRIVATE
            ${FO_CWD}/copyrightUtils.cc
            ${FO_CWD}/copyrightState.cc
            ${FO_CWD}/database.cc
            ${FO_CWD}/${COP_XSRC})
        set(COPY_LIBS ${COMMON_COPY_OBJECTS})
    else()
        target_sources(${FO_COPY_TARGET}
            PRIVATE ${FO_CWD}/FossologyUnicodeClean.cc)
        set(COPY_LIBS "")
    endif()
    target_link_libraries(${FO_COPY_TARGET}
        PRIVATE fossologyCPP m ${icu-uc_LIBRARIES} stdc++
        ${Boost_LIBRARIES} ${jsoncpp_LIBRARIES} ${COPY_LIBS})
endforeach()
target_compile_options(copyright_cov PRIVATE ${FO_COV_FLAGS})
set_target_properties(copyright_lib PROPERTIES OUTPUT_NAME copyright)
set_target_properties(copyright_cov_lib PROPERTIES OUTPUT_NAME copyright_cov)

# Explicit link for ubuntu:18.04
target_link_libraries(copyright
    PRIVATE
    copyright.cc.o
)

install(TARGETS fo_unicode_clean
    RUNTIME
    DESTINATION ${FO_MODDIR}/${PROJECT_NAME}/agent
    COMPONENT copyright)

foreach(COP ecc keyword copyright ipra)
    install(TARGETS ${COP}
        RUNTIME
        DESTINATION ${FO_MODDIR}/${COP}/agent
        COMPONENT ${COP})
    install(FILES ${COP}.conf
        DESTINATION ${FO_MODDIR}/${COP}/agent
        COMPONENT ${COP})
endforeach()

if(TESTING)
    file(COPY copyright.conf DESTINATION ${CMAKE_CURRENT_BINARY_DIR})
endif()
