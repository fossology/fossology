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

/* locals */
static int Result = 0;

/**
 * @brief unpack iso file
 */
void testExtractISO1()
{
  deleteTmpFiles("./test-result/");
  exists = file_dir_exists("./test-result/");
  FO_ASSERT_EQUAL(exists, 0); // not existing
  Filename = "../test-data/testdata4unpack/imagefile.iso";
  MkDirs("./test-result/imagefile.iso.dir");
  Result = ExtractISO(Filename, "./test-result/imagefile.iso.dir");
  FO_ASSERT_EQUAL(Result, 0); 

  int rc = 0;
  char commands[250];
  sprintf(commands, "isoinfo -f -R -i '%s' | grep ';1' > /dev/null ", Filename);
  rc = system(commands);
  if (0 != rc)
  {
    exists = file_dir_exists("./test-result/imagefile.iso.dir/test.cpi");
    FO_ASSERT_EQUAL(exists, 1); 
  }
  else
  {
    exists = file_dir_exists("./test-result/imagefile.iso.dir/TEST.CPI;1");
    FO_ASSERT_EQUAL(exists, 1); // existing
  }
}

/**
 * @brief unpack iso file
 */
void testExtractISO2()
{
  deleteTmpFiles("./test-result/");
  exists = file_dir_exists("./test-result/");
  FO_ASSERT_EQUAL(exists, 0); // not existing
  Filename = "../test-data/testdata4unpack/523.iso";
  MkDirs("./test-result/523.iso.dir");
  Result = ExtractISO(Filename, "./test-result/523.iso.dir"); // 
  exists = file_dir_exists("./test-result/523.iso.dir/523sfp/DOS4GW.EXE");
  FO_ASSERT_EQUAL(Result, 0);
  FO_ASSERT_EQUAL(exists, 1); 
  exists = file_dir_exists("./test-result/523.iso.dir/523sfp/p3p10131.bin");
  FO_ASSERT_EQUAL(exists, 1); 

}

/**
 * @brief abnormal parameters
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
 */
void testExtractISO4ErrorParameters()
{
  deleteTmpFiles("./test-result/");
  exists = file_dir_exists("./test-result/");
  FO_ASSERT_EQUAL(exists, 0); // not existing
  Filename = "../test-data/testdata4unpack/fcitx_3.6.2.orig.tar.gz";
  MkDirs("./test-result/fcitx_3.6.2.orig.tar.gz.dir");
  Result = ExtractISO(Filename, "./test-result/fcitx_3.6.2.orig.tar.gz.dir");
  FO_ASSERT_EQUAL(Result, 0); // fail to Extract
}

/* ************************************************************************** */
/* **** cunit test cases **************************************************** */
/* ************************************************************************** */

CU_TestInfo ununpack_iso_testcases[] =
{
  {"testExtractISO: iso file 1:", testExtractISO1},
  {"testExtractISO: iso file 2:", testExtractISO2},
  {"testExtractISO: abnormal parameters:", testExtractISO4EmptyParameters},
  {"testExtractISO: error parameters:", testExtractISO4ErrorParameters},
  CU_TEST_INFO_NULL
};
