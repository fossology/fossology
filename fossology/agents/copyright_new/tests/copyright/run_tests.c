/*********************************************************************
Copyright (C) 2010 Hewlett-Packard Development Company, L.P.

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

/* std library includes */
#include <stdio.h>
#include <assert.h>

/* cunit includes */
#include <CUnit/CUnit.h>
#include <CUnit/Automated.h>

/* ************************************************************************** */
/* **** test case sets ****************************************************** */
/* ************************************************************************** */

extern CU_TestInfo function_registry_testcases[];
extern CU_TestInfo cvector_testcases[];
extern CU_TestInfo copyright_local_testcases[];
extern CU_TestInfo copyright_testcases[];
extern CU_TestInfo radix_testcases[];

/* ************************************************************************** */
/* **** create test suite *************************************************** */
/* ************************************************************************** */

CU_SuiteInfo suites[] =
{
    {"Testing function registry:", NULL, NULL, function_registry_testcases},
    {"Testing cvector:", NULL, NULL, cvector_testcases},
    {"Testing radix tree:", NULL, NULL,  radix_testcases},
    {"Testing copyright locals:", NULL, NULL, copyright_local_testcases},
    {"Testing copyright:", NULL, NULL, copyright_testcases},
    CU_SUITE_INFO_NULL
};

/* ************************************************************************** */
/* **** main test functions ************************************************* */
/* ************************************************************************** */

int main(int argc, char** argv)
{
  if(CU_initialize_registry())
  {
    fprintf(stderr, "Initialization of Test Registry failed.'n");
    return -1;
  }
  else
  {
    assert(CU_get_registry());
    assert(!CU_is_test_running());

    if(CU_register_suites(suites) != CUE_SUCCESS)
    {
      fprintf(stderr, "Register suites failed - %s\n", CU_get_error_msg());
      return -1;
    }

    CU_set_output_filename("Copyright_Tests");
    CU_list_tests_to_file();
    CU_automated_run_tests();

    printf("Results:\n");
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




