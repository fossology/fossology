#[=======================================================================[
SPDX-License-Identifier: GPL-2.0-only
SPDX-FileCopyrightText: © 2021 Avinal Kumar <avinal.xlvii@gmail.com>
#]=======================================================================]

#[[ macro to get latest version
    git version
    commit hash
    build time
    commit time
]]
macro(getGitVersion)
    find_package(Git REQUIRED)
    execute_process(
        COMMAND "${GIT_EXECUTABLE}" describe --tags
        COMMAND head -1
        WORKING_DIRECTORY "${FO_SOURCEDIR}"
        OUTPUT_VARIABLE VERSION_GIT
        ERROR_QUIET
        OUTPUT_STRIP_TRAILING_WHITESPACE)
    string(REPLACE "-" ";" FO_VERSION_GIT "${VERSION_GIT}")
    list(LENGTH FO_VERSION_GIT VAR_LEN)
    # At tag (4.0.0), just add .0 at end
    if(${VAR_LEN} EQUAL 1)
        string(APPEND ".0" ${VERSION_GIT})
    # At rc (4.0.0-rc1), add .0 and append -rc
    elseif(${VAR_LEN} EQUAL 2)
        list(GET FO_VERSION_GIT 0 VERSION_GIT)
        list(GET FO_VERSION_GIT 1 VERSION_RC)
        string(APPEND VERSION_GIT ".0")
        string(APPEND VERSION_GIT "-" ${VERSION_RC})
    # Commit after release (4.0.0-92-gea9184770)
    elseif(${VAR_LEN} EQUAL 3)
        string(REGEX REPLACE
        "^([0-9]+\\.[0-9]+\\.[0-9]+)-([0-9]*)-g[0-9a-z]*"
        "\\1.\\2" VERSION_GIT ${VERSION_GIT})
    # Commit after rc (4.0.0-rc1-31-gca77eedd6)
    elseif(${VAR_LEN} EQUAL 4)
        string(REGEX REPLACE
        "^([0-9]+\\.[0-9]+\\.[0-9]+)(-rc[0-9]+)-([0-9]*)-g[0-9a-z]*"
        "\\1.\\3\\2" VERSION_GIT ${VERSION_GIT})
    endif()
    set(FO_VERSION ${VERSION_GIT} CACHE INTERNAL "fossology version")

    execute_process(
        COMMAND "${GIT_EXECUTABLE}" show -s --format=%h
        COMMAND head -1
        WORKING_DIRECTORY "${FO_SOURCEDIR}"
        OUTPUT_VARIABLE COMMIT_HASH
        OUTPUT_STRIP_TRAILING_WHITESPACE
    )
    string(SUBSTRING "${COMMIT_HASH}" 0 6 COMMIT_HASH)
    set(FO_COMMIT_HASH ${COMMIT_HASH} CACHE INTERNAL "latest commit hash")

    execute_process(
        COMMAND "${GIT_EXECUTABLE}" rev-parse --abbrev-ref HEAD
        COMMAND head -1
        WORKING_DIRECTORY "${FO_SOURCEDIR}"
        OUTPUT_VARIABLE BRANCH
        OUTPUT_STRIP_TRAILING_WHITESPACE
    )
    set(FO_BRANCH ${BRANCH} CACHE INTERNAL "current branch")

    execute_process(
        COMMAND date +"%Y/%m/%d %R %:z"
        OUTPUT_VARIABLE BUILD_DATE
        OUTPUT_STRIP_TRAILING_WHITESPACE
        WORKING_DIRECTORY ${FO_SOURCEDIR}
    )
    set(FO_BUILD_DATE ${BUILD_DATE} CACHE INTERNAL "latest build date")

    execute_process(
        COMMAND "${GIT_EXECUTABLE}" show -s --format=@%ct
        COMMAND date +"%Y/%m/%d %R %:z" -f -
        OUTPUT_VARIABLE COMMIT_DATE
        OUTPUT_STRIP_TRAILING_WHITESPACE
        WORKING_DIRECTORY ${FO_SOURCEDIR}
    )
    set(FO_COMMIT_DATE ${COMMIT_DATE} CACHE INTERNAL "latest commit date")

    set_property(GLOBAL APPEND
        PROPERTY CMAKE_CONFIGURE_DEPENDS
        "${CMAKE_SOURCE_DIR}/.git/index")
endmacro(getGitVersion)


