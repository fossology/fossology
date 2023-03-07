#[=======================================================================[
SPDX-License-Identifier: GPL-2.0-only
SPDX-FileCopyrightText: © 2022 Siemens AG
SPDX-FileContributor: Gaurav Mishra <mishra.gaurav@siemens.com>
#]=======================================================================]

generate_version_php()

install(DIRECTORY ${CMAKE_CURRENT_LIST_DIR}/
    DESTINATION ${FO_MODDIR}/${PROJECT_NAME}/agent
    COMPONENT decisionimporter
    FILES_MATCHING
    PATTERN *.php
    PATTERN version.php EXCLUDE
)

install(DIRECTORY ${CMAKE_CURRENT_BINARY_DIR}/gen/
    DESTINATION ${FO_MODDIR}/${PROJECT_NAME}/agent
    COMPONENT decisionimporter
    FILES_MATCHING
    PATTERN *.php
)

add_symlink(${PROJECT_NAME} ${FO_LIBEXECDIR}/fo_wrapper ${FO_MODDIR}/${PROJECT_NAME}/agent)

# Remove obsolete files
set(FO_DECISIONIMPORTER_OBSOLETE
    DecisionImporter.php
)

set(FO_DECISIONIMPORTER_OBSOLETE_SRC "")
foreach(OBSOLETE ${FO_DECISIONIMPORTER_OBSOLETE})
    list(APPEND FO_DECISIONIMPORTER_OBSOLETE_SRC "${CMAKE_CURRENT_SOURCE_DIR}/${OBSOLETE}")
endforeach()

set(FO_DECISIONIMPORTER_OBSOLETE_DEST "")
foreach(OBSOLETE ${FO_DECISIONIMPORTER_OBSOLETE})
    list(APPEND FO_DECISIONIMPORTER_OBSOLETE_DEST "${FO_MODDIR}/${PROJECT_NAME}/agent/${OBSOLETE}")
endforeach()

file(REMOVE_RECURSE ${FO_DECISIONIMPORTER_OBSOLETE_SRC})

install(CODE "file(REMOVE_RECURSE ${FO_DECISIONIMPORTER_OBSOLETE_DEST})")
