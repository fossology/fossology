/*
 SPDX-FileCopyrightText: © 2010 Hewlett-Packard Development Company, L.P.

 SPDX-License-Identifier: GPL-2.0-only
*/
/**
 * \dir
 * \brief Unit test cases for ununpack agent
 * \file
 * \brief Unit test runner for ununpack agent
 */
#include "run_tests.h"
#include "../agent/ununpack_globals.h"

#define AGENT_DIR "../../"
/* globals that mostly shouldn't be globals */
char *Filename = "";              ///< Filename
char *NewDir = "./test-result";   ///< Test result directory
int Recurse = -1;                 ///< Level of unpack recursion. Default to infinite
int exists = 0;                   ///< Default not exists
char *DBConfFile = NULL;          ///< DB conf file location

/* ************************************************************************** */
/* **** test suite ********************************************************** */
/* ************************************************************************** */
extern CU_TestInfo ExtractAR_testcases[];       ///< AR test cases
extern CU_TestInfo ExtractZstd_testcases[];      ///< Zstd test cases
extern CU_TestInfo ununpack_iso_testcases[];    ///< ISO test cases
extern CU_TestInfo ununpack_disk_testcases[];   ///< Disk image test cases
extern CU_TestInfo CopyFile_testcases[];        ///< Copy test cases
extern CU_TestInfo FindCmd_testcases[];         ///< FindCmd() test cases
extern CU_TestInfo Prune_testcases[];           ///< Prune() test cases
extern CU_TestInfo RunCommand_testcases[];      ///< Run test cases
extern CU_TestInfo Traverse_testcases[];        ///< Traverse() test cases
extern CU_TestInfo TraverseChild_testcases[];   ///< TraverseChild() test cases
extern CU_TestInfo TraverseStart_testcases[];   ///< TraverseStart() test cases
extern CU_TestInfo TaintString_testcases[];     ///< TaintString() test cases
extern CU_TestInfo IsFunctions_testcases[];     ///< Isxxx() test cases
extern CU_TestInfo ContainerInfo_testcases[];   ///< Container info test cases
extern CU_TestInfo Checksum_testcases[];        ///< Checksum test cases
extern CU_TestInfo PathCheck_testcases[];       ///< Pacth check test cases
extern CU_TestInfo DBInsertPfile_testcases[];   ///< DB insertion test cases (pfile)
extern CU_TestInfo DBInsertUploadTree_testcases[];  ///< DB insertion test cases (uploadtree)

CU_SuiteInfo suites[] =
{
  // ununpack-ar.c
  {"ExtractAR", NULL, NULL, NULL, NULL, ExtractAR_testcases},

  // ununpack-zstd.c
  {"ExtractZstd", NULL, NULL, NULL, NULL, ExtractZstd_testcases},

  // ununpack-iso.c
  {"ununpack-iso", NULL, NULL, NULL, NULL, ununpack_iso_testcases},

  // ununpack-disk.c
  {"ununpack-disk", NULL, NULL, (CU_SetUpFunc)FatDiskNameInit, (CU_TearDownFunc)FatDiskNameClean, ununpack_disk_testcases},

  // utils.c
  {"CopyFile", NULL, NULL, (CU_SetUpFunc)CopyFileInit, (CU_TearDownFunc)CopyFileClean, CopyFile_testcases},
  /** \todo not working {"FindCmd", NULL, NULL, NULL, NULL, FindCmd_testcases}, */
  {"Prune", NULL, NULL, (CU_SetUpFunc)PruneInit, (CU_TearDownFunc)PruneClean, Prune_testcases},
  {"RunCommand", NULL, NULL, NULL, NULL, RunCommand_testcases},
  {"TaintString", NULL, NULL, NULL, NULL, TaintString_testcases},
  {"IsFunctions", NULL, NULL, NULL, NULL, IsFunctions_testcases},
  {"ContainerInfo", NULL, NULL, NULL, NULL, ContainerInfo_testcases},
  {"PathCheck", NULL, NULL, NULL, NULL, PathCheck_testcases},
  //{"DBInsert", DBInsertInit, DBInsertClean, DBInsert_testcases},

  // traverse.c
  {"Traverse", NULL, NULL, (CU_SetUpFunc)TraverseInit, (CU_TearDownFunc)TraverseClean, Traverse_testcases},
  {"TraverseChild", NULL, NULL, (CU_SetUpFunc)TraverseChildInit, NULL, TraverseChild_testcases},
  {"TraverseStart", NULL, NULL, (CU_SetUpFunc)TraverseStartInit, (CU_TearDownFunc)TraverseStartClean, TraverseStart_testcases},

  // checksum.c
  {"checksum", NULL, NULL, NULL, NULL, Checksum_testcases},

  //utils.c
  {"DBInsertPfile", NULL, NULL, (CU_SetUpFunc)DBInsertInit, (CU_TearDownFunc)DBInsertClean, DBInsertPfile_testcases},
  {"DBInsertUploadTree", NULL, NULL, (CU_SetUpFunc)DBInsertInit, (CU_TearDownFunc)DBInsertClean, DBInsertUploadTree_testcases},

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
  dropTestEnvironment(dbManager, AGENT_DIR, "ununpack");
  return(rc);
}
