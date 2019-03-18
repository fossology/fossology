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
/**
 * \file
 * \brief Unit test cases for TraverseStart()
 */
static char *Label = "";

/**
 * @brief initialize
 */
int  TraverseStartInit()
{
  MagicCookie = magic_open(MAGIC_PRESERVE_ATIME|MAGIC_MIME);
  if (MagicCookie == NULL)
  {
    fprintf(stderr,"FATAL: Failed to initialize magic cookie\n");
    return -1;
  }

  magic_load(MagicCookie,NULL);
  return 0;
}

/**
 * @brief clean env and others
 */
int TraverseStartClean()
{
  magic_close(MagicCookie);
  return 0;
}

/**
 * \brief Test TraverseStart() for normal file
 * \test
 * -# Pass a normal file location to TraverseStart()
 * -# Check if files are unpacked
 */
void testTraverseStartNormal()
{
  Filename = "../testdata/testthree.zip";
  deleteTmpFiles("./test-result/");
  exists = file_dir_exists("./test-result/testthree.zip.dir/testtwo.zip.dir/test.zip.dir/ununpack");
  FO_ASSERT_EQUAL(exists, 0); // ./test-result/ is not existing
  TraverseStart(Filename, Label, NewDir, Recurse);
  exists = file_dir_exists("./test-result/testthree.zip.dir/testtwo.zip.dir/test.zip.dir/ununpack");
  FO_ASSERT_EQUAL(exists, 1); // ./test-result/ is existing
}

/**
 * @brief test TraverseStart() for directory
 * \test
 * -# Pass a directory's location to TraverseStart()
 * -# Check if files are unpacked
 */
void testTraverseStartDir()
{
  Filename = "../testdata/";
  deleteTmpFiles("./test-result/");
  exists = file_dir_exists("./test-result/test.jar.dir/ununpack");
  FO_ASSERT_EQUAL(exists, 0); // ./test-result/ is not existing
  TraverseStart(Filename, Label, NewDir, Recurse);
  exists = file_dir_exists("./test-result/test.jar.dir/ununpack");
  FO_ASSERT_EQUAL(exists, 1); // ./test-result/ is existing
}


/* ************************************************************************** */
/* **** cunit test cases **************************************************** */
/* ************************************************************************** */

CU_TestInfo TraverseStart_testcases[] =
{
  {"TraverseStart normal:", testTraverseStartNormal},
  {"TraverseStart dir:", testTraverseStartDir},
  CU_TEST_INFO_NULL
};
