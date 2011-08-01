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

extern CU_TestInfo testcases_GetFieldValue[];
extern CU_TestInfo testcases_GetMetadata[];
extern CU_TestInfo testcases_Trim[];
//extern CU_TestInfo testcases_ProcessUpload[];
extern CU_TestInfo testcases_RecordMetadataRPM[];
extern CU_TestInfo testcases_RecordMetadataDEB[];

CU_SuiteInfo suites[] = {
    {"Testing the function trim:", NULL, NULL, testcases_Trim},
    {"Testing the function GetFieldValue:", NULL, NULL, testcases_GetFieldValue},
    {"Testing the function GetMetadata:", NULL, NULL, testcases_GetMetadata},
    //{"Testing the function ProcessUpload:", NULL, NULL, testcases_ProcessUpload},
    {"Testing the function RecordMetadataRPM:", NULL, NULL, testcases_RecordMetadataRPM},
    {"Testing the function RecordMetadataDEB:", NULL, NULL, testcases_RecordMetadataDEB},
    CU_SUITE_INFO_NULL
};

void AddTests(void)
{
  assert(NULL != CU_get_registry());
  assert(!CU_is_test_running());


  if(CUE_SUCCESS != CU_register_suites(suites)){
    fprintf(stderr, "Register suites failed - %s ", CU_get_error_msg());
    exit(EXIT_FAILURE);
  }
}

int main( int argc, char *argv[] )
{
  printf("test start\n");
  if(CU_initialize_registry()){

    fprintf(stderr, "\nInitialization of Test Registry failed.\n");
    exit(EXIT_FAILURE);
  }else{
    AddTests();

    CU_set_output_filename("Pkgagent Test");
    CU_list_tests_to_file();
    CU_automated_run_tests();
    //CU_cleanup_registry();
  }
  printf("end\n");
  printf("Results:\n");
  printf("  Number of suites run: %d\n", CU_get_number_of_suites_run());
  printf("  Number of tests run: %d\n", CU_get_number_of_tests_run());
  printf("  Number of tests failed: %d\n", CU_get_number_of_tests_failed());
  printf("  Number of asserts: %d\n", CU_get_number_of_asserts());
  printf("  Number of successes: %d\n", CU_get_number_of_successes());
  printf("  Number of failures: %d\n", CU_get_number_of_failures());
  CU_cleanup_registry();
  return 0;
}
