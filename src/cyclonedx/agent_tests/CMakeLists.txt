#[=======================================================================[
SPDX-License-Identifier: GPL-2.0-only
#]=======================================================================]

if(NOT TARGET phpunit)
    prepare_phpunit()
endif()

add_test(NAME cyclonedx_unit_bom_header_test
    COMMAND ${PHPUNIT} --bootstrap ${PHPUNIT_BOOTSTRAP}
    ${CMAKE_CURRENT_LIST_DIR}/Unit/BomReportGeneratorTest.php) 