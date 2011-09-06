/* **************************************************************
 Copyright (C) 2011 Hewlett-Packard Development Company, L.P.

 This program is free software; you can redistribute it and/or
 modify it under the terms of the GNU General Public License
 version 2 as published by the Free Software Foundation.

 This program is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 GNU General Public License for more details.

 You should have received a copy of the GNU General Public License along
 with this program; if not, write to the Free Software Foundation, Inc.,
 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
************************************************************** */
#include <stdio.h>
#include <stdlib.h>
#include <assert.h>
#include "CUnit/CUnit.h"
#include "CUnit/Automated.h"

/**
 * \brief cunit main test function
 *
 * \param char *test_name - the test name, who invoke this cunit_main, if no failure,
 * will get test report for this test, the test report are test_name-Result.xml
 * and test_name-Listing.xml.
 * also can get a brief command line report, if test_name is mimetype, the brief report is like:
 * #mimetype# cunit test results:
 *  Number of suites run: 6
 *  Number of tests run: 6
 *  Number of tests failed: 0
 *  Number of asserts: 9
 *  Number of successes: 9
 *  Number of failures: 0
 *
 * \return 0 on sucess, not 1 on failure
 */
int cunit_main(char *TestName);
