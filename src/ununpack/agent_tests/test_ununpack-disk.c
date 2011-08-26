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
#include "ununpack-disk.h"
#include "utility.h"

/* locals */
static int Result = 0;

/* test functions */

/**
 * @brief unpack disk image, ext2
 */
void testExtractDisk4Ext2()
{
  deleteTmpFiles("./test-result/");
  existed = file_dir_existed("./test-result/");
  CU_ASSERT_EQUAL(existed, 0); // not existing
  Filename = "./test-data/testdata4unpack/ext2test-image";
  MkDirs("./test-result/ext2test-image.dir");
  Result = ExtractDisk(Filename, "ext", "./test-result/ext2test-image.dir");
  existed = file_dir_existed("./test-result/ext2test-image.dir/ununpack.c");
  CU_ASSERT_EQUAL(Result, 0); 
  CU_ASSERT_EQUAL(existed, 1); 
}

/**
 * @brief unpack disk image, ext3
 */
void testExtractDisk4Ext3()
{
  deleteTmpFiles("./test-result/");
  existed = file_dir_existed("./test-result/");
  CU_ASSERT_EQUAL(existed, 0); // not existing
  Filename = "./test-data/testdata4unpack/ext3test-image";
  MkDirs("./test-result/ext3test-image.dir");
  Result = ExtractDisk(Filename, "ext", "./test-result/ext3test-image.dir");
  existed = file_dir_existed("./test-result/ext3test-image.dir/libfossagent.a");
  CU_ASSERT_EQUAL(Result, 0);
  CU_ASSERT_EQUAL(existed, 1);
}

/**
 * @brief unpack disk image, ext2, FStype is unknowed
 */
void testExtractDisk4Ext2FstypeUnknow()
{
  deleteTmpFiles("./test-result/");
  existed = file_dir_existed("./test-result/");
  CU_ASSERT_EQUAL(existed, 0); // not existing
  Filename = "./test-data/testdata4unpack/ext2test-image";
  MkDirs("./test-result/ext2test-image.dir");
  Result = ExtractDisk(Filename, "", "./test-result/ext2test-image.dir");
  existed = file_dir_existed("./test-result/ext2test-image.dir/ununpack.c");
  CU_ASSERT_EQUAL(Result, 1);
  CU_ASSERT_EQUAL(existed, 0);
}

/**
 * @brief unpack disk image, fat 
 */
void testExtractDisk4Fat()
{
  deleteTmpFiles("./test-result/");
  existed = file_dir_existed("./test-result/");
  CU_ASSERT_EQUAL(existed, 0); // not existing
  Filename = "./test-data/testdata4unpack/fattest-image";
  MkDirs("./test-result/fattest-image.dir");
  Result = ExtractDisk(Filename, "fat", "./test-result/fattest-image.dir");
  existed = file_dir_existed("./test-result/fattest-image.dir/ununpack.c");
  CU_ASSERT_EQUAL(Result, 0);
  CU_ASSERT_EQUAL(existed, 1);
}

/**
 * @brief unpack disk image, ntfs
 */
void testExtractDisk4Ntfs()
{
  deleteTmpFiles("./test-result/");
  existed = file_dir_existed("./test-result/");
  CU_ASSERT_EQUAL(existed, 0); // not existing
  Filename = "./test-data/testdata4unpack/ntfstest-image";
  MkDirs("./test-result/ntfstest-image.dir");
  Result = ExtractDisk(Filename, "ntfs", "./test-result/ntfstest-image.dir");
  existed = file_dir_existed("./test-result/ntfstest-image.dir/ununpack.c");
  CU_ASSERT_EQUAL(Result, 0);
  CU_ASSERT_EQUAL(existed, 1);
}

CU_TestInfo ExtractDisk_testcases[] =
{
    {"Testing function ExtractDisk for ext2 image:", testExtractDisk4Ext2},
    {"Testing function ExtractDisk for ext3 image:", testExtractDisk4Ext3},
    {"Testing function ExtractDisk for ext2 image, fs type is unknowed:", testExtractDisk4Ext2FstypeUnknow},
    {"Testing function ExtractDisk for fat image:", testExtractDisk4Fat},
//    {"Testing function ExtractDisk for nfts image:", testExtractDisk4Ntfs},
    CU_TEST_INFO_NULL
};


/* locals */
static char *Name = NULL;

/* test functions */
void FatDiskName(char *name);

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
  CU_ASSERT_EQUAL(strcmp(Name, "fossology"), 0); 
}
 
void testFatDiskName2()
{
  strcpy(Name, "Fosso\0");
  FatDiskName(Name);
  CU_ASSERT_EQUAL(strcmp(Name, "fosso"), 0);

  strcpy(Name, "FOSSOLOGY HELLO\0");
  FatDiskName(Name);
  CU_ASSERT_EQUAL(strcmp(Name, "fossology hello"), 0);
}

void testFatDiskName3()
{
  strcpy(Name, "Fosso (hello)");
  FatDiskName(Name);
  CU_ASSERT_EQUAL(strcmp(Name, "fosso"), 0);
}

void testFatDiskNameNameEmpty()
{
  strcpy(Name, "");
  FatDiskName(Name);
  CU_ASSERT_EQUAL(strcmp(Name, ""), 0);
}

CU_TestInfo FatDiskName_testcases[] =
{
    {"Testing function FatDiskName, 1:", testFatDiskName1},
    {"Testing function FatDiskName, 2:", testFatDiskName2},
    {"Testing function FatDiskName, 3:", testFatDiskName3},
    {"Testing function FatDiskName, the parameter is empty:", testFatDiskNameNameEmpty},
    CU_TEST_INFO_NULL
};
