/*
 SPDX-FileCopyrightText: © 2010-2011 Hewlett-Packard Development Company, L.P.

 SPDX-License-Identifier: GPL-2.0-only
*/
#include "run_tests.h"
/**
 * \file
 * \brief Unit test cases for ExtractDisk()
 */
/* locals */
static int Result = 0;

/**
 * @brief unpack disk image, ext2
 * \test
 * -# Try to extract ext2 fs images using ExtractDisk()
 * -# Check if the files are unpacked
 */
void testExtractDisk4Ext2()
{
  deleteTmpFiles("./test-result/");
  exists = file_dir_exists("./test-result/");
  FO_ASSERT_EQUAL(exists, 0); // not existing
  Filename = "../testdata/ext2file.fs";
  MkDirs("./test-result/ext2file.fs.dir");
  Result = ExtractDisk(Filename, "ext", "./test-result/ext2file.fs.dir");
  exists = file_dir_exists("./test-result/ext2file.fs.dir/testtwo.zip");
  FO_ASSERT_EQUAL(Result, 0);
  FO_ASSERT_EQUAL(exists, 1);
}

/**
 * @brief unpack disk image, ext3
 * \test
 * -# Try to extract ext3 fs images using ExtractDisk()
 * -# Check if the files are unpacked
 */
void testExtractDisk4Ext3()
{
  deleteTmpFiles("./test-result/");
  exists = file_dir_exists("./test-result/");
  FO_ASSERT_EQUAL(exists, 0); // not existing
  Filename = "../testdata/ext3file.fs";
  MkDirs("./test-result/ext3file.fs");
  Result = ExtractDisk(Filename, "ext", "./test-result/ext3file.fs.dir");
  exists = file_dir_exists("./test-result/ext3file.fs.dir/testtwo.zip");
  FO_ASSERT_EQUAL(Result, 0);
  FO_ASSERT_EQUAL(exists, 1);
}

/**
 * @brief unpack disk image, ext2, FStype is unknown
 * \test
 * -# Try to extract ext2 fs images using ExtractDisk() with empty FStype
 * -# Check if function returns NOT OK
 * -# Check if function has not unpacked the files
 */
void testExtractDisk4Ext2FstypeUnknow()
{
  deleteTmpFiles("./test-result/");
  exists = file_dir_exists("./test-result/");
  FO_ASSERT_EQUAL(exists, 0); // not existing
  Filename = "../testdata/ext2file.fs";
  MkDirs("./test-result/ext2file.fs.dir");
  Result = ExtractDisk(Filename, "", "./test-result/ext2file.fs.dir");
  exists = file_dir_exists("./test-result/ext2file.fs.dir/testtwo.zip");
  FO_ASSERT_EQUAL(Result, 1);
  FO_ASSERT_EQUAL(exists, 0);
}

/**
 * @brief unpack disk image, fat
 * \test
 * -# Try to extract fat fs images using ExtractDisk()
 * -# Check if the files are unpacked
 */
void testExtractDisk4Fat()
{
  deleteTmpFiles("./test-result/");
  exists = file_dir_exists("./test-result/");
  FO_ASSERT_EQUAL(exists, 0); // not existing
  Filename = "../testdata/fatfile.fs";
  MkDirs("./test-result/fatfile.fs.dir");
  Result = ExtractDisk(Filename, "fat", "./test-result/fatfile.fs.dir");
  exists = file_dir_exists("./test-result/fatfile.fs.dir/testtwo.zip");
  FO_ASSERT_EQUAL(Result, 0);
  FO_ASSERT_EQUAL(exists, 1);
}

/**
 * @brief unpack disk image, ntfs
 * \test
 * -# Try to extract ntfs fs images using ExtractDisk()
 * -# Check if the files are unpacked
 */
void testExtractDisk4Ntfs()
{
  deleteTmpFiles("./test-result/");
  exists = file_dir_exists("./test-result/");
  FO_ASSERT_EQUAL(exists, 0); // not existing
  Filename = "../testdata/ntfsfile.fs";
  MkDirs("./test-result/ntfsfile.fs.dir");
  Result = ExtractDisk(Filename, "ntfs", "./test-result/ntfsfile.fs.dir");
  exists = file_dir_exists("./test-result/ntfsfile.fs.dir/testtwo.zip");
  FO_ASSERT_EQUAL(Result, 0);
  FO_ASSERT_EQUAL(exists, 1);
}


/* locals */
static char *Name = NULL;


/**
 * @brief initialize
 */
int  FatDiskNameInit()
{
  Name = (char *)malloc(100);
  return 0;
}

/**
 * @brief clean env and others
 */
int FatDiskNameClean()
{
  free(Name);
  return 0;
}

/**
 * @brief Convert to lowercase.
 * \test
 * -# Pass a string with upper case letters to FatDiskName()
 * -# Check if the string is returned in lower case
 */
void testFatDiskName1()
{
  strcpy(Name, "Fossology\0");
  FatDiskName(Name);
  FO_ASSERT_EQUAL(strcmp(Name, "fossology"), 0);
}

/**
 * @brief Convert to lowercase.
 * \test
 * -# Pass a string with upper case letters to FatDiskName()
 * -# Check if the string is returned in lower case
 * -# Pass a string with upper case letters and spaces to FatDiskName()
 * -# Check if the string is returned in lower case with spaces in place
 */
void testFatDiskName2()
{
  strcpy(Name, "Fosso\0");
  FatDiskName(Name);
  FO_ASSERT_EQUAL(strcmp(Name, "fosso"), 0);

  strcpy(Name, "FOSSOLOGY HELLO\0");
  FatDiskName(Name);
  FO_ASSERT_EQUAL(strcmp(Name, "fossology hello"), 0);
}

/**
 * @brief Convert to lowercase.
 * \test
 * -# Pass a string with upper case letters and text in `()` to FatDiskName()
 * -# Check if the string is returned in lower case without `()`
 */
void testFatDiskName3()
{
  strcpy(Name, "Fosso (hello)");
  FatDiskName(Name);
  FO_ASSERT_EQUAL(strcmp(Name, "fosso"), 0);
}

/**
 * @brief Convert to lowercase.
 * \test
 * -# Pass an empty string to FatDiskName()
 * -# Check if empty string is returned
 */
void testFatDiskNameNameEmpty()
{
  strcpy(Name, "");
  FatDiskName(Name);
  FO_ASSERT_EQUAL(strcmp(Name, ""), 0);
}


/* ************************************************************************** */
/* **** cunit test cases **************************************************** */
/* ************************************************************************** */

CU_TestInfo ununpack_disk_testcases[] =
{
  {"ExtractDisk: ext2 image:", testExtractDisk4Ext2},
  {"ExtractDisk: ext3 image:", testExtractDisk4Ext3},
  {"ExtractDisk: ext2 image, fs type is unknowed:", testExtractDisk4Ext2FstypeUnknow},
  {"ExtractDisk: fat image:", testExtractDisk4Fat},
  {"ExtractDisk: nfts image:", testExtractDisk4Ntfs},
  {"FatDiskName: 1:", testFatDiskName1},
  {"FatDiskName: 2:", testFatDiskName2},
  {"FatDiskName: 3:", testFatDiskName3},
  {"FatDiskName: empty parameter", testFatDiskNameNameEmpty},
  CU_TEST_INFO_NULL
};
