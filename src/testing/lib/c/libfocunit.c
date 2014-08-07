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

#include "libfocunit.h"

/**
 * \brief fossology cunit main test function
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
 * \param argc - number of command line arguments
 * \param argv - command line arguments
 * \param test_name - the test name
 * \param suites - the test suite array
 *
 * \return 0 on sucess, not 1 on failure
 */
int focunit_main(int argc, char **argv, char *test_name, CU_SuiteInfo *suites)
{
  int iopt;
  char *SuiteName = 0;
  char *TestName = 0;
  CU_pTestRegistry pRegistry;
  CU_pSuite pSuite;
  CU_pTest pTest;
  CU_ErrorCode ErrCode;
  CU_pFailureRecord FailureList;
  CU_RunSummary *pRunSummary;
  int FailRec;

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

  if (CU_register_suites(suites) != CUE_SUCCESS)
  {
    fprintf(stderr, "FATAL: Register suites failed - %s\n", CU_get_error_msg());
    exit(1);
  }
  pRegistry = CU_get_registry();

  /* option -s suitename to run
   *        -t testname to run
   */
  while ((iopt = getopt(argc, argv, "s:t:")) != -1)
  {
    switch (iopt)
    {
    case 's':
      SuiteName = optarg;
      break;
    case 't':
      TestName = optarg;
      break;
    default:
      fprintf(stderr, "Invalid argument for %s\n", argv[0]);
      exit(-1);
    }
  }

  /* If TestName is specified, SuiteName is required */
  if (TestName && !SuiteName)
  {
    fprintf(stderr, "A Suite name (-s) is required if you specify a test name.\n");
    exit(-1);
  }

  if (SuiteName)
  {
    pSuite = CU_get_suite_by_name(SuiteName, pRegistry);
    if (!pSuite)
    {
      fprintf(stderr, "Suite %s not found.\n", SuiteName);
      exit(-1);
    }

    if (TestName)
    {
      pTest = CU_get_test_by_name(TestName, pSuite);
      if (!pTest)
      {
        fprintf(stderr, "Test %s not found in suite %s.\n", TestName, SuiteName);
        exit(-1);
      }
      ErrCode = CU_run_test(pSuite, pTest);
    }
    else
    {
      ErrCode = CU_run_suite(pSuite);
    }

    if (ErrCode)
    {
      fprintf(stderr, "Error: %s\n", CU_get_error_msg());
      exit(-1);
    }
  }
  else // generate xml test report via Automated mode only when run all suits in pRegistrAy, or not

  {
    CU_set_output_filename(test_name);
    CU_list_tests_to_file();
    CU_automated_run_tests();
  }

  pRunSummary = CU_get_run_summary();
  printf("%s summary:\n", test_name);
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
