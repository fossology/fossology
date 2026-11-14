#[=======================================================================[
SPDX-License-Identifier: GPL-2.0-only
Author: Shaheem Azmal M MD <shaheem.azmal@gmail.com>
SPDX-FileCopyrightText: © 2026 Siemens AG
#]=======================================================================]

add_test(NAME cyclonedx_report_generator_test
    COMMAND php ${CMAKE_CURRENT_LIST_DIR}/test_report_output.php)
