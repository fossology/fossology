/*********************************************************************
Copyright (C) 2010-2012 Hewlett-Packard Development Company, L.P.

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
 * \brief Unit test cases for ExtractAR()
 */
/* locals */
static int Result = 0;


 /**
  * @brief unpack archive library file
  * \test
  * -# Try to extract `.ar` library file using ExtractAR()
  * -# Check if the files are unpacked
  */
void testExtractAR4ArchiveLibraryFile()
{
  deleteTmpFiles("./test-result/");
  exists = file_dir_exists("./test-result/");
  FO_ASSERT_EQUAL(exists, 0); // not existing
  MkDirs("./test-result/test.ar.dir/");
  Filename = "../testdata/test.ar";
  Result = ExtractAR(Filename, "./test-result/test.ar.dir");
  exists = file_dir_exists("./test-result/test.ar.dir/test.tar");
  FO_ASSERT_EQUAL(exists, 1); // existing
  FO_ASSERT_EQUAL(Result, 0); // Extract archieve library successfully
}

/**
 * @brief unpack deb file
 * \test
 * -# Try to extract `.deb` archives using ExtractAR()
 * -# Check if the files are unpacked
 */
void testExtractAR4DebFile()
{
  deleteTmpFiles("./test-result/");
  exists = file_dir_exists("./test-result/");
  FO_ASSERT_EQUAL(exists, 0); // not existing
  MkDirs("./test-result/test.deb.dir/");
  Filename = "../testdata/test.deb";
  Result = ExtractAR(Filename, "./test-result/test.deb.dir");
  exists = file_dir_exists("./test-result/test.deb.dir/data.tar.xz");
  FO_ASSERT_EQUAL(exists, 1); // existing
  FO_ASSERT_EQUAL(Result, 0); // Extract archieve library successfully
}

/**
 * @brief abnormal parameters
 * \test
 * -# Call ExtractAR() with empty parameters
 * -# Check if the function return NOT OK
 */
void testExtractAR4EmptyParameters()
{
  deleteTmpFiles("./test-result/");
  exists = file_dir_exists("./test-result/");
  FO_ASSERT_EQUAL(exists, 0); // not existing
  Result = ExtractAR("", ""); // empty parameters
  FO_ASSERT_EQUAL(Result, 1); // fail to Extract archieve library
}

/**
 * @brief abnormal parameters
 * \test
 * -# Try to extract `.rpm` archives using ExtractAR()
 * -# Check if the function return NOT OK
 */
void testExtractAR4ErrorParameters()
{
  deleteTmpFiles("./test-result/");
  exists = file_dir_exists("./test-result/");
  FO_ASSERT_EQUAL(exists, 0); // not existing
  MkDirs("./test-result/test.rpm.dir/");
  Filename = "../testdata/test.rpm";
  Result = ExtractAR(Filename, "./test-result/test.dir");
  FO_ASSERT_EQUAL(Result, 1); // fail to Extract archieve library
}

/* ************************************************************************** */
/* **** cunit test cases **************************************************** */
/* ************************************************************************** */

CU_TestInfo ExtractAR_testcases[] =
{
  {"Testing function testExtractAR for archive library file:", testExtractAR4ArchiveLibraryFile},
  {"Testing function testExtractAR for deb file:", testExtractAR4DebFile},
  {"Testing function testExtractAR for abnormal parameters:", testExtractAR4EmptyParameters},
  {"Testing function testExtractAR for error parameters:", testExtractAR4ErrorParameters},
  CU_TEST_INFO_NULL
};
