/*
 SPDX-FileCopyrightText: © 2011-2012 Hewlett-Packard Development Company, L.P.

 SPDX-License-Identifier: GPL-2.0-only
*/

/* library includes */
#include <stdio.h>
/* cunit includes */
#include "CUnit/CUnit.h"
/* includes for files that will be tested */
#include <pkgagent.h>

/**
 * \file
 * \brief unit test for GetFieldValue function
 */

/**
 * \brief test case for input parameter is normal
 * \test
 * -# Create a string with \c field=value format
 * -# Call GetFieldValue() with a separator
 * -# Check if the value string is parsed properly
 */
void test_GetFieldValue_normal()
{
  char *Sin = "hello name=larry, very good";
  char Field[256];
  int FieldMax = 256;
  char Value[1024];
  int ValueMax = 1024;
  char Separator = '=';
  char *predictValue = "name=larry, very good";
  char *Result = GetFieldValue(Sin, Field, FieldMax, Value, ValueMax, Separator);
  //printf("test_GetFieldValue_normal Result is:%s, field is: %s, value is:%s\n", Result, Field, Value);
  CU_ASSERT_TRUE(!strcmp(Result, predictValue));
}

/**
 * \brief test case for input parameter is NULL
 * \test
 * -# Create an empty string
 * -# Pass to GetFieldValue()
 * -# Check if value string returned is NULL
 */
void test_GetFieldValue_sin_is_null()
{
  char *Sin = "";
  char Field[256];
  int FieldMax = 256;
  char Value[1024];
  int ValueMax = 1024;
  char Separator = '=';
  char *predictValue = NULL;
  char *Result = GetFieldValue(Sin, Field, FieldMax, Value, ValueMax, Separator);
  //printf("test_GetFieldValue_sin_is_null Result is:%s, field is: %s, value is:%s\n", Result, Field, Value);
  CU_ASSERT_EQUAL(Result, predictValue);
}

/**
 * \brief test case for don't set input separator
 * \test
 * -# Create a string with \c field=value format
 * -# Call GetFieldValue() with separator as NULL
 * -# Check if field and value are not separated
 */
void test_GetFieldValue_noseparator()
{
  char *Sin = "name=larry, very good";
  char Field[256];
  int FieldMax = 256;
  char Value[1024];
  int ValueMax = 1024;
  char *predictField = "name";
  char *predictValue = "=larry, very good";
  char Separator = '\0';
  char *Result = GetFieldValue(Sin, Field, FieldMax, Value, ValueMax, Separator);
  //printf("test_GetFieldValue_noseparator Result is:%s, field is: %s, value is:%s\n", Result, Field, Value);
  CU_ASSERT_TRUE(!strcmp(Result, predictValue));
  CU_ASSERT_TRUE(!strcmp(Field, predictField));
}

/**
 * \brief testcases for function GetFieldValue
 */
CU_TestInfo testcases_GetFieldValue[] = {
    {"Testing GetFieldValue, paramters are normal:", test_GetFieldValue_normal},
    {"Testing GetFieldValue, no separator:", test_GetFieldValue_noseparator},
    {"Testing GetFieldValue,sin is null:", test_GetFieldValue_sin_is_null},
    CU_TEST_INFO_NULL
};

