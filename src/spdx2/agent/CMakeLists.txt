#[=======================================================================[
SPDX-License-Identifier: GPL-2.0-only
SPDX-FileCopyrightText: © 2021 Avinal Kumar <avinal.xlvii@gmail.com>
#]=======================================================================]

generate_version_php()

install(DIRECTORY ./
    DESTINATION ${FO_MODDIR}/${PROJECT_NAME}/agent
    COMPONENT spdx2
    FILES_MATCHING
    PATTERN *.php
    PATTERN *.twig
    PATTERN version.php EXCLUDE
)

install(DIRECTORY ${CMAKE_CURRENT_BINARY_DIR}/gen/
    DESTINATION ${FO_MODDIR}/${PROJECT_NAME}/agent
    COMPONENT spdx2
    FILES_MATCHING
    PATTERN *.php
    PATTERN *.twig
)

foreach(SPDX_INSTALL spdx2 spdx2tv dep5 spdx2csv spdx3jsonld spdx3json spdx3rdf spdx3tv)
    install(PROGRAMS agent.sh
        DESTINATION ${FO_MODDIR}/${SPDX_INSTALL}/agent
        RENAME ${SPDX_INSTALL}.sh
        COMPONENT spdx2)
endforeach()

add_symlink(${PROJECT_NAME} ${FO_LIBEXECDIR}/fo_wrapper ${FO_MODDIR}/${PROJECT_NAME}/agent)
