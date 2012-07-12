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
static ContainerInfo *CI = NULL;
/**
 * @brief test function DebugContainerInfo(ContainerInfo *CI)
 */
void testDebugContainerInfo()
{
  struct stat Stat = {0};
  ParentInfo PI = {0, 1287725739, 1287725739, 0, 0};
  ContainerInfo CITest = {"../test-data/testdata4unpack/threezip.zip", "./test-result/", "threezip.zip", "threezip.zip.dir", 1, 1, 0, 0, Stat, PI, 0, 0, 0, 9, 0, 0};
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
