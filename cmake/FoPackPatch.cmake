#[=======================================================================[
SPDX-License-Identifier: GPL-2.0-only
SPDX-FileCopyrightText: © 2021 Avinal Kumar <avinal.xlvii@gmail.com>
#]=======================================================================]

foreach(comp IN LISTS CPACK_COMPONENTS_ALL)
    string(TOUPPER "CPACK_DEBIAN_${comp}_PACKAGE_NAME" package_name_var)
    string(TOLOWER "${${package_name_var}}" package_name)
    set(copyright_dir "${CPACK_TEMPORARY_DIRECTORY}/${comp}/usr/share/doc/${package_name}")
    configure_file("${CPACK_RESOURCE_FILE_LICENSE}" "${copyright_dir}/copyright"
        COPYONLY NO_SOURCE_PERMISSIONS)
    configure_file("${CPACK_RESOURCE_FILE_README}" "${copyright_dir}/README.Debian"
        COPYONLY NO_SOURCE_PERMISSIONS)
    configure_file("${CPACK_RESOURCE_FILE_CHANGELOG}" "${copyright_dir}/changelog.Debian.gz"
        COPYONLY NO_SOURCE_PERMISSIONS)
endforeach()

## Specific to fossology-common (missing from package)
set(package_name "${CPACK_DEBIAN_FOSSOLOGY-COMMON_PACKAGE_NAME}")
set(copyright_dir "${CPACK_TEMPORARY_DIRECTORY}/fossology-common/usr/share/doc/${package_name}")
configure_file("${CPACK_RESOURCE_FILE_LICENSE}" "${copyright_dir}/copyright"
    COPYONLY NO_SOURCE_PERMISSIONS)
configure_file("${CPACK_RESOURCE_FILE_README_COMMON}" "${copyright_dir}/README.Debian"
    COPYONLY NO_SOURCE_PERMISSIONS)
configure_file("${CPACK_RESOURCE_FILE_README_MD}" "${copyright_dir}/README.md.gz"
    COPYONLY NO_SOURCE_PERMISSIONS)
configure_file("${CPACK_RESOURCE_FILE_CHANGELOG}" "${copyright_dir}/changelog.Debian.gz"
    COPYONLY NO_SOURCE_PERMISSIONS)
