/*
 SPDX-FileCopyrightText: © 2010-2011 Hewlett-Packard Development Company, L.P.

 SPDX-License-Identifier: GPL-2.0-only
*/
/**
 * \file
 * \brief Unit test cases related to file handling
 */
#include "run_tests.h"

/* local variables */
static char *Src = "";      ///< Souce location
static char *Dst = NULL;    ///< Destination location
static struct stat statSrc; ///< Stat of source
static struct stat statDst; ///< Stat of destination
static int Result = 0;      ///< Result of calls


/**
 * @brief initialize
 */
int  CopyFileInit()
{
  Dst = (char *)malloc(100);
  return 0;
}

/**
 * @brief clean env and others
 */
int CopyFileClean()
{
  free(Dst);
  return 0;
}


/* test functions */

/**
 * \brief copy directory
 * \test
 * -# Add a new directory to Dst
 * -# Call CopyFile() to copy a file from Src to Dst
 * -# Check if file was copied to new directory
 */
void testCopyFileNormalFile()
{
  Src = "../testdata/test.iso";
  deleteTmpFiles("./test-result/");
  strcpy(Dst, "./test-result/hello");
  stat(Src, &statSrc);
  Result = CopyFile(Src, Dst);
  stat(Dst, &statDst);
  FO_ASSERT_EQUAL((int)statSrc.st_size, (int)statDst.st_size);
  FO_ASSERT_EQUAL(Result, 0);
}

/**
 * \brief copy directory
 * \test
 * -# Call CopyFile() to copy directory
 * -# Check if the function returns 1
 * -# Check if a file under Src is not copied to Dst
 */
void testCopyFileNormalDir()
{
  Src = "../testdata";
  strcpy(Dst, "./test-result/hello");
  deleteTmpFiles("./test-result/");
  Result = CopyFile(Src, Dst);
  exists = file_dir_exists("./test-result/hello/test.tar");
  FO_ASSERT_EQUAL(exists, 0); // no existing
  FO_ASSERT_EQUAL(Result, 1); // is directory
}

/**
 * \brief parameters are null
 * \test
 * -# Call CopyFile() on an empty Src
 * -# Check if function returns 1
 * -# Check if function did not do anything
 */
void testCopyFileAbnormal()
{
  Src = "";
  strcpy(Dst, "./test-result/hello");
  deleteTmpFiles("./test-result/");
  Result = CopyFile(Src, Dst);
  exists = file_dir_exists("./test-result");
  FO_ASSERT_EQUAL(exists, 0); // no existing
  FO_ASSERT_EQUAL(Result, 1); // failed
}


/* ************************************************************************** */
/* **** cunit test cases **************************************************** */
/* ************************************************************************** */

CU_TestInfo CopyFile_testcases[] =
{
  {"CopyFile: file name", testCopyFileNormalFile},
  {"CopyFile: dir name", testCopyFileNormalDir},
  {"CopyFile: file name is empty", testCopyFileAbnormal},
  CU_TEST_INFO_NULL
};
