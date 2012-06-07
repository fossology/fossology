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
 * @brief regular  file, size is 0
 */
void testPruneFileFileSzieIs0()
{
  Fname = "../test-data/testdata4unpack/null_file";
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
 * @brief regular  file, size is great than 0
 */
void testPruneRegFile()
{
  Fname = "../test-data/testdata4unpack/libfossagent.a";
  deleteTmpFiles(NewDir);
  strcpy(Dst, "./test-result/libfossagent.a");
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
 */
void testPruneCharFile()
{
  Fname = "../test-data/testdata4unpack/cfile";
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
