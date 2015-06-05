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

#define AGENT_DIR "../../"
/* globals that mostly shouldn't be globals */
char *Filename = "";
char *NewDir = "./test-result";
int Recurse = -1;
int exists = 0; // default not exists
magic_t MagicCookie;
char *DBConfFile = NULL;

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
extern CU_TestInfo TaintString_testcases[];
extern CU_TestInfo IsFunctions_testcases[];
extern CU_TestInfo ContainerInfo_testcases[];
extern CU_TestInfo Checksum_testcases[];
extern CU_TestInfo PathCheck_testcases[];
extern CU_TestInfo DBInsertPfile_testcases[];
extern CU_TestInfo DBInsertUploadTree_testcases[];

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
  {"FindCmd", NULL, FindCmdClean, FindCmd_testcases},
  {"Prune", PruneInit, PruneClean, Prune_testcases},
  {"RunCommand", NULL, NULL, RunCommand_testcases},
  {"TaintString", NULL, NULL, TaintString_testcases},
  {"IsFunctions", NULL, NULL, IsFunctions_testcases},
  {"ContainerInfo", NULL, NULL, ContainerInfo_testcases},
  {"PathCheck", NULL, NULL, PathCheck_testcases},
  //{"DBInsert", DBInsertInit, DBInsertClean, DBInsert_testcases},

  // traverse.c
  {"Traverse", TraverseInit, TraverseClean, Traverse_testcases},
  {"TraverseChild", TraverseChildInit, NULL, TraverseChild_testcases},
  {"TraverseStart", TraverseStartInit, TraverseStartClean, TraverseStart_testcases},

  // checksum.c
  {"checksum", NULL, NULL, Checksum_testcases},

  //utils.c
  {"DBInsertPfile", DBInsertInit, DBInsertClean, DBInsertPfile_testcases},
  {"DBInsertUploadTree", DBInsertInit, DBInsertClean, DBInsertUploadTree_testcases}, 

  CU_SUITE_INFO_NULL
};


/**
 * @brief test if a file or directory exists
 * @param path_name, the pathname of a file or directory to test
 * @return 0=path_name does not exist, 1=exists
 */
int file_dir_exists(char *path_name)
{
  struct stat sts;
  int exists = 1; // 0: not exists, 1: exists, default exists

  if ((stat (path_name, &sts)) == -1) exists = 0;
  return exists;
}


/* ************************************************************************** */
/* **** main test functions ************************************************* */
/* ************************************************************************** */

int main(int argc, char** argv)
{

  fo_dbManager* dbManager = createTestEnvironment(AGENT_DIR, "ununpack", 1);
  if(!dbManager) {
    printf("Unable to connect to test database\n");
    return 1;
  }

  DBConfFile = get_dbconf();

  int rc = focunit_main(argc, argv, "ununpack_Tests", suites);
  dropTestEnvironment(dbManager, AGENT_DIR);
  return(rc);
}




