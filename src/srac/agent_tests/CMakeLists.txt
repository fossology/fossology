#[=======================================================================[
SPDX-License-Identifier: GPL-2.0-only
SPDX-FileCopyrightText: © 2026 Fossology contributors
#]=======================================================================]

if(NOT TARGET phpunit)
    prepare_phpunit()
endif()

add_test(NAME srac_unit_tests
    COMMAND ${PHPUNIT} --bootstrap ${PHPUNIT_BOOTSTRAP} ${CMAKE_CURRENT_LIST_DIR}/Unit)
