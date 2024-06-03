#[=======================================================================[
SPDX-License-Identifier: GPL-2.0-only
SPDX-FileCopyrightText: © 2021 Avinal Kumar <avinal.xlvii@gmail.com>
#]=======================================================================]

generate_version_php()

install(DIRECTORY ./
    DESTINATION ${FO_MODDIR}/${PROJECT_NAME}/agent
    COMPONENT spdx3
    FILES_MATCHING
    PATTERN *.php
    PATTERN *.twig
    PATTERN version.php EXCLUDE
)

install(DIRECTORY ${CMAKE_CURRENT_BINARY_DIR}/gen/
    DESTINATION ${FO_MODDIR}/${PROJECT_NAME}/agent
    COMPONENT spdx3
    FILES_MATCHING
    PATTERN *.php
    PATTERN *.twig
)

foreach(SPDX_INSTALL spdx3json)
    install(PROGRAMS agent.sh
        DESTINATION ${FO_MODDIR}/${SPDX_INSTALL}/agent
        RENAME ${SPDX_INSTALL}.sh
        COMPONENT spdx3)
endforeach()

add_symlink(${PROJECT_NAME} ${FO_LIBEXECDIR}/fo_wrapper ${FO_MODDIR}/${PROJECT_NAME}/agent)
