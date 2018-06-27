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
 * \brief Unit test cases for ExtractISO()
 */
/* locals */
static int Result = 0;

/**
 * @brief unpack iso file
 * \test
 * -# Pass an ISO to ExtractISO()
 * -# Check if function returns 0 and files are unpacked
 */
void testExtractISO()
{
  deleteTmpFiles("./test-result/");
  exists = file_dir_exists("./test-result/");
  FO_ASSERT_EQUAL(exists, 0); // not existing
  Filename = "../testdata/test.iso";
  MkDirs("./test-result/test.iso.dir");
  Result = ExtractISO(Filename, "./test-result/test.iso.dir");
  FO_ASSERT_EQUAL(Result, 0);

  exists = file_dir_exists("./test-result/test.iso.dir/test1.zip.tar.dir/test1.zip");
  FO_ASSERT_EQUAL(exists, 1);
}

/**
 * @brief abnormal parameters
 * \test
 * -# Pass an empty strings to ExtractISO()
 * -# Check if function returns 1
 */
void testExtractISO4EmptyParameters()
{
  deleteTmpFiles("./test-result/");
  exists = file_dir_exists("./test-result/");
  FO_ASSERT_EQUAL(exists, 0); // not existing
  Result = ExtractISO("", ""); // empty parameters
  FO_ASSERT_EQUAL(Result, 1); // fail to Extract
}

/**
 * @brief abnormal parameters
 * \test
 * -# Pass a non ISO to ExtractISO()
 * -# Check if function returns 1 and files are not unpacked
 */
void testExtractISO4ErrorParameters()
{
  deleteTmpFiles("./test-result/");
  exists = file_dir_exists("./test-result/");
  FO_ASSERT_EQUAL(exists, 0); // not existing
  Filename = "../testdata/test_1.orig.tar.gz";
  MkDirs("./test-result/test_1.orig.tar.gz.dir");
  Result = ExtractISO(Filename, "./test-result/test_1.orig.tar.gz.dir");
  FO_ASSERT_EQUAL(Result, 0); // fail to Extract
}

/* ************************************************************************** */
/* **** cunit test cases **************************************************** */
/* ************************************************************************** */

CU_TestInfo ununpack_iso_testcases[] =
{
  {"testExtractISO: iso file:", testExtractISO},
  {"testExtractISO: abnormal parameters:", testExtractISO4EmptyParameters},
  {"testExtractISO: error parameters:", testExtractISO4ErrorParameters},
  CU_TEST_INFO_NULL
};
