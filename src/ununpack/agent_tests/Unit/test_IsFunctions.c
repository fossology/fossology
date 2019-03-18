/*********************************************************************
Copyright (C) 2012 Hewlett-Packard Development Company, L.P.

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

static int Result = 0;
/**
 * \file
 * \brief Unit test cases for Isxxx functions
 */
/**
 * \brief function IsDebianSourceFile(char *Filename)
 * \test
 * -# Call IsDebianSourceFile() on dsc file
 * -# Check if function returns YES
 */
void testIsDebianSourceFile()
{
  char *Filename = "../testdata/test_1-1.dsc";
  Result = IsDebianSourceFile(Filename);
  FO_ASSERT_EQUAL(Result, 1);
}

/**
 * \brief function IsDebianSourceFile(char *Filename)
 * \test
 * -# Call IsDebianSourceFile() on non dsc file
 * -# Check if function returns NO
 */
void testIsNotDebianSourceFile()
{
  char *Filename = "../testdata/test_1.orig.tar.gz";
  Result = IsDebianSourceFile(Filename);
  FO_ASSERT_EQUAL(Result, 0);
}

/**
 * \brief function IsExe(char *Filename)
 * \test
 * -# Call IsDebianSourceFile() on exe file
 * -# Check if function returns YES
 */
void testIsExeFile()
{
  char *Filename = "../testdata/test.exe";
  Result = IsExe(Filename, 1);
  FO_ASSERT_EQUAL(Result, 1);
}

/**
 * \brief function IsExe(char *Filename)
 * \test
 * -# Call IsDebianSourceFile() on non exe file
 * -# Check if function returns NO
 */
void testIsNotExeFile()
{
  char *Filename = "../testdata/test_1.orig.tar.gz";
  Result = IsExe(Filename, 1);
  FO_ASSERT_EQUAL(Result, 0);
}

/* ************************************************************************** */
/* **** cunit test cases **************************************************** */
/* ************************************************************************** */

CU_TestInfo IsFunctions_testcases[] =
{
  {"IsDebianSourceFile:", testIsDebianSourceFile},
  {"IsNotDebianSourceFile:", testIsNotDebianSourceFile},
/**  {"IsExeFile:", testIsExeFile}, */
  {"IsNotExeFile:", testIsNotExeFile},
  CU_TEST_INFO_NULL
};
