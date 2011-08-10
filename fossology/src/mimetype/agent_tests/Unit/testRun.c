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

#include <stdio.h>
#include <stdlib.h>
#include <assert.h>
#include "CUnit/CUnit.h"
#include "CUnit/Automated.h"
#include "testRun.h"

/**
 * \file testRun.c
 * \brief main function for in this testing module
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
 * \brief  main test function
 */
int main( int argc, char *argv[] )
{
  printf("test start\n");
  if (CU_initialize_registry())
  {

    fprintf(stderr, "\nInitialization of Test Registry failed.\n");
    exit(EXIT_FAILURE);
  } else
  {
    AddTests();
    /** mimetype */
    CU_set_output_filename("mimetype");
    CU_list_tests_to_file();
    CU_automated_run_tests();
    CU_cleanup_registry();
  }
  printf("end\n");
  return 0;
}

