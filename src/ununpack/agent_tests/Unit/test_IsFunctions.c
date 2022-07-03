/*
 SPDX-FileCopyrightText: © 2012 Hewlett-Packard Development Company, L.P.

 SPDX-License-Identifier: GPL-2.0-only
*/
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
