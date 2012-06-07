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

/* cunit includes */
#include <CUnit/CUnit.h>
#include "wget_agent.h"
#include "utility.h"

/**
 * \file testSetEnv.c
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
  memset(GlobalTempFile, 0, MAXCMD);
  memset(GlobalURL, 0, MAXCMD);
  memset(GlobalParam, 0, MAXCMD);
  return 0;
}

/**
 * \brief clean the env 
 */
int  SetEnvClean()
{
  GlobalUploadKey = -1;
  memset(GlobalTempFile, 0, MAXCMD);
  memset(GlobalURL, 0, MAXCMD);
  memset(GlobalParam, 0, MAXCMD);
  return 0;
}
/**
 * \brief set the global variables
 */
void testSetEnvNormal()
{
  strcpy(Source, "38 - http://fossology.org/debian/mkpackages -l 1 -R index.html*");
  strcpy(TempFileDir, "./test_result");
  SetEnv(Source, TempFileDir);
  CU_ASSERT_EQUAL(GlobalUploadKey, 38);
  char *cptr = strstr(GlobalTempFile, "./test_result/wget."); /* is like ./test_result/wget.29923 */
  CU_ASSERT_PTR_NOT_NULL(cptr);
  CU_ASSERT_STRING_EQUAL(GlobalURL, "http://fossology.org/debian/mkpackages");
  CU_ASSERT_STRING_EQUAL(GlobalParam, "-l 1 -R index.html*");
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

