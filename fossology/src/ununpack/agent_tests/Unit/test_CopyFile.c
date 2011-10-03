/*********************************************************************
Copyright (C) 2010-2011 Hewlett-Packard Development Company, L.P.

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

/* local variables */
static char *Src = "";
static char *Dst = NULL;
static struct stat statSrc;
static struct stat statDst;
static int Result = 0;


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
 * @brief copy directory 
 */
void testCopyFileNormalFile()
{
  Src = "../test-data/testdata4unpack/imagefile.iso";
  deleteTmpFiles("./test-result/");
  strcpy(Dst, "./test-result/hello");
  stat(Src, &statSrc);
  Result = CopyFile(Src, Dst);
  stat(Dst, &statDst);
  FO_ASSERT_EQUAL((int)statSrc.st_size, (int)statDst.st_size);
  FO_ASSERT_EQUAL(Result, 0);
}

/**
 * @brief copy directory 
 */
void testCopyFileNormalDir()
{
  Src = "../test-data/testdata4unpack/testdir";
  strcpy(Dst, "./test-result/hello");
  deleteTmpFiles("./test-result/");
  Result = CopyFile(Src, Dst);
  exists = file_dir_exists("./test-result/hello/test.tar");
  FO_ASSERT_EQUAL(exists, 0); // no existing
  FO_ASSERT_EQUAL(Result, 1); // is directory
}

/**
 * @brief parameters are null
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
