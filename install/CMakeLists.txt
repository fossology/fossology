#[=======================================================================[
SPDX-License-Identifier: GPL-2.0-only
SPDX-FileCopyrightText: © 2021 Avinal Kumar <avinal.xlvii@gmail.com>
#]=======================================================================]

set(FO_CWD ${CMAKE_CURRENT_SOURCE_DIR})

file(GLOB_RECURSE FO_INSTALL_CONFS ${FO_CWD}/*.in)

foreach(CONF_FILE ${FO_INSTALL_CONFS})
    get_filename_component(FILE_NAMEXT ${CONF_FILE} NAME)
    string(REPLACE ".in" "" FILE_NAME ${FILE_NAMEXT})
    configure_file(${CONF_FILE}
        ${CMAKE_CURRENT_BINARY_DIR}/gen/${FILE_NAME}
        NEWLINE_STYLE UNIX
        @ONLY)
    message(STATUS "Generating ${FILE_NAME}")
endforeach()

add_custom_target(BUILD_version ALL
        COMMAND ${CMAKE_COMMAND}
            -DIN_FILE_NAME="VERSION.in"
            -DINPUT_FILE_DIR="${FO_CMAKEDIR}"
            -DOUTPUT_FILE_DIR="${CMAKE_CURRENT_BINARY_DIR}"
            -DOUT_FILE_NAME="VERSION"
            -DPROJECT_NAME="BUILD"
            -DFO_VERSION="${FO_VERSION}"
            -DFO_BRANCH="${FO_BRANCH}"
            -DFO_COMMIT_HASH="${FO_COMMIT_HASH}"
            -DFO_BUILD_DATE="${FO_BUILD_DATE}"
            -DFO_COMMIT_DATE="${FO_COMMIT_DATE}"
            -P ${FO_CMAKEDIR}/FoVersionFile.cmake
        DEPENDS "${FO_CMAKEDIR}/VERSION.in"
        COMMENT "Generating VERSION for ${FO_PROJECT}"
        BYPRODUCTS "${CMAKE_CURRENT_BINARY_DIR}/VERSION")


install(FILES
    "${CMAKE_CURRENT_BINARY_DIR}/VERSION"
    ${CMAKE_CURRENT_BINARY_DIR}/gen/Db.conf
    ${CMAKE_CURRENT_BINARY_DIR}/gen/fossology.conf
    COMPONENT common
    DESTINATION ${FO_SYSCONFDIR})

install(FILES "${CMAKE_CURRENT_BINARY_DIR}/VERSION"
    DESTINATION ${FO_MODDIR}
    COMPONENT common
)


install(DIRECTORY
    ${CMAKE_CURRENT_SOURCE_DIR}/defconf/
    ${CMAKE_CURRENT_SOURCE_DIR}/fossdash/
    COMPONENT common
    DESTINATION ${FO_SYSCONFDIR}
    FILES_MATCHING
    PATTERN *.txt
    PATTERN *.yml
)

install(DIRECTORY ${FO_CWD}/db/
    ${CMAKE_CURRENT_BINARY_DIR}/gen/
    DESTINATION ${FO_LIBEXECDIR}
    COMPONENT db
    FILES_MATCHING
    PATTERN *.php
    PATTERN *.sql
    PATTERN *.json
    PATTERN "migratetest.php" EXCLUDE
)

install(PROGRAMS ${CMAKE_CURRENT_BINARY_DIR}/gen/fo-postinstall
    ${FO_CWD}/fo_dbcheck.php
    ${FO_CWD}/fossinit-common.php
    ${FO_CWD}/fossinit.php
    ${FO_CWD}/fo-install-pythondeps
    ${CMAKE_CURRENT_BINARY_DIR}/gen/fossdash-publish.py
    DESTINATION ${FO_LIBEXECDIR}
    COMPONENT common
)
install(PROGRAMS ${CMAKE_CURRENT_BINARY_DIR}/gen/dbcreate
    DESTINATION ${FO_LIBEXECDIR}
    COMPONENT db
)

install(FILES
    ${FO_CWD}/fo-apache.conf
    ${FO_CWD}/src-install-apache-example.conf
    COMPONENT common
    DESTINATION ${FO_SYSCONFDIR}/conf
)

install(FILES ${CMAKE_CURRENT_BINARY_DIR}/gen/db.cron
    DESTINATION ${FO_INITDIR}/cron.d
    RENAME fossology
    COMPONENT db
)
