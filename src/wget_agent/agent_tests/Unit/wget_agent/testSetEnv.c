/*
 SPDX-FileCopyrightText: © 2011-2012 Hewlett-Packard Development Company, L.P.

 SPDX-License-Identifier: GPL-2.0-only
*/

/* cunit includes */
#include <CUnit/CUnit.h>
#include "wget_agent.h"
#include "utility.h"

/**
 * \file
 * \brief testing for the function SetEnv()
 */

static char Source[MAX_LENGTH];
static char TempFileDir[MAX_LENGTH];

/* test functions */

/**
 * \brief initialize
 */
int  SetEnvInit()
{
  GlobalUploadKey = -1;
  memset(GlobalTempFile, 0, STRMAX);
  memset(GlobalURL, 0, URLMAX);
  memset(GlobalParam, 0, STRMAX);
  return 0;
}

/**
 * \brief clean the env
 */
int  SetEnvClean()
{
  GlobalUploadKey = -1;
  memset(GlobalTempFile, 0, STRMAX);
  memset(GlobalURL, 0, URLMAX);
  memset(GlobalParam, 0, STRMAX);
  return 0;
}
/**
 * \brief set the global variables
 * \test
 * -# Set parameters for wget_agent
 * -# Call SetEnv()
 * -# Check if the parameters get set
 */
void testSetEnvNormal()
{
  strcpy(Source, "38 - https://mirrors.kernel.org/fossology/releases/3.0.0/ubuntu/ -l 1 -R *.deb");
  strcpy(TempFileDir, "./test_result");
  SetEnv(Source, TempFileDir);
  CU_ASSERT_EQUAL(GlobalUploadKey, 38);
  char *cptr = strstr(GlobalTempFile, "./test_result/wget."); /* is like ./test_result/wget.29923 */
  CU_ASSERT_PTR_NOT_NULL(cptr);
  CU_ASSERT_STRING_EQUAL(GlobalURL, "https://mirrors.kernel.org/fossology/releases/3.0.0/ubuntu/");
  CU_ASSERT_STRING_EQUAL(GlobalParam, "-l 1 -R *.deb");
}

/**
 * \brief testcases for function SetEnv
 */
CU_TestInfo testcases_SetEnv[] =
{
#if 0
#endif
{"SetEnv:Normal", testSetEnvNormal},
  CU_TEST_INFO_NULL
};

