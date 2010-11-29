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
#include "ununpack-iso.h"
#include "utility.h"

/* locals */
static int Result = 0;

/* test functions */

/**
 * @brief unpack iso file
 */
void testExtractISO1()
{
  deleteTmpFiles("./test-result/");
  existed = file_dir_existed("./test-result/");
  CU_ASSERT_EQUAL(existed, 0); // not existing
  Filename = "./test-data/testdata4unpack/imagefile.iso";
  MkDirs("./test-result/imagefile.iso.dir");
  Result = ExtractISO(Filename, "./test-result/imagefile.iso.dir");
  existed = file_dir_existed("./test-result/imagefile.iso.dir/test.cpio");
  CU_ASSERT_EQUAL(Result, 0); 
  CU_ASSERT_EQUAL(existed, 1); 
}

/**
 * @brief unpack iso file
 */
void testExtractISO2()
{
  deleteTmpFiles("./test-result/");
  existed = file_dir_existed("./test-result/");
  CU_ASSERT_EQUAL(existed, 0); // not existing
  Filename = "./test-data/testdata4unpack/523.iso";
  MkDirs("./test-result/523.iso.dir");
  Result = ExtractISO(Filename, "./test-result/523.iso.dir"); // 
  existed = file_dir_existed("./test-result/523.iso.dir/523SFP/DOS4GW.EXE");
  CU_ASSERT_EQUAL(Result, 0);
  CU_ASSERT_EQUAL(existed, 1); 
  existed = file_dir_existed("./test-result/523.iso.dir/523SFP/P3P10131.BIN");

}

/**
 * @brief abnormal parameters
 */
void testExtractISO4EmptyParameters()
{
  deleteTmpFiles("./test-result/");
  existed = file_dir_existed("./test-result/");
  CU_ASSERT_EQUAL(existed, 0); // not existing
  Result = ExtractISO("", ""); // empty parameters
  CU_ASSERT_EQUAL(Result, 1); // fail to Extract  
}

CU_TestInfo ExtractISO_testcases[] =
{
    {"Testing function testExtractAR for iso file 1:", testExtractISO1},
    {"Testing function testExtractISO  for iso file 2:", testExtractISO2},
    {"Testing function testExtractAR for abnormal parameters:", testExtractISO4EmptyParameters},
    CU_TEST_INFO_NULL
};
