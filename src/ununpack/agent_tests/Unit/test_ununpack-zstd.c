/*
 SPDX-FileCopyrightText: © 2023 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/
#include "run_tests.h"
/**
 * \file
 * \brief Unit test cases for ExtractZstd()
 */
/* locals */
static int Result = 0;


 /**
  * @brief unpack ZSTd file
  * \test
  * -# Try to extract `.zst` file using ExtractZstd()
  * -# Check if the files are unpacked
  */
void testExtractZstdFile()
{
  deleteTmpFiles("./test-result/");
  exists = file_dir_exists("./test-result/");
  FO_ASSERT_EQUAL(exists, 0); // not existing
  MkDirs("./test-result/test.zst.dir/");
  Filename = "../testdata/test.zst";
  Result = ExtractAR(Filename, "./test-result/test.ar.zst");
  exists = file_dir_exists("./test-result/test.zst.dir/test.tar");
  FO_ASSERT_EQUAL(exists, 1); // existing
  FO_ASSERT_EQUAL(Result, 0); // Extract ZST file successfully
}

/**
 * @brief unpack lz4 file
 * \test
 * -# Try to extract `.lz4` archives using ExtractZst()
 * -# Check if the files are unpacked
 */
void testExtractZstlz4File()
{
  deleteTmpFiles("./test-result/");
  exists = file_dir_exists("./test-result/");
  FO_ASSERT_EQUAL(exists, 0); // not existing
  MkDirs("./test-result/test.lz4.dir/");
  Filename = "../testdata/test.lz4";
  Result = ExtractAR(Filename, "./test-result/test.lz4.dir");
  exists = file_dir_exists("./test-result/test.lz4.dir/data.tar");
  FO_ASSERT_EQUAL(exists, 1); // existing
  FO_ASSERT_EQUAL(Result, 0); // Extract lz4 successfully
}

/**
 * @brief unpack lzma file
 * \test
 * -# Try to extract `.lzma` archives using ExtractZst()
 * -# Check if the files are unpacked
 */
void testExtractZstlzmaFile()
{
  deleteTmpFiles("./test-result/");
  exists = file_dir_exists("./test-result/");
  FO_ASSERT_EQUAL(exists, 0); // not existing
  MkDirs("./test-result/test.lzma.dir/");
  Filename = "../testdata/test.lzma";
  Result = ExtractAR(Filename, "./test-result/test.lzma.dir");
  exists = file_dir_exists("./test-result/test.lzma.dir/data.tar");
  FO_ASSERT_EQUAL(exists, 1); // existing
  FO_ASSERT_EQUAL(Result, 0); // Extract lzma successfully
}

/**
 * @brief abnormal parameters
 * \test
 * -# Call ExtractZst() with empty parameters
 * -# Check if the function return NOT OK
 */
void testExtractZst4EmptyParameters()
{
  deleteTmpFiles("./test-result/");
  exists = file_dir_exists("./test-result/");
  FO_ASSERT_EQUAL(exists, 0); // not existing
  Result = ExtractAR("", ""); // empty parameters
  FO_ASSERT_EQUAL(Result, 1); // fail to extract
}

/**
 * @brief abnormal parameters
 * \test
 * -# Try to extract `null_file` archives using ExtractZst()
 * -# Check if the function return NOT OK
 */
void testExtractZst4ErrorParameters()
{
  deleteTmpFiles("./test-result/");
  exists = file_dir_exists("./test-result/");
  FO_ASSERT_EQUAL(exists, 0); // not existing
  MkDirs("./test-result/null_file.dir/");
  Filename = "../testdata/null_file";
  Result = ExtractAR(Filename, "./test-result/null_file.dir");
  FO_ASSERT_EQUAL(Result, 1); // fail to extract
}

/* ************************************************************************** */
/* **** cunit test cases **************************************************** */
/* ************************************************************************** */

CU_TestInfo ExtractZstd_testcases[] =
{
  {"Testing function testExtractZst for archive file:", testExtractZstdFile},
  {"Testing function testExtractZst for lz4 file:", testExtractZstlz4File},
  {"Testing function testExtractZst for lzma file:", testExtractZstlzmaFile},
  {"Testing function testExtractZst for abnormal parameters:", testExtractZst4EmptyParameters},
  {"Testing function testExtractZst for error parameters:", testExtractZst4ErrorParameters},
  CU_TEST_INFO_NULL
};
