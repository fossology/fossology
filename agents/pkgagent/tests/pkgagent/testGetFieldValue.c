#include <stdio.h>
#include "CUnit/CUnit.h"

extern char * GetFieldValue(char *Sin, char *Field, int FieldMax, char *Value, int ValueMax, char Separator);

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
    printf("test_GetFieldValue_normal Result is:%s, field is: %s, value is:%s\n", Result, Field, Value); 
    CU_ASSERT_TRUE(!strcmp(Result, predictValue));
}

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
    printf("test_GetFieldValue_sin_is_null Result is:%s, field is: %s, value is:%s\n", Result, Field, Value);
    CU_ASSERT_EQUAL(Result, predictValue);
}

CU_TestInfo testcases_GetFieldValue[] = {
        {"Testing GetFieldValue, paramters are  normal:", test_GetFieldValue_normal},
        {"Testing GetFieldValue,sin is null:", test_GetFieldValue_sin_is_null},
        CU_TEST_INFO_NULL
};

