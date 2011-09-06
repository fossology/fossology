/*********************************************************************
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
*********************************************************************/

#include "libcunit.h"

/**
 * \file libcunit.c
 */

extern CU_SuiteInfo suites[];

void AddTests(void)
{
  assert(NULL != CU_get_registry());
  assert(!CU_is_test_running());


  if (CUE_SUCCESS != CU_register_suites(suites))
  {
    fprintf(stderr, "Register suites failed - %s ", CU_get_error_msg());
    exit(EXIT_FAILURE);
  }
}

/**
 * \brief cunit main test function
 *
 * \param char *test_name - the test name, who invoke this cunit_main, if no failure,
 * will get test report for this test, the test report are test_name-Result.xml
 * and test_name-Listing.xml
 *
 * \return 0 on sucess, not 1 on failure
 */
int cunit_main(char *test_name)
{
  /** test name is empty? */
  if (!test_name)
  {
    printf("error, the test name is empty\n");
    exit(1);
  }
  if (CU_initialize_registry())
  {
    fprintf(stderr, "\nInitialization of Test Registry failed.\n");
    exit(EXIT_FAILURE);
  } else
  {
    AddTests();
    CU_set_output_filename(test_name);
    CU_list_tests_to_file();
    CU_automated_run_tests();
    printf(" #%s# cunit test results:\n", test_name);
    printf("  Number of suites run: %d\n", CU_get_number_of_suites_run());
    printf("  Number of tests run: %d\n", CU_get_number_of_tests_run());
    printf("  Number of tests failed: %d\n", CU_get_number_of_tests_failed());
    printf("  Number of asserts: %d\n", CU_get_number_of_asserts());
    printf("  Number of successes: %d\n", CU_get_number_of_successes());
    printf("  Number of failures: %d\n", CU_get_number_of_failures());
    CU_cleanup_registry();
  }
  return 0;
}

