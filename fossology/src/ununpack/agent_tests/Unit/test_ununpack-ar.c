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


 /** @brief unpack archive library file
 **/
void testExtractAR4ArchiveLibraryFile()
{
  deleteTmpFiles("./test-result/");
  exists = file_dir_exists("./test-result/");
  FO_ASSERT_EQUAL(exists, 0); // not existing
  MkDirs("./test-result/libfossagent.a.dir/");
  Filename = "../test-data/testdata4unpack/libfossagent.a";
  Result = ExtractAR(Filename, "./test-result/libfossagent.a.dir");
  exists = file_dir_exists("./test-result/libfossagent.a.dir/libfossagent.o");
  FO_ASSERT_EQUAL(exists, 1); // existing
  FO_ASSERT_EQUAL(Result, 0); // Extract archieve library successfully
}

/**
 * @brief unpack deb file
 */
void testExtractAR4DebFile()
{
  deleteTmpFiles("./test-result/");
  exists = file_dir_exists("./test-result/");
  FO_ASSERT_EQUAL(exists, 0); // not existing
  MkDirs("./test-result/bind9-host_1%3a9.7.0.dfsg.P1-1_i386.deb.dir/");
  Filename = "../test-data/testdata4unpack/bind9-host_1%3a9.7.0.dfsg.P1-1_i386.deb";
  Result = ExtractAR(Filename, "./test-result/bind9-host_1%3a9.7.0.dfsg.P1-1_i386.deb.dir");
  exists = file_dir_exists("./test-result/bind9-host_1%3a9.7.0.dfsg.P1-1_i386.deb.dir/data.tar.gz");
  FO_ASSERT_EQUAL(exists, 1); // existing
  FO_ASSERT_EQUAL(Result, 0); // Extract archieve library successfully
}

/**
 * @brief abnormal parameters
 */
void testExtractAR4EmptyParameters()
{
  deleteTmpFiles("./test-result/");
  exists = file_dir_exists("./test-result/");
  FO_ASSERT_EQUAL(exists, 0); // not existing
  Result = ExtractAR("", ""); // empty parameters
  FO_ASSERT_EQUAL(Result, 1); // fail to Extract archieve library 
}

/**
 * @brief abnormal parameters
 */
void testExtractAR4ErrorParameters()
{
  deleteTmpFiles("./test-result/");
  exists = file_dir_exists("./test-result/");
  FO_ASSERT_EQUAL(exists, 0); // not existing
  MkDirs("./test-result/fossology-1.2.0-1.el5.i386.rpm.dir/");
  Filename = "../test-data/testdata4unpack/fossology-1.2.0-1.el5.i386.rpm";
  Result = ExtractAR(Filename, "./test-result/fossology-1.2.0-1.el5.i386.rpm.dir");
  FO_ASSERT_EQUAL(Result, 1); // fail to Extract archieve library
}

/* ************************************************************************** */
/* **** cunit test cases **************************************************** */
/* ************************************************************************** */

CU_TestInfo ExtractAR_testcases[] =
{
  {"Testing function testExtractAR for archive library file:", testExtractAR4ArchiveLibraryFile},
  {"Testing function testExtractAR for deb file:", testExtractAR4DebFile},
  {"Testing function testExtractAR for abnormal parameters:", testExtractAR4EmptyParameters},
  {"Testing function testExtractAR for error parameters:", testExtractAR4ErrorParameters},
  CU_TEST_INFO_NULL
};
