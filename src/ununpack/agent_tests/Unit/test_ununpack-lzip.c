/*
 SPDX-FileCopyrightText: © 2025 Siemens Healthineers AG
 SPDX-FileContributor: Sushant Kumar <sushant.kumar@siemens-healthineers.com>

 SPDX-License-Identifier: GPL-2.0-only
*/

#include "run_tests.h"

/**
 * \file
 * \brief Unit test cases for ExtractLzip()
 */

/* locals */
static int Result = 0;

/**
 * @brief unpack lzip file
 * \test
 * -# Try to extract `.lz` file using ExtractLzip()
 * -# Check if the files are unpacked
 */
void testExtractLzipFile()
{
  deleteTmpFiles("./test-result/");
  exists = file_dir_exists("./test-result/");
  FO_ASSERT_EQUAL(exists, 0); // not existing

  MkDirs("./test-result/test.lz.dir/");
  Filename = "../testdata/test.lz";
  /* Ensure test.lz contains a file named 'data.tar' for this assertion */
  Result = ExtractLzip(Filename, "test.lz", "./test-result/test.lz.dir");
  
  exists = file_dir_exists("./test-result/test.lz.dir/test");
  FO_ASSERT_EQUAL(exists, 1); // existing
  FO_ASSERT_EQUAL(Result, 0); // Extract lzip successfully
}

/**
 * @brief unpack tlz (tar.lz) file
 * \test
 * -# Try to extract `.tlz` archives using ExtractLzip()
 * -# Check if the files are unpacked
 */
void testExtractTlzFile()
{
  deleteTmpFiles("./test-result/");
  exists = file_dir_exists("./test-result/");
  FO_ASSERT_EQUAL(exists, 0);

  MkDirs("./test-result/test.tlz.dir/");
  Filename = "../testdata/test.tlz";
  Result = ExtractLzip(Filename, "test.tlz", "./test-result/test.tlz.dir");
  
  exists = file_dir_exists("./test-result/test.tlz.dir/test.tar");
  FO_ASSERT_EQUAL(exists, 1);
  FO_ASSERT_EQUAL(Result, 0);
}

/**
 * @brief unpack tar.lz file
 * \test
 * -# Try to extract `.tar.lz` archives using ExtractLzip()
 * -# Check if the files are unpacked
 */
void testExtractTarLzFile()
{
  deleteTmpFiles("./test-result/");
  exists = file_dir_exists("./test-result/");
  FO_ASSERT_EQUAL(exists, 0); 

  MkDirs("./test-result/test.tar.lz.dir/");
  Filename = "../testdata/test.tar.lz";
  Result = ExtractLzip(Filename, "test.tar.lz", "./test-result/test.tar.lz.dir");

  exists = file_dir_exists("./test-result/test.tar.lz.dir/test.tar");
  FO_ASSERT_EQUAL(exists, 1); 
  FO_ASSERT_EQUAL(Result, 0); 
}

/**
 * @brief abnormal parameters
 * \test
 * -# Call ExtractLzip() with empty parameters
 * -# Check if the function return NOT OK
 */
void testExtractLzipEmptyParameters()
{
  deleteTmpFiles("./test-result/");
  exists = file_dir_exists("./test-result/");
  FO_ASSERT_EQUAL(exists, 0);

  Result = ExtractLzip("", "", "");
  FO_ASSERT_EQUAL(Result, 1);
}

/**
 * @brief abnormal parameters
 * \test
 * -# Try to extract non-existent archives using ExtractLzip()
 * -# Check if the function return NOT OK
 */
void testExtractLzipErrorParameters()
{
  deleteTmpFiles("./test-result/");
  exists = file_dir_exists("./test-result/");
  FO_ASSERT_EQUAL(exists, 0);

  MkDirs("./test-result/no_file.dir/");
  Filename = "../testdata/non_existent_file.lz";
  Result = ExtractLzip(Filename, "non_existent_file.lz", "./test-result/no_file.dir");
  FO_ASSERT_EQUAL(Result, 1);
}

/* ************************************************************************** */
/* **** cunit test cases **************************************************** */
/* ************************************************************************** */

CU_TestInfo ExtractLzip_testcases[] =
{
  {"Testing function ExtractLzip for .lz file:", testExtractLzipFile},
  {"Testing function ExtractLzip for .tlz file:", testExtractTlzFile},
  {"Testing function ExtractLzip for .tar.lz file:", testExtractTarLzFile},
  {"Testing function ExtractLzip for empty parameters:", testExtractLzipEmptyParameters},
  {"Testing function ExtractLzip for error parameters:", testExtractLzipErrorParameters},
  CU_TEST_INFO_NULL
};
