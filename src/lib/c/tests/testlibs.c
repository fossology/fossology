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

/* std library includes */
#include <stdio.h>
#include <assert.h>

/* cunit includes */
#include <CUnit/CUnit.h>
#include <CUnit/Basic.h>
#include <CUnit/Automated.h>

/* ************************************************************************** */
/* * test case sets                                                         * */
/* *    when adding the tests for a library, simply add the "extern"        * */
/* *    declaration and add it to the suites array                          * */
/* ************************************************************************** */

char* dbConf;

extern CU_TestInfo fossconfig_testcases[];
extern CU_TestInfo fossscheduler_testcases[];
extern CU_TestInfo libfossdb_testcases[];
extern CU_TestInfo libfossdbmanager_testcases[];

/**
* array of every test suite. There should be at least one test suite for every
* library includes in libfossology.
*/
CU_SuiteInfo suites[] =
  {
    {"Testing libfossdb", NULL, NULL, libfossdb_testcases},
    {"Testing fossconfig", NULL, NULL, fossconfig_testcases},
    {"Testing libfossdbmanger", NULL, NULL, libfossdbmanager_testcases},
    // TODO fix { "Testing fossscheduler", NULL, NULL, fossscheduler_testcases },
    CU_SUITE_INFO_NULL
  };

/* ************************************************************************** */
/* **** main function ******************************************************* */
/* ************************************************************************** */

int main(int argc, char** argv)
{
  if (argc>1)
    dbConf = argv[1];
  else
    dbConf = NULL;

  CU_pFailureRecord FailureList;
  CU_RunSummary* pRunSummary;
  int FailRec;

  if (CU_initialize_registry())
  {
    fprintf(stderr, "Initialization of Test Registry failed.'n");
    return -1;
  }

  assert(CU_get_registry());
  assert(!CU_is_test_running());

  if (CU_register_suites(suites) != CUE_SUCCESS)
  {
    fprintf(stderr, "Register suites failed - %s\n", CU_get_error_msg());
    return -1;
  }

  CU_set_output_filename("lib-c");
  CU_list_tests_to_file();
  CU_automated_run_tests();

  pRunSummary = CU_get_run_summary();
  printf("Results:\n");
  printf("  Number of suites run: %d\n", pRunSummary->nSuitesRun);
  printf("  Number of suites failed: %d\n", pRunSummary->nSuitesFailed);
  printf("  Number of tests run: %d\n", pRunSummary->nTestsRun);
  printf("  Number of tests failed: %d\n", pRunSummary->nTestsFailed);
  printf("  Number of asserts: %d\n", pRunSummary->nAsserts);
  printf("  Number of asserts failed: %d\n", pRunSummary->nAssertsFailed);
  printf("  Number of failures: %d\n", pRunSummary->nFailureRecords);

  /* Print any failures */
  if (pRunSummary->nFailureRecords)
  {
    printf("\nFailures:\n");
    FailRec = 1;
    for (FailureList = CU_get_failure_list(); FailureList; FailureList = FailureList->pNext)
    {
      printf("%d. File: %s  Line: %u   Test: %s\n",
        FailRec,
        FailureList->strFileName,
        FailureList->uiLineNumber,
        (FailureList->pTest)->pName);
      printf("  %s\n",
        FailureList->strCondition);
      FailRec++;
    }
    printf("\n");
  }

  CU_cleanup_registry();

  return 0;
}
