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

/* locals */
static int Result = 0;

/**
 * @brief unpack disk image, ext2
 */
void testExtractDisk4Ext2()
{
  deleteTmpFiles("./test-result/");
  exists = file_dir_exists("./test-result/");
  FO_ASSERT_EQUAL(exists, 0); // not existing
  Filename = "../test-data/testdata4unpack/ext2test-image";
  MkDirs("./test-result/ext2test-image.dir");
  Result = ExtractDisk(Filename, "ext", "./test-result/ext2test-image.dir");
  exists = file_dir_exists("./test-result/ext2test-image.dir/ununpack.c");
  FO_ASSERT_EQUAL(Result, 0); 
  FO_ASSERT_EQUAL(exists, 1); 
}

/**
 * @brief unpack disk image, ext3
 */
void testExtractDisk4Ext3()
{
  deleteTmpFiles("./test-result/");
  exists = file_dir_exists("./test-result/");
  FO_ASSERT_EQUAL(exists, 0); // not existing
  Filename = "../test-data/testdata4unpack/ext3test-image";
  MkDirs("./test-result/ext3test-image.dir");
  Result = ExtractDisk(Filename, "ext", "./test-result/ext3test-image.dir");
  exists = file_dir_exists("./test-result/ext3test-image.dir/libfossagent.a");
  FO_ASSERT_EQUAL(Result, 0);
  FO_ASSERT_EQUAL(exists, 1);
}

/**
 * @brief unpack disk image, ext2, FStype is unknowed
 */
void testExtractDisk4Ext2FstypeUnknow()
{
  deleteTmpFiles("./test-result/");
  exists = file_dir_exists("./test-result/");
  FO_ASSERT_EQUAL(exists, 0); // not existing
  Filename = "../test-data/testdata4unpack/ext2test-image";
  MkDirs("./test-result/ext2test-image.dir");
  Result = ExtractDisk(Filename, "", "./test-result/ext2test-image.dir");
  exists = file_dir_exists("./test-result/ext2test-image.dir/ununpack.c");
  FO_ASSERT_EQUAL(Result, 1);
  FO_ASSERT_EQUAL(exists, 0);
}

/**
 * @brief unpack disk image, fat 
 */
void testExtractDisk4Fat()
{
  deleteTmpFiles("./test-result/");
  exists = file_dir_exists("./test-result/");
  FO_ASSERT_EQUAL(exists, 0); // not existing
  Filename = "../test-data/testdata4unpack/fattest-image";
  MkDirs("./test-result/fattest-image.dir");
  Result = ExtractDisk(Filename, "fat", "./test-result/fattest-image.dir");
  exists = file_dir_exists("./test-result/fattest-image.dir/ununpack.c");
  FO_ASSERT_EQUAL(Result, 0);
  FO_ASSERT_EQUAL(exists, 1);
}

/**
 * @brief unpack disk image, ntfs
 */
void testExtractDisk4Ntfs()
{
  deleteTmpFiles("./test-result/");
  exists = file_dir_exists("./test-result/");
  FO_ASSERT_EQUAL(exists, 0); // not existing
  Filename = "../test-data/testdata4unpack/ntfstest-image";
  MkDirs("./test-result/ntfstest-image.dir");
  Result = ExtractDisk(Filename, "ntfs", "./test-result/ntfstest-image.dir");
  exists = file_dir_exists("./test-result/ntfstest-image.dir/ununpack.c");
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
 */
void testFatDiskName1()
{
  strcpy(Name, "Fossology\0");
  FatDiskName(Name);
  FO_ASSERT_EQUAL(strcmp(Name, "fossology"), 0); 
}
 
void testFatDiskName2()
{
  strcpy(Name, "Fosso\0");
  FatDiskName(Name);
  FO_ASSERT_EQUAL(strcmp(Name, "fosso"), 0);

  strcpy(Name, "FOSSOLOGY HELLO\0");
  FatDiskName(Name);
  FO_ASSERT_EQUAL(strcmp(Name, "fossology hello"), 0);
}

void testFatDiskName3()
{
  strcpy(Name, "Fosso (hello)");
  FatDiskName(Name);
  FO_ASSERT_EQUAL(strcmp(Name, "fosso"), 0);
}

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
  //    {"ExtractDisk: nfts image:", testExtractDisk4Ntfs},
  {"FatDiskName: 1:", testFatDiskName1},
  {"FatDiskName: 2:", testFatDiskName2},
  {"FatDiskName: 3:", testFatDiskName3},
  {"FatDiskName: empty parameter", testFatDiskNameNameEmpty},
  CU_TEST_INFO_NULL
};
