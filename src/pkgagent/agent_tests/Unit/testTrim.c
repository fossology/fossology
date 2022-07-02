/*
 SPDX-FileCopyrightText: © 2011 Hewlett-Packard Development Company, L.P.

 SPDX-License-Identifier: GPL-2.0-only
*/

#include <stdio.h>
#include "CUnit/CUnit.h"
#include <pkgagent.h>

/**
 * \file
 * \brief Unit test for Trim function
 */

/**
 * \brief Test case for input parameter is normal
 * \test
 * -# Create a string with extra spaces both ends
 * -# Call trim()
 * -# Check if the extra space were removed
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
 * \brief Test case for input parameter is null
 * \test
 * -# Create an empty string
 * -# Call trim()
 * -# Check if entry string is returned
 */
void test_Trim_str_is_null()
{
  char *str = "";
  char *predictValue = "";
  char *Result = trim(str);
  CU_ASSERT_EQUAL(Result, predictValue);
}

/**
 * \brief Test case for input parameter is all space
 * \test
 * -# Create a string with only spaces
 * -# Call trim()
 * -# Check if empty string is returned
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

