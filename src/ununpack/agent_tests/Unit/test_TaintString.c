/*
 SPDX-FileCopyrightText: © 2012 Hewlett-Packard Development Company, L.P.

 SPDX-License-Identifier: GPL-2.0-only
*/
#include "run_tests.h"
/**
 * \brief Unit test cases for TaintString()
 */
static int Result = 0;
static int DestLen = 4096;

/**
 * @brief function TaintString()
 * \test
 * -# Check the replace functionality of TaintString()
 */
void testTaintString1()
{
  char Dest[DestLen];
  char *Src = "test%sTaintstring";
  Result = TaintString(Dest, DestLen, Src, 0, "Replace");
  FO_ASSERT_EQUAL(Result, 0);
  FO_ASSERT_STRING_EQUAL(Dest, "testReplaceTaintstring");
}
/**
 * @brief function TaintString()
 * \test
 * -# Check if TaintString() escapes quotes and slashes
 */
void testTaintString2()
{
  char Dest[DestLen];
  char *Src = "test\'Taintstring";
  Result = TaintString(Dest, DestLen, Src, 1, NULL);
  FO_ASSERT_EQUAL(Result, 0);
  FO_ASSERT_STRING_EQUAL(Dest, "test'\\''Taintstring");
}
/**
 * @brief function TaintString()
 * \test
 * -# Check if TaintString() escapes escaped slashes
 */
void testTaintString3()
{
  char Dest[DestLen];
  char *Src = "test\\Taintstring";
  Result = TaintString(Dest, DestLen, Src, 0, NULL);
  FO_ASSERT_EQUAL(Result, 0);
  FO_ASSERT_STRING_EQUAL(Dest, "test\\\\Taintstring");
}
/**
 * @brief function TaintString()
 * \test
 * -# Check if TaintString() preserves quotes and remove slashes
 * using ProtectQuotes parameter
 */
void testTaintString4()
{
  char Dest[DestLen];
  char *Src = "test\'Taintstring";
  Result = TaintString(Dest, DestLen, Src, 0, NULL);
  FO_ASSERT_EQUAL(Result, 0);
  FO_ASSERT_STRING_EQUAL(Dest, "test'Taintstring");
}

/* ************************************************************************** */
/* **** cunit test cases **************************************************** */
/* ************************************************************************** */

CU_TestInfo TaintString_testcases[] =
{
  {"TaintString1:", testTaintString1},
  {"TaintString2:", testTaintString2},
  {"TaintString3:", testTaintString3},
  {"TaintString4:", testTaintString4},
  CU_TEST_INFO_NULL
};
