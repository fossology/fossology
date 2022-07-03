/*
 SPDX-FileCopyrightText: © 2010-2011 Hewlett-Packard Development Company, L.P.

 SPDX-License-Identifier: GPL-2.0-only
*/
#include "run_tests.h"
/**
 * \file
 * \brief Unit test cases for Prune()
 */
/* local variables */
static char *Fname = "";
struct stat Stat;
static char *Dst = NULL;
static int Result = 0;


/**
 * @brief initialize
 */
int  PruneInit()
{
  Dst = (char *)malloc(100);
  return 0;
}

/**
 * @brief clean env and others
 */
int PruneClean()
{
  free(Dst);
  return 0;
}

/* test functions */

/**
 * @brief regular file, size is 0
 * \test
 * -# Copy a null file (size 0) and call Prune()
 * -# Check if directory is removed
 */
void testPruneFileFileSzieIs0()
{
  Fname = "../testdata/null_file";
  deleteTmpFiles(NewDir);
  strcpy(Dst, "./test-result/nullfile");
  stat(Fname, &Stat);
  CopyFile(Fname, Dst);
  Result = Prune(Dst, Stat);
  exists = file_dir_exists(Dst);
  FO_ASSERT_EQUAL(exists, 0); //  not  existing
  FO_ASSERT_EQUAL(Result, 1); // pruned
}

/**
 * @brief regular file, size is great than 0
 * \test
 * -# Copy a regular file and call Prune()
 * -# Check if directory is not removed
 */
void testPruneRegFile()
{
  Fname = "../testdata/test.ar";
  deleteTmpFiles(NewDir);
  strcpy(Dst, "./test-result/test.ar");
  stat(Fname, &Stat);
  CopyFile(Fname, Dst);
  Result = Prune(Dst, Stat);
  exists = file_dir_exists(Dst);
  FO_ASSERT_EQUAL(exists, 1); // existing
  FO_ASSERT_EQUAL(Result, 0); // not pruned
}

#if 0
/**
 * @brief character file
 * \test
 * -# Copy a character file and call Prune()
 * -# Check if directory is removed
 */
void testPruneCharFile()
{
  Fname = "../testdata/ext2file.fs";
  stat(Fname, &Stat);
  Result = Prune(Fname, Stat);
  exists = file_dir_exists(Fname);
  FO_ASSERT_EQUAL(exists, 0); //  not  existing
  FO_ASSERT_EQUAL(Result, 1); // pruned
}
#endif


/* ************************************************************************** */
/* **** cunit test cases **************************************************** */
/* ************************************************************************** */

CU_TestInfo Prune_testcases[] =
{
  {"Prune: file size is 0", testPruneFileFileSzieIs0},
  {"Prune: regular file, size > 0", testPruneRegFile},
  CU_TEST_INFO_NULL
};