#[[ generate VERSION file for agents
    @param Project Name
    @param version file name
]]
macro(generate_version)
    set(FO_ARGS ${ARGV})
    if(${ARGC} EQUAL 0)
        set(FO_PROJECT_NAME ${PROJECT_NAME})
        set(VERSION_FILE_NAME "VERSION")
    elseif(${ARGC} EQUAL 1)
        list(GET FO_ARGS 0 FO_PROJECT_NAME)
        set(VERSION_FILE_NAME "VERSION")
    elseif(${ARGC} EQUAL 2)
        list(GET FO_ARGS 0 FO_PROJECT_NAME)
        list(GET FO_ARGS 1 VERSION_FILE_NAME)
    endif()
    add_custom_target(${FO_PROJECT_NAME}_version ALL
        COMMAND ${CMAKE_COMMAND}
            -DIN_FILE_NAME="VERSION.in"
            -DINPUT_FILE_DIR="${FO_CMAKEDIR}"
            -DOUTPUT_FILE_DIR="${PROJECT_BINARY_DIR}"
            -DOUT_FILE_NAME="${VERSION_FILE_NAME}"
            -DPROJECT_NAME="${PROJECT_NAME}"
            -DFO_VERSION="${FO_VERSION}"
            -DFO_BRANCH="${FO_BRANCH}"
            -DFO_COMMIT_HASH="${FO_COMMIT_HASH}"
            -DFO_BUILD_DATE="${FO_BUILD_DATE}"
            -DFO_COMMIT_DATE="${FO_COMMIT_DATE}"
            -P ${FO_CMAKEDIR}/FoVersionFile.cmake
        DEPENDS "${FO_CMAKEDIR}/VERSION.in" "${FO_PROJECT_NAME}"
        COMMENT "Generating ${VERSION_FILE_NAME} for ${FO_PROJECT_NAME}"
        BYPRODUCTS "${PROJECT_BINARY_DIR}/${VERSION_FILE_NAME}")
endmacro(generate_version)


#[[ generate version.php for php agents
    @param project name
]]
macro(generate_version_php)
    set(FO_ARGS ${ARGV})
    if(${ARGC} EQUAL 0)
        set(FO_PROJECT_NAME ${PROJECT_NAME})
    elseif(${ARGC} EQUAL 1)
        list(GET FO_ARGS 0 FO_PROJECT_NAME)
    endif()
    add_custom_target(${FO_PROJECT_NAME} ALL
        COMMAND ${CMAKE_COMMAND}
            -DIN_FILE_NAME="version.php.in"
            -DINPUT_FILE_DIR="${CMAKE_CURRENT_SOURCE_DIR}"
            -DOUTPUT_FILE_DIR="${CMAKE_CURRENT_BINARY_DIR}/gen"
            -DOUT_FILE_NAME="version.php"
            -DFO_VERSION="${FO_VERSION}"
            -DFO_COMMIT_HASH="${FO_COMMIT_HASH}"
            -P ${FO_CMAKEDIR}/FoVersionFile.cmake
            BYPRODUCTS "${CMAKE_CURRENT_BINARY_DIR}/gen/version.php"
            COMMENT "Generating version.php for ${FO_PROJECT_NAME}"
            DEPENDS "${CMAKE_CURRENT_SOURCE_DIR}/version.php.in"
            WORKING_DIRECTORY ${CMAKE_CURRENT_SOURCE_DIR})
endmacro(generate_version_php)

#[[ generate symbolic links and install them
    @param link name
    @param link target
    @param link destination
]]
macro(add_symlink)
    set(LINK_NAME ${PROJECT_NAME})
    set(LINK_TARGET ${FO_MODDIR}/${PROJECT_NAME})
    set(LINK_DESTINATION ${FO_SYSCONFDIR}/mods-enabled)
    set(FO_ARGS ${ARGV})
    if(${ARGC} EQUAL 1)
        list(GET FO_ARGS 0 LINK_NAME)
    elseif(${ARGC} EQUAL 2)
        list(GET FO_ARGS 0 LINK_NAME)
        list(GET FO_ARGS 1 LINK_TARGET)
    elseif(${ARGC} EQUAL 3)
        list(GET FO_ARGS 0 LINK_NAME)
        list(GET FO_ARGS 1 LINK_TARGET)
        list(GET FO_ARGS 2 LINK_DESTINATION)
    endif()
    get_filename_component(LINK_TARGET_NAME ${LINK_TARGET} NAME_WE)
    install(CODE
        "file(MAKE_DIRECTORY \"\$ENV{DESTDIR}${LINK_DESTINATION}\")
        execute_process(
            COMMAND ln -sf -T \"${LINK_TARGET}\" \"${LINK_NAME}\"
            WORKING_DIRECTORY \"\$ENV{DESTDIR}${LINK_DESTINATION}\")"
        COMPONENT ${PROJECT_NAME})
endmacro(add_symlink)

#[[ prepare phpunit for tests
    adds symbolic links for autoload.php
    adds PHPUnit location to cmake cache
]]
macro(prepare_phpunit)
    file(COPY ${FO_SOURCEDIR}/composer.lock DESTINATION ${CMAKE_BINARY_DIR})
    configure_file(${FO_SOURCEDIR}/composer.json.in ${CMAKE_BINARY_DIR}/composer.json @ONLY)
    add_custom_target(phpunit ALL
        COMMENT "Generating PHPunit"
        COMMAND composer install --prefer-dist --working-dir=.
        COMMAND mkdir -p ${FO_SOURCEDIR}/vendor
        COMMAND ln -sf -T ${CMAKE_BINARY_DIR}/vendor/autoload.php ${FO_SOURCEDIR}/vendor/autoload.php
        WORKING_DIRECTORY ${CMAKE_BINARY_DIR}
        BYPRODUCTS ${CMAKE_BINARY_DIR}/vendor)
    set(PHPUNIT ${CMAKE_BINARY_DIR}/vendor/bin/phpunit CACHE PATH "phpunit location")
    set(PHPUNIT_BOOTSTRAP ${FO_SOURCEDIR}/phpunit-bootstrap.php CACHE PATH "phpunit bootstrap file")
endmacro()
