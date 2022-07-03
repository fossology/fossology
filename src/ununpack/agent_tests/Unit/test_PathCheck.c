/*
 SPDX-FileCopyrightText: © 2012 Hewlett-Packard Development Company, L.P.

 SPDX-License-Identifier: GPL-2.0-only
*/
#include "run_tests.h"
/**
 * \file
 * \brief Unit test cases for PathCheck() and Usage()
 */
/**
 * \brief function PathCheck
 * \test
 * -# Create a string with `%H`, `%R` and `%U`
 * -# Call PathCheck() and check if place holders are replaced
 */
void testPathCheck()
{
  char *DirPath = "%H%R/!%U";
  char *NewPath = NULL;
  char HostName[1024];
  char TmpPath[1024];
  char *subs;

  NewPath = PathCheck(DirPath);
  subs = strstr(NewPath, "!");
  gethostname(HostName, sizeof(HostName));

  snprintf(TmpPath, sizeof(TmpPath), "%s%s%s%s", HostName,fo_config_get(sysconfig, "FOSSOLOGY", "path", NULL),"/", subs);
  FO_ASSERT_STRING_EQUAL(NewPath, TmpPath);
}

/**
 * \brief function Usage
 * \test
 * -# Call Usage()
 * -# Check the results
 * \todo Need added output check of Usage, how to do it?
 */
void testUsage()
{
  Usage("ununpack", "2.0");
}

/* ************************************************************************** */
/* **** cunit test cases **************************************************** */
/* ************************************************************************** */

CU_TestInfo PathCheck_testcases[] =
{
  {"PathCheck:", testPathCheck},
  {"Usage:", testUsage},
  CU_TEST_INFO_NULL
};
