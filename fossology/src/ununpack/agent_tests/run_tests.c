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

#include "run_tests.h"
#include "../agent/ununpack_globals.h"

/* globals that mostly shouldn't be globals */
char *Filename = "";
char *NewDir = "./test-result";
int Recurse = -1;
int existed = 0; // default not existed
magic_t MagicCookie;


/* ************************************************************************** */
/* **** test suite ********************************************************** */
/* ************************************************************************** */
extern CU_TestInfo ExtractAR_testcases[];
extern CU_TestInfo ununpack_iso_testcases[];
extern CU_TestInfo ununpack_disk_testcases[];
extern CU_TestInfo CopyFile_testcases[];
extern CU_TestInfo FindCmd_testcases[];
extern CU_TestInfo Prune_testcases[];
extern CU_TestInfo RunCommand_testcases[];
extern CU_TestInfo Traverse_testcases[];
extern CU_TestInfo TraverseChild_testcases[];
extern CU_TestInfo TraverseStart_testcases[];

CU_SuiteInfo suites[] = 
{
  // ununpack-ar.c
  {"ExtractAR", NULL, NULL, ExtractAR_testcases},

  // ununpack-iso.c
  {"ununpack-iso", NULL, NULL, ununpack_iso_testcases},

  // ununpack-disk.c
  {"ununpack-disk", FatDiskNameInit, FatDiskNameClean, ununpack_disk_testcases},

  // utils.c
  {"CopyFile", CopyFileInit, CopyFileClean, CopyFile_testcases},
  {"FindCmd", FindCmdInit, FindCmdClean, FindCmd_testcases},
  {"Prune", PruneInit, PruneClean, Prune_testcases},
  {"RunCommand", NULL, NULL, RunCommand_testcases},

  // traverse.c
  {"Traverse", TraverseInit, TraverseClean, Traverse_testcases},
  {"TraverseChild", TraverseChildInit, NULL, TraverseChild_testcases},
  {"TraverseStart", TraverseStartInit, TraverseStartClean, TraverseStart_testcases},

  CU_SUITE_INFO_NULL
};


/**
 * @brief juge if the file or directory is existed not
 * @param path_name, the file or directory name including path
 * @return existed or not, 0: not existed, 1: existed
 */
int file_dir_existed(char *path_name)
{
  struct stat sts;
  int existed = 1; // 0: not existed, 1: existed, default existed
  if ((stat (path_name, &sts)) == -1)
  {
    //printf ("The file or dir %s doesn't exist...\n", path_name);
    existed = 0;
  }
  return existed;
}


/* ************************************************************************** */
/* **** test failure function *********************************************** */
/* ************************************************************************** */

void test_failure_function()
{
  static int num_failed = 0;
  if(CU_get_number_of_tests_failed() != num_failed)
  {
    printf(" FAILED");
    num_failed++;
  }
}

/* external function to test if a particular test failed */
void (*test_failure)(void) = test_failure_function;

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

    CU_set_output_filename("Ununpack_Tests");
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




