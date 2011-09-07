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
 * \brief cunit main test function
 *        This function standardizes how CUnit tests are reported.
 *
 *  There are three parts to the test output.
 *  - {test_name}-Listing.xml
 *  - {test_name}-Result.xml
 *  - stdout, to which is printed a run summary and any specific error details 
 (    (the actual and expected results).
 *
 *  The caller also needs to use the FO_ macros in libcunit.h instead of the CU_ macros.
 *
 * \param test_name - the test name
 * \param suites - the test suite array
 *
 * \return 0 on sucess, not 1 on failure
 */
int cunit_main(char *test_name, CU_SuiteInfo *suites)
{
  /** test name is empty? */
  if (!test_name)
  {
    fprintf(stderr, "FATAL: empty test name.\n");
    exit(1);
  }

  if (CU_initialize_registry())
  {
    fprintf(stderr, "FATAL: Initialization of Test Registry failed.\n");
    exit(1);
  } 

  assert(CU_get_registry());
  assert(!CU_is_test_running());

  if(CU_register_suites(suites) != CUE_SUCCESS)
  {
    fprintf(stderr, "FATAL: Register suites failed - %s\n", CU_get_error_msg());
    exit(1);
  }

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

  return 0;
}

