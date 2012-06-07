/*********************************************************************
Copyright (C) 2011 Hewlett-Packard Development Company, L.P.

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

#include <stdio.h>
#include "CUnit/CUnit.h"
#include <pkgagent.h>

/**
 * \file testTrim.c
 * \brief unit test for Trim function
 */

/**
 * \brief test case for input parameter is normal
 */
void test_Trim_normal()
{
  char str[] = " test trim!   ";
  char *predictValue = "test trim!";
  char *Result = trim(str);
  //printf("test_Trim_normal Result is:%s\n", Result);
  CU_ASSERT_TRUE(!strcmp(Result, predictValue));
}

/**
 * \brief test case for input parameter is null
 */
void test_Trim_str_is_null()
{
  char *str = "";
  char *predictValue = "";
  char *Result = trim(str);
  CU_ASSERT_EQUAL(Result, predictValue);
}

/**
 * \brief test case for input parameter is all space
 */
void test_Trim_allspace()
{
  char *str = "       ";
  char *predictValue = "";
  char *Result = trim(str);
  CU_ASSERT_TRUE(!strcmp(Result, predictValue));
}

/**
 * \brief testcases for function Trim
 */
CU_TestInfo testcases_Trim[] = {
    {"Testing Trim, paramters are normal:", test_Trim_normal},
    {"Testing Trim, paramter is null:", test_Trim_str_is_null},
    {"Testing Trim, paramter is allspace:", test_Trim_allspace},
    CU_TEST_INFO_NULL
};

