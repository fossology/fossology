/*
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
 */

/**
 * rununit.c
 * \brief run one or more CUnit test suites
 *
 *  Created on: Aug 6, 2010
 *      Author: markd
 * @version "$Id: rununit.c 3568 2010-10-15 20:05:57Z rrando $"
 */

#include <stdio.h>
#include <stdlib.h>
#include <assert.h>
#include "CUnit/Console.h"
#include "Cunit/Basic.h"
#include "Cunit/Automated.h"

extern CU_TestInfo testcases_calc_add[];
extern CU_TestInfo testcases_calc_minus[];
extern CU_TestInfo testcases_comp_max[];
extern int init_suite_max(void);
extern int clean_suite_max(void);

CU_SuiteInfo suites[] = { { "Testing the function add at calc.c:", NULL, NULL,
    testcases_calc_add }, { "Testing the function minus at calc.c:", NULL,
    NULL, testcases_calc_minus }, { "Testing the function at comp.c:",
    init_suite_max, clean_suite_max, testcases_comp_max }, CU_SUITE_INFO_NULL };

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

int main(int argc, char *argv[])
{
  int basic;
  int console;
  int automated;

  /* parse the command line options */
  while ((c = getopt(argc, argv, "abc")) != -1)
  {
    switch (c)
    {
      case 'a': /* basic mode */
        basic = 1;
        break;
      case 'b': /* basic mode */
        basic = 1;
        break;
      case 'c': /* run from command line */
        console = 1;
        break;
      default: /* error, print usage */
        usage(argv[0]);
        return -1;
    }
  }

  if (CU_initialize_registry())
  {

    fprintf(stderr, "\nInitialization of Test Registry failed.\n");
    exit(EXIT_FAILURE);
  }
  else
  {
    AddTests();

    // set up the run mode and run the tests
    if (automated)
    {
      CU_set_output_filename("TestOutput.xml");
      CU_list_tests_to_file();
      CU_automated_run_tests();
    }
    else if (basic)
    {
      CU_BasicRunMode mode = CU_BRM_VERBOSE;
      CU_ErrorAction error_action = CUEA_IGNORE;
      CU_basic_set_mode(mode);
      CU_set_error_action(error_action);
      CU_basic_run_tests();
    }
    else if (console)
    {
      CU_console_run_tests();
    }
    CU_cleanup_registry();
  }
  return 0;
}
