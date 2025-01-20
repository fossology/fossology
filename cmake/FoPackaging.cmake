#[=======================================================================[
SPDX-License-Identifier: GPL-2.0-only
SPDX-FileCopyrightText: © 2021 Avinal Kumar <avinal.xlvii@gmail.com>
#]=======================================================================]

set(FO_PACKAGE_COMMON_DESCRIPTION
"The FOSSology project is a web based framework that allows you to
upload software to be picked apart and then analyzed by software agents
which produce results that are then browsable via the web interface.
Existing agents include license analysis, metadata extraction, and MIME
type identification.
.")

## Debian packaging default location
set(CPACK_PACKAGING_INSTALL_PREFIX "/usr" CACHE PATH "use /usr as base for package")
set(CPACK_INSTALL_PREFIX "/usr" CACHE PATH "use /usr as base for package")

## Replace -rc to ~rc for packaging
string(REPLACE "-rc" "~rc"
    FO_PACKAGE_VERSION "${FO_VERSION}"
)
## DEBIAN PACKAGING COMMON STUFF
set(CPACK_PACKAGE_VERSION ${FO_PACKAGE_VERSION})
set(CPACK_GENERATOR DEB)
set(CPACK_DEBIAN_PACKAGE_MAINTAINER "FOSSology <fossology@fossology.org>")
set(CPACK_DEBIAN_PACKAGE_PRIORITY "optional")
set(CPACK_DEBIAN_PACKAGE_HOMEPAGE "https://fossology.org")
set(CPACK_DEBIAN_PACKAGE_SOURCE "fossology")
set(CPACK_PACKAGE_VENDOR "fossology")
set(CPACK_DEB_COMPONENT_INSTALL ON)
set(CPACK_DEBIAN_PACKAGE_SHLIBDEPS ON)
set(CPACK_DEBIAN_PACKAGE_GENERATE_SHLIBS ON)
set(CPACK_RESOURCE_FILE_README "${FO_DEBDIR}/README.Debian")
set(CPACK_RESOURCE_FILE_LICENSE "${FO_DEBDIR}/copyright")
set(CPACK_RESOURCE_FILE_CHANGELOG "${CMAKE_BINARY_DIR}/pack/changelog.Debian.gz")
set(CPACK_RESOURCE_FILE_README_MD "${CMAKE_BINARY_DIR}/pack/README.md.gz")
set(CPACK_RESOURCE_FILE_README_COMMON "${FO_DEBDIR}/common/fossology-common.README.Debian")
set(CPACK_COMPONENTS_GROUPING "ONE_PER_GROUP")
set(CPACK_DEBIAN_PACKAGE_CONTROL_STRICT_PERMISSION TRUE)
set(CPACK_PRE_BUILD_SCRIPTS ${CMAKE_CURRENT_LIST_DIR}/FoPackPatch.cmake)

## PACKING COMPONENTS
set(CPACK_COMPONENTS_ALL
    fossology
    adj2nest
    ununpack
    cli
    clixml
    compatibility
    cyclonedx
    lib
    common
    maintagent
    vendor
    buckets
    copyright
    ecc
    keyword
    ipra
    db
    debug
    decider
    deciderjob
    decisionexporter
    decisionimporter
    delagent
    mimetype
    monk
    monkbulk
    nomos
    ojo
    pkgagent
    readmeoss
    unifiedreport
    reuser
    reso
    scancode
    scheduler
    softwareHeritage
    spasht
    spdx
    reportImport
    wget_agent
    www)

## FOSSOLOGY pseudo package
set(CPACK_DEBIAN_FOSSOLOGY_PACKAGE_NAME "fossology")
set(CPACK_DEBIAN_FOSSOLOGY_FILE_NAME "fossology_${FO_PACKAGE_VERSION}-1_amd64.deb")
set(CPACK_DEBIAN_FOSSOLOGY_DESCRIPTION
"open and modular architecture for analyzing software
${FO_PACKAGE_COMMON_DESCRIPTION}
This metapackage ensures that the fossology component packages needed
for a single-system install are installed in the right order. For a
multi-system install, consult the README.Debian file included in the
fossology-common package.")

set(CPACK_DEBIAN_FOSSOLOGY_PACKAGE_DEPENDS
    "fossology-web, fossology-scheduler, fossology-ununpack,
    fossology-copyright, fossology-ecc, fossology-keyword, fossology-ipra,
    fossology-buckets, fossology-mimetype, fossology-delagent,
    fossology-wgetagent")
set(CPACK_DEBIAN_FOSSOLOGY_PACKAGE_RECOMMENDS
    "fossology-cyclonedx, fossology-monk, fossology-monkbulk, fossology-decider,
    fossology-readmeoss, fossology-spdx, fossology-reportimport,
    fossology-softwareheritage, fossology-reuser, fossology-compatibility")

set(CPACK_DEBIAN_FOSSOLOGY_PACKAGE_CONFLICTS
    "fossology-db (<= 1.4.1), fossology-common (<= 1.4.1)")

set(CPACK_DEBIAN_FOSSOLOGY_PACKAGE_SECTION "utils")
set(CPACK_DEBIAN_FOSSOLOGY_PACKAGE_CONTROL_EXTRA
    "${FO_DEBDIR}/fossology/postinst;${FO_DEBDIR}/fossology/postrm")

## FOSSOLOGY-COMMON PACKAGE
set(CPACK_DEBIAN_FOSSOLOGY-COMMON_PACKAGE_NAME "fossology-common")
set(CPACK_DEBIAN_FOSSOLOGY-COMMON_FILE_NAME "fossology-common_${FO_PACKAGE_VERSION}-1_amd64.deb")
set(CPACK_DEBIAN_FOSSOLOGY-COMMON_DESCRIPTION
"architecture for analyzing software, common files
${FO_PACKAGE_COMMON_DESCRIPTION}
This package contains the resources needed by all of the other
fossology components. This includes admin tools for maintenance.")

set(CPACK_DEBIAN_FOSSOLOGY-COMMON_PACKAGE_DEPENDS
    "php7.2-pgsql | php7.3-pgsql | php7.4-pgsql | php8.1-pgsql | php8.2-pgsql | php8.3-pgsql,
    php-pear, php7.2-cli | php7.3-cli | php7.4-cli | php8.1-cli | php8.2-cli | php8.3-cli,
    php-mbstring, php7.2-json | php7.3-json | php7.4-json | php-json,
    php-zip, php-xml,
    php7.2-curl | php7.3-curl | php7.4-curl | php8.1-curl | php8.2-curl | php8.3-curl, php-uuid,
    php7.2-gd | php7.3-gd | php7.4-gd | php8.1-gd | php8.2-gd | php8.3-gd,
    php7.2-yaml | php7.3-yaml | php7.4-yaml | php8.1-yaml | php8.2-yaml | php8.3-yaml | php-yaml")

set(CPACK_DEBIAN_FOSSOLOGY-COMMON_PACKAGE_SECTION "utils")
set(CPACK_DEBIAN_FOSSOLOGY-COMMON_PACKAGE_CONTROL_EXTRA
"${FO_DEBDIR}/common/postinst;${FO_DEBDIR}/common/postrm;${FO_DEBDIR}/common/conffiles")

## FOSSOLOGY-WEB PACKAGE
set(CPACK_DEBIAN_WWW_PACKAGE_NAME "fossology-web")
set(CPACK_DEBIAN_WWW_FILE_NAME "fossology-web_${FO_PACKAGE_VERSION}-1_amd64.deb")
set(CPACK_DEBIAN_WWW_DESCRIPTION
"architecture for analyzing software, web interface
${FO_PACKAGE_COMMON_DESCRIPTION}
This package depends on the packages for the web interface.")

set(CPACK_DEBIAN_WWW_PACKAGE_DEPENDS
    "fossology-common, apache2, php7.2-gd|php7.3-gd|php7.4-gd|php8.1-gd|php8.2-gd|php8.3-gd,
    libapache2-mod-php7.2|libapache2-mod-php7.3|libapache2-mod-php7.4|libapache2-mod-php8.1|libapache2-mod-php8.2|libapache2-mod-php8.3")

set(CPACK_DEBIAN_WWW_PACKAGE_SECTION "utils")
set(CPACK_DEBIAN_WWW_PACKAGE_RECOMMENDS "fossology-db")
set(CPACK_DEBIAN_WWW_PACKAGE_CONTROL_EXTRA
"${FO_DEBDIR}/web/postinst")

## FOSSOLOGY-SCANCODE
set(CPACK_DEBIAN_SCANCODE_PACKAGE_NAME "fossology-scancode")
set(CPACK_DEBIAN_SCANCODE_FILE_NAME "fossology-scancode_${FO_PACKAGE_VERSION}-1_amd64.deb")
set(CPACK_DEBIAN_SCANCODE_DESCRIPTION
"architecture to fetch license, copyright information from scancode
${FO_PACKAGE_COMMON_DESCRIPTION}
This package contains the scancode agent programs and their resources.")

set(CPACK_DEBIAN_SCANCODE_PACKAGE_DEPENDS
    "fossology-common, fossology-ununpack, fossology-wgetagent,
    python3, python3-pip")

set(CPACK_DEBIAN_SCANCODE_PACKAGE_SECTION "utils")

## FOSSOLOGY-SCHEDULER
set(CPACK_DEBIAN_SCHEDULER_PACKAGE_NAME "fossology-scheduler")
set(CPACK_DEBIAN_SCHEDULER_FILE_NAME "fossology-scheduler_${FO_PACKAGE_VERSION}-1_amd64.deb")
set(CPACK_DEBIAN_SCHEDULER_DESCRIPTION
"architecture for analyzing software, scheduler
${FO_PACKAGE_COMMON_DESCRIPTION}
This package contains the scheduler daemon.")

set(CPACK_DEBIAN_SCHEDULER_PACKAGE_DEPENDS "fossology-common, s-nail")

set(CPACK_DEBIAN_SCHEDULER_PACKAGE_SECTION "utils")
set(CPACK_DEBIAN_SCHEDULER_PACKAGE_CONFLICTS "fossology-scheduler-single")
set(CPACK_DEBIAN_SCHEDULER_PACKAGE_CONTROL_EXTRA
"${FO_DEBDIR}/scheduler/postinst;${FO_DEBDIR}/scheduler/postrm;${FO_DEBDIR}/scheduler/conffiles")

## FOSSOLOGY-DB
set(CPACK_DEBIAN_DB_PACKAGE_NAME "fossology-db")
set(CPACK_DEBIAN_DB_FILE_NAME "fossology-db_${FO_PACKAGE_VERSION}-1_amd64.deb")
set(CPACK_DEBIAN_DB_DESCRIPTION
"architecture for analyzing software, database
${FO_PACKAGE_COMMON_DESCRIPTION}
This package contains the database resources and will create a
fossology database on the system (and requires that postgresql is
running at install time). If you prefer to use a remote database,
or want to create the database yourself, do not install this package
and consult the README.Debian file included in the fossology-common
package.")

set(CPACK_DEBIAN_DB_PACKAGE_DEPENDS "postgresql")

set(CPACK_DEBIAN_DB_PACKAGE_SECTION "utils")
set(CPACK_DEBIAN_DB_PACKAGE_CONTROL_EXTRA
"${FO_DEBDIR}/db/postinst;${FO_DEBDIR}/db/postrm;${FO_DEBDIR}/db/conffiles")

if(NOT MONOPACK)
## FOSSOLOGY-UNUNPACK
set(CPACK_DEBIAN_FOSSOLOGY-UNUNPACK_PACKAGE_NAME "fossology-ununpack")
set(CPACK_DEBIAN_FOSSOLOGY-UNUNPACK_FILE_NAME "fossology-ununpack_${FO_PACKAGE_VERSION}-1_amd64.deb")
set(CPACK_DEBIAN_SCHEDULER_PACKAGE_CONFLICTS "fossology-scheduler-single")
set(CPACK_DEBIAN_FOSSOLOGY-UNUNPACK_DESCRIPTION
"architecture for analyzing software, ununpack and adj2nest
${FO_PACKAGE_COMMON_DESCRIPTION}
This package contains the ununpack and adj2nest agent programs and their
resources.")

set(CPACK_DEBIAN_FOSSOLOGY-UNUNPACK_PACKAGE_DEPENDS
    "fossology-common, binutils, bzip2, cabextract, cpio, sleuthkit,
    genisoimage, poppler-utils, rpm, unrar-free, unzip, p7zip-full, p7zip,
    zstd")

set(CPACK_DEBIAN_FOSSOLOGY-UNUNPACK_PACKAGE_SECTION "utils")
else()

## FOSSOLOGY-UNUNPACK
set(CPACK_DEBIAN_UNUNPACK_PACKAGE_NAME "fossology-ununpack")
set(CPACK_DEBIAN_UNUNPACK_FILE_NAME "fossology-ununpack_${FO_PACKAGE_VERSION}-1_amd64.deb")
set(CPACK_DEBIAN_SCHEDULER_PACKAGE_CONFLICTS "fossology-scheduler-single")
set(CPACK_DEBIAN_UNUNPACK_DESCRIPTION
"architecture for analyzing software, ununpack
${FO_PACKAGE_COMMON_DESCRIPTION}
This package contains the ununpack agent program and resources.")

set(CPACK_DEBIAN_UNUNPACK_PACKAGE_DEPENDS
    "fossology-common, binutils, bzip2, cabextract, cpio, sleuthkit,
    genisoimage, poppler-utils, rpm, unrar-free, unzip, p7zip-full, p7zip")

set(CPACK_DEBIAN_UNUNPACK_PACKAGE_SECTION "utils")


## FOSSOLOGY-ADJ2NEST
set(CPACK_DEBIAN_ADJ2NEST_PACKAGE_NAME "fossology-adj2nest")
set(CPACK_DEBIAN_ADJ2NEST_FILE_NAME "fossology-adj2nest_${FO_PACKAGE_VERSION}-1_amd64.deb")
set(CPACK_DEBIAN_ADJ2NEST_DESCRIPTION
"architecture for analyzing software, adj2nest
${FO_PACKAGE_COMMON_DESCRIPTION}
This package contains the adj2nest agent program and resources.")

set(CPACK_DEBIAN_ADJ2NEST_PACKAGE_DEPENDS
    "fossology-common, binutils, bzip2, cabextract, cpio, sleuthkit,
    genisoimage, poppler-utils, rpm, unrar-free, unzip, p7zip-full, p7zip")

set(CPACK_DEBIAN_ADJ2NEST_PACKAGE_SECTION "utils")
endif()

## FOSSOLOGY-COPYRIGHT
set(CPACK_DEBIAN_COPYRIGHT_PACKAGE_NAME "fossology-copyright")
set(CPACK_DEBIAN_COPYRIGHT_FILE_NAME "fossology-copyright_${FO_PACKAGE_VERSION}-1_amd64.deb")
set(CPACK_DEBIAN_COPYRIGHT_DESCRIPTION
"architecture for analyzing software, copyright
${FO_PACKAGE_COMMON_DESCRIPTION}
This package contains the copyright agent programs and their resources.")

set(CPACK_DEBIAN_COPYRIGHT_PACKAGE_DEPENDS
    "fossology-common, libpcre3")

set(CPACK_DEBIAN_COPYRIGHT_PACKAGE_SECTION "utils")

## FOSSOLOGY-ECC
set(CPACK_DEBIAN_ECC_PACKAGE_NAME "fossology-ecc")
set(CPACK_DEBIAN_ECC_FILE_NAME "fossology-ecc_${FO_PACKAGE_VERSION}-1_amd64.deb")
set(CPACK_DEBIAN_ECC_DESCRIPTION
"architecture for analyzing software, ecc
${FO_PACKAGE_COMMON_DESCRIPTION}
This package contains the ecc agent programs and their resources.")

set(CPACK_DEBIAN_ECC_PACKAGE_DEPENDS
    "fossology-common, fossology-copyright, libpcre3")

set(CPACK_DEBIAN_ECC_PACKAGE_SECTION "utils")

## FOSSOLOGY-KEYWORD
set(CPACK_DEBIAN_KEYWORD_PACKAGE_NAME "fossology-keyword")
set(CPACK_DEBIAN_KEYWORD_FILE_NAME "fossology-keyword_${FO_PACKAGE_VERSION}-1_amd64.deb")
set(CPACK_DEBIAN_KEYWORD_DESCRIPTION
"architecture for analyzing software, keyword
${FO_PACKAGE_COMMON_DESCRIPTION}
This package contains the keyword agent programs and their resources.")

set(CPACK_DEBIAN_KEYWORD_PACKAGE_DEPENDS
    "fossology-common, fossology-copyright, libpcre3")
set(CPACK_DEBIAN_KEYWORD_PACKAGE_SECTION "utils")

## FOSSOLOGY-IPRA
set(CPACK_DEBIAN_IPRA_PACKAGE_NAME "fossology-ipra")
set(CPACK_DEBIAN_IPRA_FILE_NAME "fossology-ipra_${FO_PACKAGE_VERSION}-1_amd64.deb")
set(CPACK_DEBIAN_IPRA_DESCRIPTION
"architecture for analyzing software, ipra
${FO_PACKAGE_COMMON_DESCRIPTION}
This package contains the ipra agent programs and their resources.")

set(CPACK_DEBIAN_IPRA_PACKAGE_DEPENDS
    "fossology-common, fossology-copyright, libpcre3")

set(CPACK_DEBIAN_IPRA_PACKAGE_SECTION "utils")

set(CPACK_DEBIAN_KEYWORD_PACKAGE_SECTION "utils")

## FOSSOLOGY-BUCKETS
set(CPACK_DEBIAN_BUCKETS_PACKAGE_NAME "fossology-buckets")
set(CPACK_DEBIAN_BUCKETS_FILE_NAME "fossology-buckets_${FO_PACKAGE_VERSION}-1_amd64.deb")
set(CPACK_DEBIAN_BUCKETS_DESCRIPTION
"architecture for analyzing software, buckets
${FO_PACKAGE_COMMON_DESCRIPTION}
This package contains the buckets agent programs and their resources.")

set(CPACK_DEBIAN_BUCKETS_PACKAGE_DEPENDS
    "fossology-nomos, fossology-pkgagent")

set(CPACK_DEBIAN_BUCKETS_PACKAGE_SECTION "utils")

## FOSSOLOGY-MIMETYPE
set(CPACK_DEBIAN_MIMETYPE_PACKAGE_NAME "fossology-mimetype")
set(CPACK_DEBIAN_MIMETYPE_FILE_NAME "fossology-mimetype_${FO_PACKAGE_VERSION}-1_amd64.deb")
set(CPACK_DEBIAN_MIMETYPE_DESCRIPTION
"architecture for analyzing software, mimetype
${FO_PACKAGE_COMMON_DESCRIPTION}
This package contains the mimetype agent programs and their resources.")

set(CPACK_DEBIAN_MIMETYPE_PACKAGE_DEPENDS
    "fossology-common, libmagic1")

set(CPACK_DEBIAN_MIMETYPE_PACKAGE_SECTION "utils")

## FOSSOLOGY-NOMOS
set(CPACK_DEBIAN_NOMOS_PACKAGE_NAME "fossology-nomos")
set(CPACK_DEBIAN_NOMOS_FILE_NAME "fossology-nomos_${FO_PACKAGE_VERSION}-1_amd64.deb")
set(CPACK_DEBIAN_NOMOS_DESCRIPTION
"architecture for analyzing software, nomos
${FO_PACKAGE_COMMON_DESCRIPTION}
This package contains the nomos agent programs and their resources.")

set(CPACK_DEBIAN_NOMOS_PACKAGE_DEPENDS
    "fossology-common")

set(CPACK_DEBIAN_NOMOS_PACKAGE_SECTION "utils")

## FOSSOLOGY-PKGAGENT
set(CPACK_DEBIAN_PKGAGENT_PACKAGE_NAME "fossology-pkgagent")
set(CPACK_DEBIAN_PKGAGENT_FILE_NAME "fossology-pkgagent_${FO_PACKAGE_VERSION}-1_amd64.deb")
set(CPACK_DEBIAN_PKGAGENT_DESCRIPTION
"architecture for analyzing software, pkgagent
${FO_PACKAGE_COMMON_DESCRIPTION}
This package contains the pgagent agent programs and their resources.")

set(CPACK_DEBIAN_PKGAGENT_PACKAGE_DEPENDS
    "fossology-common, rpm")

set(CPACK_DEBIAN_PKGAGENT_PACKAGE_SECTION "utils")

## FOSSOLOGY-DELAGENT PACKAGE
set(CPACK_DEBIAN_DELAGENT_PACKAGE_NAME "fossology-delagent")
set(CPACK_DEBIAN_DELAGENT_FILE_NAME "fossology-delagent_${FO_PACKAGE_VERSION}-1_amd64.deb")
set(CPACK_DEBIAN_DELAGENT_DESCRIPTION
"architecture for analyzing software, delagent
${FO_PACKAGE_COMMON_DESCRIPTION}
This package contains the delagent agent programs and their resources.")

set(CPACK_DEBIAN_DELAGENT_PACKAGE_DEPENDS
    "fossology-common")

set(CPACK_DEBIAN_DELAGENT_PACKAGE_SECTION "utils")

## FOSSOLOGY-WGETAGENT PACKAGE
set(CPACK_DEBIAN_WGET_AGENT_PACKAGE_NAME "fossology-wgetagent")
set(CPACK_DEBIAN_WGET_AGENT_FILE_NAME "fossology-wgetagent_${FO_PACKAGE_VERSION}-1_amd64.deb")
set(CPACK_DEBIAN_WGET_AGENT_PACKAGE_REPLACES "fossology-wgetagent (<= 2.2.0)")
set(CPACK_DEBIAN_WGET_AGENT_DESCRIPTION
"architecture for analyzing software, wget_agent
${FO_PACKAGE_COMMON_DESCRIPTION}
This package contains the wgetagent programs and their resources.")

set(CPACK_DEBIAN_WGET_AGENT_PACKAGE_DEPENDS
    "fossology-common, wget, subversion, git")

set(CPACK_DEBIAN_WGET_AGENT_PACKAGE_SECTION "utils")

## FOSSOLOGY-DEBUG PACKAGE
set(CPACK_DEBIAN_DEBUG_PACKAGE_NAME "fossology-debug")
set(CPACK_DEBIAN_DEBUG_FILE_NAME "fossology-debug_${FO_PACKAGE_VERSION}-1_amd64.deb")
set(CPACK_DEBIAN_DEBUG_DESCRIPTION
"architecture for analyzing software, debug UI
${FO_PACKAGE_COMMON_DESCRIPTION}
This package contains the debug UI.")

set(CPACK_DEBIAN_DEBUG_PACKAGE_DEPENDS
    "fossology-common")

set(CPACK_DEBIAN_DEBUG_PACKAGE_SECTION "utils")

## FOSSOLOGY-MONK PACKAGE
set(CPACK_DEBIAN_MONK_PACKAGE_NAME "fossology-monk")
set(CPACK_DEBIAN_MONK_FILE_NAME "fossology-monk_${FO_PACKAGE_VERSION}-1_amd64.deb")
set(CPACK_DEBIAN_MONK_DESCRIPTION
"architecture for analyzing software, monk
${FO_PACKAGE_COMMON_DESCRIPTION}
This package contains the monk agent programs and their resources.")

set(CPACK_DEBIAN_MONK_PACKAGE_DEPENDS
    "fossology-common")

set(CPACK_DEBIAN_MONK_PACKAGE_SECTION "utils")

## FOSSOLOGY-MONKBULK PACKAGE
set(CPACK_DEBIAN_MONKBULK_PACKAGE_NAME "fossology-monkbulk")
set(CPACK_DEBIAN_MONKBULK_FILE_NAME "fossology-monkbulk_${FO_PACKAGE_VERSION}-1_amd64.deb")
set(CPACK_DEBIAN_MONKBULK_DESCRIPTION
"architecture for analyzing software, monk bulk scanning
${FO_PACKAGE_COMMON_DESCRIPTION}
This package contains the monkbulk agent programs and their resources.")

set(CPACK_DEBIAN_MONKBULK_PACKAGE_DEPENDS
    "fossology-common, fossology-deciderjob")

set(CPACK_DEBIAN_MONKBULK_PACKAGE_SECTION "utils")

## FOSSOLOGY-OJO PACKAGE
set(CPACK_DEBIAN_OJO_PACKAGE_NAME "fossology-ojo")
set(CPACK_DEBIAN_OJO_FILE_NAME "fossology-ojo_${FO_PACKAGE_VERSION}-1_amd64.deb")
set(CPACK_DEBIAN_OJO_DESCRIPTION
"architecture for analyzing software, ojo
${FO_PACKAGE_COMMON_DESCRIPTION}
This package contains the ojo agent programs and their resources.")

set(CPACK_DEBIAN_OJO_PACKAGE_DEPENDS
    "fossology-common")

set(CPACK_DEBIAN_OJO_PACKAGE_SECTION "utils")

## FOSSOLOGY-CLIXML PACKAGE
set(CPACK_DEBIAN_CLIXML_PACKAGE_NAME "fossology-clixml")
set(CPACK_DEBIAN_CLIXML_FILE_NAME "fossology-clixml_${FO_PACKAGE_VERSION}-1_amd64.deb")
set(CPACK_DEBIAN_CLIXML_DESCRIPTION
"architecture for analyzing software, XML based report generator
${FO_PACKAGE_COMMON_DESCRIPTION}
This package contains the clixml agent programs and their resources.")

set(CPACK_DEBIAN_CLIXML_PACKAGE_DEPENDS
    "fossology-common, php-uuid")

set(CPACK_DEBIAN_CLIXML_PACKAGE_SECTION "utils")

## FOSSOLOGY-DECIDER PACKAGE
set(CPACK_DEBIAN_DECIDER_PACKAGE_NAME "fossology-decider")
set(CPACK_DEBIAN_DECIDER_FILE_NAME "fossology-decider_${FO_PACKAGE_VERSION}-1_amd64.deb")
set(CPACK_DEBIAN_DECIDER_DESCRIPTION
"architecture for analyzing software, decider
${FO_PACKAGE_COMMON_DESCRIPTION}
This package contains the decider agent programs and their resources.")

set(CPACK_DEBIAN_DECIDER_PACKAGE_DEPENDS
    "fossology-common, fossology-deciderjob")

set(CPACK_DEBIAN_DECIDER_PACKAGE_SECTION "utils")

## FOSSOLOGY-DECIDERJOB PACKAGE
set(CPACK_DEBIAN_DECIDERJOB_PACKAGE_NAME "fossology-deciderjob")
set(CPACK_DEBIAN_DECIDERJOB_FILE_NAME "fossology-deciderjob_${FO_PACKAGE_VERSION}-1_amd64.deb")
set(CPACK_DEBIAN_DECIDERJOB_DESCRIPTION
"architecture for analyzing software, deciderjob
${FO_PACKAGE_COMMON_DESCRIPTION}
This package contains the deciderjob agent programs and their resources.")

set(CPACK_DEBIAN_DECIDERJOB_PACKAGE_DEPENDS
    "fossology-common")

set(CPACK_DEBIAN_DECIDERJOB_PACKAGE_SECTION "utils")

## FOSSOLOGY-DECISIONEXPORTER PACKAGE
set(CPACK_DEBIAN_DECISIONEXPORTER_PACKAGE_NAME "fossology-decisionexporter")
set(CPACK_DEBIAN_DECISIONEXPORTER_FILE_NAME "fossology-decisionexporter_${FO_PACKAGE_VERSION}-1_amd64.deb")
set(CPACK_DEBIAN_DECISIONEXPORTER_DESCRIPTION
"architecture for analyzing software, decisionexporter
${FO_PACKAGE_COMMON_DESCRIPTION}
This package contains the decisionexporter agent program and its resources.")

set(CPACK_DEBIAN_DECISIONEXPORTER_PACKAGE_DEPENDS
    "fossology-common")

set(CPACK_DEBIAN_DECISIONEXPORTER_PACKAGE_SECTION "utils")

## FOSSOLOGY-DECISIONIMPORTER PACKAGE
set(CPACK_DEBIAN_DECISIONIMPORTER_PACKAGE_NAME "fossology-decisionimporter")
set(CPACK_DEBIAN_DECISIONIMPORTER_FILE_NAME "fossology-decisionimporter_${FO_PACKAGE_VERSION}-1_amd64.deb")
set(CPACK_DEBIAN_DECISIONIMPORTER_DESCRIPTION
"architecture for analyzing software, decisionimporter
${FO_PACKAGE_COMMON_DESCRIPTION}
This package contains the decisionimporter agent program and its resources.")

set(CPACK_DEBIAN_DECISIONIMPORTER_PACKAGE_DEPENDS
    "fossology-common")

set(CPACK_DEBIAN_DECISIONIMPORTER_PACKAGE_SECTION "utils")

## FOSSOLOGY-READMEOSS PACKAGE
set(CPACK_DEBIAN_READMEOSS_PACKAGE_NAME "fossology-readmeoss")
set(CPACK_DEBIAN_READMEOSS_FILE_NAME "fossology-readmeoss_${FO_PACKAGE_VERSION}-1_amd64.deb")
set(CPACK_DEBIAN_READMEOSS_DESCRIPTION
"architecture for analyzing software, OSS readme generator
${FO_PACKAGE_COMMON_DESCRIPTION}
This package contains the readmeoss agent programs and their resources.")

set(CPACK_DEBIAN_READMEOSS_PACKAGE_DEPENDS
    "fossology-common, fossology-copyright")

set(CPACK_DEBIAN_READMEOSS_PACKAGE_SECTION "utils")

## FOSSOLOGY-COMPATIBILITY PACKAGE
set(CPACK_DEBIAN_COMPATIBILITY_PACKAGE_NAME "fossology-compatibility")
set(CPACK_DEBIAN_COMPATIBILITY_FILE_NAME "fossology-compatibility_${FO_PACKAGE_VERSION}-1_amd64.deb")
set(CPACK_DEBIAN_COMPATIBILITY_DESCRIPTION
        "architecture for analyzing software, compatibility decider agent
${FO_PACKAGE_COMMON_DESCRIPTION}
This package contains the compatibility agent programs and their resources.")

set(CPACK_DEBIAN_COMPATIBILITY_PACKAGE_DEPENDS
        "fossology-common, fossology-decider, fossology-deciderjob")

set(CPACK_DEBIAN_COMPATIBILITY_PACKAGE_SECTION "utils")

## FOSSOLOGY-CYCLONEDX PACKAGE
set(CPACK_DEBIAN_CYCLONEDX_PACKAGE_NAME "fossology-cyclonedx")
set(CPACK_DEBIAN_CYCLONEDX_FILE_NAME "fossology-cyclonedx_${FO_PACKAGE_VERSION}-1_amd64.deb")
set(CPACK_DEBIAN_CYCLONEDX_DESCRIPTION
"architecture for analyzing software, cyclonedx generator
${FO_PACKAGE_COMMON_DESCRIPTION}
This package contains the cyclonedx agent programs and their resources.")

set(CPACK_DEBIAN_CYCLONEDX_PACKAGE_DEPENDS
    "fossology-common, fossology-copyright")

set(CPACK_DEBIAN_CYCLONEDX_PACKAGE_SECTION "utils")

## FOSSOLOGY-RESO PACKAGE
set(CPACK_DEBIAN_RESO_PACKAGE_NAME "fossology-reso")
set(CPACK_DEBIAN_RESO_FILE_NAME "fossology-reso_${FO_PACKAGE_VERSION}-1_amd64.deb")
set(CPACK_DEBIAN_RESO_DESCRIPTION
"architecture for analyzing software, reso
${FO_PACKAGE_COMMON_DESCRIPTION}
This package contains the reso agent programs and their resources.")

set(CPACK_DEBIAN_RESO_PACKAGE_DEPENDS
    "fossology-common")

set(CPACK_DEBIAN_RESO_PACKAGE_SECTION "utils")

## FOSSOLOGY-UNIFIEDREPORT PACKAGE
set(CPACK_DEBIAN_UNIFIEDREPORT_PACKAGE_NAME "fossology-unifiedreport")
set(CPACK_DEBIAN_UNIFIEDREPORT_FILE_NAME "fossology-unifiedreport_${FO_PACKAGE_VERSION}-1_amd64.deb")
set(CPACK_DEBIAN_UNIFIEDREPORT_DESCRIPTION
"architecture for analyzing software, Microsoft Word report generator
${FO_PACKAGE_COMMON_DESCRIPTION}
This package contains the unifiedreport agent programs and their resources.")

set(CPACK_DEBIAN_UNIFIEDREPORT_PACKAGE_DEPENDS
    "fossology-common, php-zip, php-xml")

set(CPACK_DEBIAN_UNIFIEDREPORT_PACKAGE_SECTION "utils")

## FOSSOLOGY-REUSER PACKAGE
set(CPACK_DEBIAN_REUSER_PACKAGE_NAME "fossology-reuser")
set(CPACK_DEBIAN_REUSER_FILE_NAME "fossology-reuser_${FO_PACKAGE_VERSION}-1_amd64.deb")
set(CPACK_DEBIAN_REUSER_DESCRIPTION
"architecture for reusing clearing result of other uploads, reuser
${FO_PACKAGE_COMMON_DESCRIPTION}
This package contains the reuser agent programs and their resources.")

set(CPACK_DEBIAN_REUSER_PACKAGE_DEPENDS
    "fossology-common")

set(CPACK_DEBIAN_REUSER_PACKAGE_SECTION "utils")

## FOSSOLOGY-SPDX PACKAGE
set(CPACK_DEBIAN_SPDX_PACKAGE_NAME "fossology-spdx")
set(CPACK_DEBIAN_SPDX_FILE_NAME "fossology-spdx_${FO_PACKAGE_VERSION}-1_amd64.deb")
set(CPACK_DEBIAN_SPDX_DESCRIPTION
"architecture for analyzing software, SPDX v2.0 and v3.0 generator
${FO_PACKAGE_COMMON_DESCRIPTION}
This package contains the spdx agent programs and their resources.")

set(CPACK_DEBIAN_SPDX_PACKAGE_DEPENDS
    "fossology-common")

set(CPACK_DEBIAN_SPDX_PACKAGE_SECTION "utils")

## FOSSOLOGY-REPORTIMPORT PACKAGE
set(CPACK_DEBIAN_REPORTIMPORT_PACKAGE_NAME "fossology-reportimport")
set(CPACK_DEBIAN_REPORTIMPORT_FILE_NAME "fossology-reportimport_${FO_PACKAGE_VERSION}-1_amd64.deb")
set(CPACK_DEBIAN_REPORTIMPORT_DESCRIPTION
"architecture for analyzing software, report importer
${FO_PACKAGE_COMMON_DESCRIPTION}
This package contains the reportimport agent programs and their resources.")

set(CPACK_DEBIAN_REPORTIMPORT_PACKAGE_DEPENDS
    "fossology-common, php-mbstring")

set(CPACK_DEBIAN_REPORTIMPORT_PACKAGE_SECTION "utils")

## FOSSOLOGY-SOFTWAREHERITAGE PACKAGE
set(CPACK_DEBIAN_SOFTWAREHERITAGE_PACKAGE_NAME "fossology-softwareheritage")
set(CPACK_DEBIAN_SOFTWAREHERITAGE_FILE_NAME "fossology-softwareheritage_${FO_PACKAGE_VERSION}-1_amd64.deb")
set(CPACK_DEBIAN_SOFTWAREHERITAGE_DESCRIPTION
"architecture for fetching the origin of a file software heritage archive.
${FO_PACKAGE_COMMON_DESCRIPTION}
This package contains the softwareheritage agent programs and their resources.")

set(CPACK_DEBIAN_SOFTWAREHERITAGE_PACKAGE_DEPENDS
    "fossology-common, fossology-ununpack, fossology-wgetagent")

set(CPACK_DEBIAN_SOFTWAREHERITAGE_PACKAGE_SECTION "utils")

## FOSSOLOGY-SPASHT PACKAGE
set(CPACK_DEBIAN_SPASHT_PACKAGE_NAME "fossology-spasht")
set(CPACK_DEBIAN_SPASHT_FILE_NAME "fossology-spasht_${FO_PACKAGE_VERSION}-1_amd64.deb")
set(CPACK_DEBIAN_SPASHT_DESCRIPTION
"architecture to connect clearlyDefined, spasht
${FO_PACKAGE_COMMON_DESCRIPTION}
This package contains the spasht agent programs and their resources.")

set(CPACK_DEBIAN_SPASHT_PACKAGE_DEPENDS
    "fossology-common")

set(CPACK_DEBIAN_SPASHT_PACKAGE_SECTION "utils")


## include CPACK
include(CPack)

## specify groups

## fossology-common package group
cpack_add_component_group(fossology-common)
cpack_add_component(cli REQUIRED GROUP fossology-common)
cpack_add_component(common REQUIRED GROUP fossology-common)
cpack_add_component(maintagent REQUIRED GROUP fossology-common)
cpack_add_component(lib REQUIRED GROUP fossology-common)
cpack_add_component(vendor REQUIRED GROUP fossology-common)

if(NOT MONOPACK)
## fossology-ununpack package group
cpack_add_component_group(fossology-ununpack)
cpack_add_component(ununpack REQUIRED GROUP fossology-ununpack)
cpack_add_component(adj2nest REQUIRED GROUP fossology-ununpack)
endif()
