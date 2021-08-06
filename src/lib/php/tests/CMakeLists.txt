#[=======================================================================[
SPDX-License-Identifier: GPL-2.0-only
SPDX-FileCopyrightText: © 2021 Avinal Kumar <avinal.xlvii@gmail.com>
#]=======================================================================]

if(NOT TARGET phpunit)
    prepare_phpunit()
endif()

add_test(NAME test_phplib
    COMMAND ${PHPUNIT} --log-junit ${CMAKE_CURRENT_BINARY_DIR}/lib-php-Xunit-Results.xml --configuration ${CMAKE_CURRENT_LIST_DIR}/tests.xml)
