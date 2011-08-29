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
  CU_ASSERT_EQUAL(Result, 0); 

  int rc = 0;
  char commands[250];
  sprintf(commands, "isoinfo -f -R -i '%s' | grep ';1' > /dev/null ", Filename);
  rc = system(commands);
  if (0 != rc)
  {
    existed = file_dir_existed("./test-result/imagefile.iso.dir/test.cpi");
    CU_ASSERT_EQUAL(existed, 1); 
  }
  else
  {
    existed = file_dir_existed("./test-result/imagefile.iso.dir/TEST.CPI;1");
    CU_ASSERT_EQUAL(existed, 1); // existing
  }
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
  existed = file_dir_existed("./test-result/523.iso.dir/523sfp/DOS4GW.EXE");
  CU_ASSERT_EQUAL(Result, 0);
  CU_ASSERT_EQUAL(existed, 1); 
  existed = file_dir_existed("./test-result/523.iso.dir/523sfp/p3p10131.bin");
  CU_ASSERT_EQUAL(existed, 1); 

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

/* ************************************************************************** */
/* **** cunit test cases **************************************************** */
/* ************************************************************************** */

CU_TestInfo ununpack_iso_testcases[] =
{
  {"testExtractAR: iso file 1:", testExtractISO1},
  {"testExtractISO: iso file 2:", testExtractISO2},
  {"testExtractAR: abnormal parameters:", testExtractISO4EmptyParameters},
  CU_TEST_INFO_NULL
};
