#[=======================================================================[
SPDX-License-Identifier: GPL-2.0-only
SPDX-FileCopyrightText: © 2021 Avinal Kumar <avinal.xlvii@gmail.com>
#]=======================================================================]

message(STATUS "Configuring ${PROJECT_NAME}")

# checking for dependencies
find_package(PkgConfig REQUIRED)

if(NOT DEFINED ARE_DEFAULTS_SET)

# set all the default variable in cache
set(FO_PROJECT "fossology" CACHE INTERNAL "The name of our project")

set(FO_PROJECTUSER "fossy" CACHE INTERNAL "user for the project in the system")

set(FO_PROJECTGROUP "fossy" CACHE INTERNAL "group for the project in the system")

# agents library and source paths
get_filename_component(FO_BASEDIR ${CMAKE_CURRENT_LIST_DIR}/../ ABSOLUTE CACHE PATH)

set(FO_DEBDIR "${FO_BASEDIR}/debian" CACHE PATH "debian packaging stuffs")

set(FO_CMAKEDIR "${FO_BASEDIR}/cmake" CACHE PATH "cmake modules of fossology")

set(FO_SOURCEDIR "${FO_BASEDIR}/src" CACHE PATH "source directory of fossology")

set(FO_CLIB_SRC "${FO_SOURCEDIR}/lib/c" CACHE PATH "path to fossology c library source directory")

set(FO_CXXLIB_SRC "${FO_SOURCEDIR}/lib/cpp" CACHE PATH "path to fossology c++ library source directory")

set(FO_PHPLIB_SRC "${FO_SOURCEDIR}/lib/php" CACHE PATH "path to fossology php library source directory")

set(FO_CLI_SRC "${FO_SOURCEDIR}/cli" CACHE PATH "path to fossology cli source directory")

set(FO_SCH_SRC "${FO_SOURCEDIR}/scheduler" CACHE PATH "path to fossology scheduler source directory")

set(FO_TESTDIR "${FO_SOURCEDIR}/testing" CACHE PATH "testing directory of fossology")


# common flags and options (always use list for flags)
set(FO_C_FLAGS "-Wall" CACHE INTERNAL "default fossology c flags")

set(FO_CXX_FLAGS "${FO_C_FLAGS} --std=c++17" CACHE INTERNAL "default fossology c++ flags")

set(FO_COV_FLAGS "-O0;-fprofile-arcs;-ftest-coverage" CACHE INTERNAL "coverage flags for fossology")


# Install paths
set(FO_DESTDIR "" CACHE INTERNAL "pseudoroot for packaging purposes")

# set(CMAKE_INSTALL_PREFIX "")
message(STATUS "Installation path: ${CMAKE_INSTALL_PREFIX}")

set(FO_PREFIX "${CMAKE_INSTALL_PREFIX}" CACHE PATH "base of the program data tree")

set(FO_BINDIR "${FO_PREFIX}/bin" CACHE PATH "executable programs that users run")

set(FO_SBINDIR "${FO_PREFIX}/sbin" CACHE PATH "executable programs that sysadmins can run")

set(FO_SYSCONFDIR "${FO_PREFIX}/etc/${FO_PROJECT}" CACHE PATH "configuration files")

set(FO_INITDIR "/etc" CACHE PATH "init script root dir")

set(FO_LIBDIR "${FO_PREFIX}/lib" CACHE PATH "object code libraries")

set(FO_INCLUDEDIR "${FO_PREFIX}/include" CACHE PATH "include files")

set(FO_LIBEXECDIR "${FO_PREFIX}/lib/${FO_PROJECT}" CACHE PATH "executables/libraries that only our project uses")

set(FO_DATAROOTDIR "${FO_PREFIX}/share" CACHE PATH "non-arch-specific data")

set(FO_MODDIR "${FO_DATAROOTDIR}/${FO_PROJECT}" CACHE PATH "non-arch-dependent program data")

