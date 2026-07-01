#[=======================================================================[
SPDX-License-Identifier: GPL-2.0-only
SPDX-FileCopyrightText: © 2026 Adesh Deshmukh <adeshkd123@gmail.com>
#]=======================================================================]

if(NOT TARGET phpunit)
    prepare_phpunit()
endif()

add_test(NAME reportImport_unit_test
    COMMAND ${PHPUNIT} --bootstrap ${PHPUNIT_BOOTSTRAP} ${CMAKE_CURRENT_LIST_DIR}/Unit/ReportImportAgentPluginTest.php)
