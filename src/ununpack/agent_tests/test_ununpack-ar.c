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
#include "ununpack-ar.h"
#include "utility.h"

/* locals */
static int Result = 0;

/* test functions */

/**
 * @brief unpack archive library file
 */
void testExtractAR4ArchiveLibraryFile()
{
  deleteTmpFiles("./test-result/");
  existed = file_dir_existed("./test-result/");
  CU_ASSERT_EQUAL(existed, 0); // not existing
  MkDirs("./test-result/libfossagent.a.dir/");
  Filename = "./test-data/testdata4unpack/libfossagent.a";
  Result = ExtractAR(Filename, "./test-result/libfossagent.a.dir");
  existed = file_dir_existed("./test-result/libfossagent.a.dir/libfossagent.o");
  CU_ASSERT_EQUAL(existed, 1); // existing
  CU_ASSERT_EQUAL(Result, 0); // Extract archieve library successfully
}

/**
 * @brief unpack deb file
 */
void testExtractAR4DebFile()
{
  deleteTmpFiles("./test-result/");
  existed = file_dir_existed("./test-result/");
  CU_ASSERT_EQUAL(existed, 0); // not existing
  MkDirs("./test-result/bind9-host_1%3a9.7.0.dfsg.P1-1_i386.deb.dir/");
  Filename = "./test-data/testdata4unpack/bind9-host_1%3a9.7.0.dfsg.P1-1_i386.deb";
  Result = ExtractAR(Filename, "./test-result/bind9-host_1%3a9.7.0.dfsg.P1-1_i386.deb.dir");
  existed = file_dir_existed("./test-result/bind9-host_1%3a9.7.0.dfsg.P1-1_i386.deb.dir/data.tar.gz");
  CU_ASSERT_EQUAL(existed, 1); // existing
  CU_ASSERT_EQUAL(Result, 0); // Extract archieve library successfully
}

/**
 * @brief abnormal parameters
 */
void testExtractAR4EmptyParameters()
{
  deleteTmpFiles("./test-result/");
  existed = file_dir_existed("./test-result/");
  CU_ASSERT_EQUAL(existed, 0); // not existing
  Result = ExtractAR("", ""); // empty parameters
  CU_ASSERT_EQUAL(Result, 1); // fail to Extract archieve library 
}

CU_TestInfo ExtractAR_testcases[] =
{
    {"Testing function testExtractAR for archive library file:", testExtractAR4ArchiveLibraryFile},
    {"Testing function testExtractAR for deb file:", testExtractAR4DebFile},
    {"Testing function testExtractAR for abnormal parameters:", testExtractAR4EmptyParameters},
    CU_TEST_INFO_NULL
};