set(FO_REPODIR "/srv/${FO_PROJECT}/repository" CACHE PATH "hardcoded repository location")

set(FO_LOCALSTATEDIR "/var/local" CACHE PATH "local state")

set(FO_PROJECTSTATEDIR "${FO_LOCALSTATEDIR}/lib/${FO_PROJECT}" CACHE PATH "project local state")

set(FO_CACHEDIR "${FO_LOCALSTATEDIR}/cache/${FO_PROJECT}" CACHE PATH "cache dir")

set(FO_LOGDIR "/var/log/${FO_PROJECT}" CACHE PATH "project logdir")

set(FO_MANDIR "${FO_DATAROOTDIR}/man" CACHE PATH "manpages")

set(FO_MAN1DIR "${FO_MANDIR}/man1" CACHE PATH "man pages in *roff format, man 1")

set(FO_DOCDIR "${DATAROOTDIR}/doc/${FO_PROJECT}" CACHE PATH "project documentation")

set(FO_WEBDIR "${FO_MODDIR}/www" CACHE PATH "webroot")

set(FO_PHPDIR "${FO_MODDIR}/php" CACHE PATH "php root")

set(FO_WRAPPER "${CMAKE_BINARY_DIR}/src/cli/gen/fo_wrapper.php" CACHE PATH "fo wrapper for testing")

## Build variables

set(FO_APACHE_CTL "/usr/sbin/apachectl" CACHE PATH "apache ctl")

set(FO_APACHE2_EN_SITE "/usr/sbin/a2ensite" CACHE PATH "apache ensite")

set(FO_APACHE2_DIS_SITE "/usr/sbin/a2dissite" CACHE PATH "apache dissite")

set(FO_APACHE2SITE_DIR "/etc/apache2/sites-available" CACHE PATH "apache site dir")

set(FO_HTTPD_SITE_DIR "/etc/httpd/conf.d" CACHE PATH "http site dir")

set(FO_TWIG_CACHE ${FO_CACHEDIR} CACHE INTERNAL "twig cache variable")

set(ARE_DEFAULTS_SET ON CACHE BOOL "flag to check if defaults have been set")

endif(NOT DEFINED ARE_DEFAULTS_SET)

find_package(PostgreSQL REQUIRED)
if(DEFINED CMAKE_CXX_COMPILER)
    find_package(Boost REQUIRED regex system filesystem program_options)
endif()
find_package(Git REQUIRED)

# libmagic, libgcrypt does not have pc module on Debian buster
# json-c does not have cmake aware packages on Debian buster
foreach(SCHE_LIBS
        glib-2.0 gthread-2.0 gio-2.0 gobject-2.0 rpm libxml-2.0 libxslt icu-uc
        json-c
)
    string(REPLACE "-2.0" "" LIB_NAME ${SCHE_LIBS})
    pkg_check_modules(${LIB_NAME} REQUIRED ${SCHE_LIBS})
endforeach()

find_file(FO_RPM_CRYPTO_H_HEADER 
    NAMES rpmcrypto.h
    PATHS ${rpm_INCLUDE_DIRS}
    PATH_SUFFIXES rpm
    NO_DEFAULT_PATH
)

if(TESTING)
    foreach(TEST_LIBS
            cunit cppunit
    )
        pkg_check_modules(${TEST_LIBS} REQUIRED ${TEST_LIBS})
    endforeach()
endif(TESTING)

set(CMAKE_INSTALL_MESSAGE NEVER) # control if messages should be displayed while installing

include(${FO_CMAKEDIR}/FoUtilities.cmake)
getGitVersion()

# Variables for testing
execute_process(
    COMMAND /usr/bin/id --name --user
    OUTPUT_VARIABLE FO_CURRENTUSER
)
execute_process(
    COMMAND /usr/bin/id --name --group
    OUTPUT_VARIABLE FO_CURRENTGROUP
)
