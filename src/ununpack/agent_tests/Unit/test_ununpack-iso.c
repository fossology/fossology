/*
 SPDX-FileCopyrightText: © 2010-2012 Hewlett-Packard Development Company, L.P.

 SPDX-License-Identifier: GPL-2.0-only
*/
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
