/*********************************************************************
Copyright (C) 2011-2012 Hewlett-Packard Development Company, L.P.

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

/* library includes */
#include <stdio.h>
/* cunit includes */
#include "CUnit/CUnit.h"
/* includes for files that will be tested */
#include <pkgagent.h>

/**
 * \file testGetFieldValue.c
 * \brief unit test for GetFieldValue function
 */

/**
 * \brief test case for input parameter is normal
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
 *  * \brief test case for don't set input separator
 *   */
void test_GetFieldValue_noseparator()
{
  char *Sin = "name=larry, very good";
  char Field[256];
  int FieldMax = 256;
  char Value[1024];
  int ValueMax = 1024;
  char *predictField = "name";
  char *predictValue = "=larry, very good";
  char Separator = NULL;
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

