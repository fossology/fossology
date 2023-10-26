#[=======================================================================[
SPDX-License-Identifier: GPL-2.0-only
SPDX-FileCopyrightText: © 2021 Avinal Kumar <avinal.xlvii@gmail.com>
#]=======================================================================]

cmake_minimum_required(VERSION 3.13 FATAL_ERROR)
project(www)
# set defaults

include(${CMAKE_CURRENT_SOURCE_DIR}/../../cmake/SetDefaults.cmake)

# Obsolete files to be removed
set(FO_WWW_OBSOLETE
    admin-tag-ns.php admin-change-owner.php admin-tag-ns-perm.php
    admin-folder-delete.php admin-dashboard.php ajax-showjobs.php
    group-manage-self.php group-manage.php ajax-perms.php
    upload_permissions.php upload-srv-files.php upload-vcs.php
    ui-license-list.php ui-browse-license.php
    template/ui-license-list-form.html.twig
    template/components template/include
)

file(WRITE ${CMAKE_CURRENT_BINARY_DIR}/gen/fossology.rc "${FO_SYSCONFDIR}")

install(DIRECTORY ui
    DESTINATION ${FO_MODDIR}/${PROJECT_NAME}
    COMPONENT www
    FILES_MATCHING
    PATTERN "*.css"
    PATTERN "*.dat"
    PATTERN "*.dtd"
    PATTERN "*.gif"
    PATTERN "*.htc"
    PATTERN "*.htm"
    PATTERN "*.html"
    PATTERN "*.ico"
    PATTERN "*.js"
    PATTERN "*openapi*.yaml"
    PATTERN "*.php"
    PATTERN "*.png"
    PATTERN "*.twig"
    PATTERN "pretty-swag.json" EXCLUDE
)

install(FILES
    ${CMAKE_CURRENT_BINARY_DIR}/gen/fossology.rc
    ${FO_SOURCEDIR}/../NOTICES-THIRDPARTY.html
    DESTINATION ${FO_MODDIR}/${PROJECT_NAME}/ui
    COMPONENT www)

install(DIRECTORY
    ${CMAKE_CURRENT_BINARY_DIR}/../genvendor/vendor/vendor/twbs/bootstrap/dist/css/
    DESTINATION ${FO_MODDIR}/${PROJECT_NAME}/ui/css/bootstrap
    COMPONENT www
    PATTERN *.map EXCLUDE
)

install(DIRECTORY
    ${CMAKE_CURRENT_BINARY_DIR}/../genvendor/vendor/vendor/twbs/bootstrap/dist/js/
    DESTINATION ${FO_MODDIR}/${PROJECT_NAME}/ui/scripts/bootstrap
    COMPONENT www
    PATTERN *.map EXCLUDE
)

# Generate source and destination file list
set(FO_WWW_OBSOLETE_SRC "")
foreach(OBSOLETE ${FO_WWW_OBSOLETE})
    list(APPEND FO_WWW_OBSOLETE_SRC "${CMAKE_CURRENT_SOURCE_DIR}/ui/${OBSOLETE}")
endforeach()

set(FO_WWW_OBSOLETE_DEST "")
foreach(OBSOLETE ${FO_WWW_OBSOLETE})
    list(APPEND FO_WWW_OBSOLETE_DEST "${FO_MODDIR}/${PROJECT_NAME}/ui/${OBSOLETE}")
endforeach()

file(REMOVE_RECURSE ${FO_WWW_OBSOLETE_SRC})

install(CODE "file(REMOVE_RECURSE ${FO_WWW_OBSOLETE_DEST})")

# Create symlink for www components
add_custom_target(www ALL
    COMMENT "Configuring www for symbolic link")
add_symlink()
foreach(WWW_INSTALL page async)
    add_symlink(www-${WWW_INSTALL} ${FO_MODDIR}/${PROJECT_NAME}/ui/${WWW_INSTALL})
endforeach()

# Clean TWIG cache on each install
file(GLOB CACHE_FILES_REMOVE ${FO_TWIG_CACHE}/*)

list(LENGTH CACHE_FILES_REMOVE CACHE_FILE_COUNT)

if(${CACHE_FILE_COUNT} GREATER 0)
    install(CODE
        "file(REMOVE_RECURSE ${CACHE_FILES_REMOVE})"
        COMPONENT lib
    )
endif()
