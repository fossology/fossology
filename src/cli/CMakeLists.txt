#[=======================================================================[
SPDX-License-Identifier: GPL-2.0-only
SPDX-FileCopyrightText: © 2021 Avinal Kumar <avinal.xlvii@gmail.com>
#]=======================================================================]

cmake_minimum_required(VERSION 3.18 FATAL_ERROR)
project(cli)

set(FO_CWD ${CMAKE_CURRENT_SOURCE_DIR})

include(${CMAKE_CURRENT_SOURCE_DIR}/../../cmake/SetDefaults.cmake)
file(GLOB CLI_FILES ${FO_CWD}/*.php)
list(REMOVE_ITEM CLI_FILES
    "${FO_CWD}/acme_review.php" "${FO_CWD}/fo_antelink.php"
    "${FO_CWD}/fo_tagfile.php" "${FO_CWD}/fo_tagfoss.php")

file(MAKE_DIRECTORY "${CMAKE_CURRENT_BINARY_DIR}/gen/")

add_custom_target(cli ALL
    COMMAND ${CMAKE_COMMAND}
        -DIN_FILE_NAME="fo_wrapper.php.in"
        -DINPUT_FILE_DIR="${FO_CWD}"
        -DOUTPUT_FILE_DIR="${CMAKE_CURRENT_BINARY_DIR}/gen"
        -DOUT_FILE_NAME="fo_wrapper.php"
        -DFO_SYSCONFDIR="${FO_SYSCONFDIR}"
        -P ${FO_CMAKEDIR}/FoVersionFile.cmake
    BYPRODUCTS "${CMAKE_CURRENT_BINARY_DIR}/gen/fo_wrapper.php"
    COMMENT "Generating fo_wrapper.php"
    DEPENDS "${FO_CWD}/fo_wrapper.php.in")

set(TARGET_LINKS "")
foreach(CLI_FILE ${CLI_FILES})
    get_filename_component(FILE_NAME ${CLI_FILE} NAME_WE)
    add_custom_command(
        OUTPUT "${CMAKE_CURRENT_BINARY_DIR}/gen/${FILE_NAME}"
        COMMAND ln -sf "${CMAKE_CURRENT_BINARY_DIR}/gen/fo_wrapper.php" "${CMAKE_CURRENT_BINARY_DIR}/gen/${FILE_NAME}"
        WORKING_DIRECTORY ${FO_CWD}
    )
    list(APPEND TARGET_LINKS "${CMAKE_CURRENT_BINARY_DIR}/gen/${FILE_NAME}")
endforeach()
add_custom_target(test_cli_targets ALL DEPENDS ${TARGET_LINKS})
add_dependencies(test_cli_targets cli)

add_custom_target(manfiles ALL
    COMMAND pod2man --center="FOSSology" --release="Version ${FO_VERSION}" --name="fossjobs" "${FO_CWD}/fossjobs.pod" > "${CMAKE_CURRENT_BINARY_DIR}/gen/fossjobs.1"
    COMMAND pod2man --center="FOSSology" --release="Version ${FO_VERSION}" --name="cp2foss" "${FO_CWD}/cp2foss.pod" > "${CMAKE_CURRENT_BINARY_DIR}/gen/cp2foss.1"
    WORKING_DIRECTORY ${CMAKE_CURRENT_BINARY_DIR}
    COMMENT "Generating manfiles"
    DEPENDS cp2foss.pod fossjobs.pod
    BYPRODUCTS "${CMAKE_CURRENT_BINARY_DIR}/gen/fossjobs.1" "${CMAKE_CURRENT_BINARY_DIR}/gen/cp2foss.1"
)

add_custom_target(htmlfiles ALL
    COMMAND pod2html --infile="${FO_CWD}/fossjobs.pod" --outfile="${CMAKE_CURRENT_BINARY_DIR}/gen/fossjobs.html"
    COMMAND pod2html --infile="${FO_CWD}/cp2foss.pod" --outfile="${CMAKE_CURRENT_BINARY_DIR}/gen/cp2foss.html"
    COMMAND rm -f pod*.tmp
    WORKING_DIRECTORY ${CMAKE_CURRENT_BINARY_DIR}
    COMMENT "Generating htmlfiles"
    DEPENDS cp2foss.pod fossjobs.pod
    BYPRODUCTS "${CMAKE_CURRENT_BINARY_DIR}/gen/fossjobs.html" "${CMAKE_CURRENT_BINARY_DIR}/gen/cp2foss.html"
)

add_custom_target(txtfiles ALL
    COMMAND pod2text "${FO_CWD}/fossjobs.pod" "${CMAKE_CURRENT_BINARY_DIR}/gen/fossjobs.txt"
    COMMAND pod2text "${FO_CWD}/cp2foss.pod" "${CMAKE_CURRENT_BINARY_DIR}/gen/cp2foss.txt"
    WORKING_DIRECTORY ${CMAKE_CURRENT_BINARY_DIR}
    COMMENT "Generating txtfiles"
    DEPENDS cp2foss.pod fossjobs.pod
    BYPRODUCTS "${CMAKE_CURRENT_BINARY_DIR}/gen/fossjobs.txt" "${CMAKE_CURRENT_BINARY_DIR}/gen/cp2foss.txt"
)

install(PROGRAMS ${CMAKE_CURRENT_BINARY_DIR}/gen/fo_wrapper.php
    DESTINATION ${FO_LIBEXECDIR}
    RENAME fo_wrapper
    COMPONENT cli)

install(DIRECTORY ${CMAKE_CURRENT_BINARY_DIR}/gen/
    DESTINATION ${FO_MAN1DIR}
    COMPONENT cli
    FILES_MATCHING
    PATTERN *.1)

install(PROGRAMS ${CLI_FILES} DESTINATION ${FO_LIBEXECDIR} COMPONENT cli)

foreach(CLI_FILE ${CLI_FILES})
    get_filename_component(FILE_NAME ${CLI_FILE} NAME_WE)
    add_symlink(${FILE_NAME} ${FO_LIBEXECDIR}/fo_wrapper ${FO_BINDIR})
endforeach()
