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
  deleteTmpFiles("../test-result/");
  existed = file_dir_existed("../test-result/");
  CU_ASSERT_EQUAL(existed, 0); // not existing
  Filename = "../test-data/testdata4unpack/ext2test-image";
  MkDirs("../test-result/ext2test-image.dir");
  Result = ExtractDisk(Filename, "ext", "../test-result/ext2test-image.dir");
  existed = file_dir_existed("../test-result/ext2test-image.dir/ununpack.c");
  CU_ASSERT_EQUAL(Result, 0); 
  CU_ASSERT_EQUAL(existed, 1); 
}

/**
 * @brief unpack disk image, ext3
 */
void testExtractDisk4Ext3()
{
  deleteTmpFiles("../test-result/");
  existed = file_dir_existed("../test-result/");
  CU_ASSERT_EQUAL(existed, 0); // not existing
  Filename = "../test-data/testdata4unpack/ext3test-image";
  MkDirs("../test-result/ext3test-image.dir");
  Result = ExtractDisk(Filename, "ext", "../test-result/ext3test-image.dir");
  existed = file_dir_existed("../test-result/ext3test-image.dir/libfossagent.a");
  CU_ASSERT_EQUAL(Result, 0);
  CU_ASSERT_EQUAL(existed, 1);
}

/**
 * @brief unpack disk image, ext2, FStype is unknowed
 */
void testExtractDisk4Ext2FstypeUnknow()
{
  deleteTmpFiles("../test-result/");
  existed = file_dir_existed("../test-result/");
  CU_ASSERT_EQUAL(existed, 0); // not existing
  Filename = "../test-data/testdata4unpack/ext2test-image";
  MkDirs("../test-result/ext2test-image.dir");
  Result = ExtractDisk(Filename, "", "../test-result/ext2test-image.dir");
  existed = file_dir_existed("../test-result/ext2test-image.dir/ununpack.c");
  CU_ASSERT_EQUAL(Result, 1);
  CU_ASSERT_EQUAL(existed, 0);
}

/**
 * @brief unpack disk image, fat 
 */
void testExtractDisk4Fat()
{
}

/**
 * @brief unpack disk image, ntfs
 */
void testExtractDisk4Ntfs()
{
}

CU_TestInfo ExtractDisk_testcases[] =
{
    {"Testing function ExtractDisk for ext2 image:", testExtractDisk4Ext2},
    {"Testing function ExtractDisk for ext3 image:", testExtractDisk4Ext3},
    {"Testing function ExtractDisk for ext2 image, fs type is unknowed:", testExtractDisk4Ext2FstypeUnknow},
//    {"Testing function ExtractDisk for fat image:", testExtractDisk4Fat},
//    {"Testing function ExtractDisk for nfts image:", testExtractDisk4Ntfs},
    CU_TEST_INFO_NULL
};
