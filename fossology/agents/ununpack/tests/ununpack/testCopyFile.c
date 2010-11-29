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

/* cunit includes */
#include <CUnit/CUnit.h>
#include "utility.h"
#include <sys/stat.h>

/* tested function */
int	CopyFile	(char *Src, char *Dst);

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
  Src = "./test-data/testdata4unpack/imagefile.iso";
  deleteTmpFiles("./test-result/");
  strcpy(Dst, "./test-result/hello");
  stat(Src, &statSrc);
  Result = CopyFile(Src, Dst);
  stat(Dst, &statDst);
  CU_ASSERT_EQUAL(statSrc.st_size, statDst.st_size);
  CU_ASSERT_EQUAL(Result, 0);
}

/**
 * @brief copy directory 
 */
void testCopyFileNormalDir()
{
  Src = "./test-data/testdata4unpack/testdir";
  strcpy(Dst, "./test-result/hello");
  deleteTmpFiles("./test-result/");
  Result = CopyFile(Src, Dst);
  existed = file_dir_existed("./test-result/hello/test.tar");
  CU_ASSERT_EQUAL(existed, 0); // no existing
  CU_ASSERT_EQUAL(Result, 1); // is directory
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
  existed = file_dir_existed("./test-result");
  CU_ASSERT_EQUAL(existed, 0); // no existing
  CU_ASSERT_EQUAL(Result, 1); // failed
}

CU_TestInfo CopyFile_testcases[] =
{
  {"Testing the function CopyFile, file name:", testCopyFileNormalFile},
  {"Testing the function CopyFile, dir name:", testCopyFileNormalDir},
  {"Testing the function CopyFile, file name  is empty:", testCopyFileAbnormal},
  CU_TEST_INFO_NULL
};

