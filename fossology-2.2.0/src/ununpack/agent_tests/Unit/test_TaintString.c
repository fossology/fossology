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
static int DestLen = 4096;

/**
 * @brief function TaintString
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
 * @brief function TaintString
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
 * @brief function TaintString
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
 *  * @brief function TaintString
 *   */
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
