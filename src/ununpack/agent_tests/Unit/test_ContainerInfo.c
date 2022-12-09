/*
 SPDX-FileCopyrightText: © 2012 Hewlett-Packard Development Company, L.P.

 SPDX-License-Identifier: GPL-2.0-only
*/
/**
 * \file
 * \brief Unit test cases for ContainerInfo
 */
#include "run_tests.h"

static int Result = 0;
static ContainerInfo *CI = NULL;
/**
 * \brief test function DebugContainerInfo()
 * \test
 * -# Create a test ContainerInfo object
 * -# Pass it to DebugContainerInfo()
 */
void testDebugContainerInfo()
{
  struct stat Stat = {0};
  ParentInfo PI = {0, 1287725739, 1287725739, 0, 0};
  ContainerInfo CITest = {
      "../testdata/test.zip", "./test-result/", "test.zip",
      "test.zip.dir", 1, 1, 0, 0, Stat, PI, 0, 0, 0, 9, 0, 0};
  CI = &CITest;
  DebugContainerInfo(CI);
  FO_ASSERT_EQUAL(Result, 0);
}

/* ************************************************************************** */
/* **** cunit test cases **************************************************** */
/* ************************************************************************** */

CU_TestInfo ContainerInfo_testcases[] =
{
  {"DebugContainerInfo:", testDebugContainerInfo},
  //{"IsNotDebianSourceFile:", testIsNotDebianSourceFile},
  CU_TEST_INFO_NULL
};
