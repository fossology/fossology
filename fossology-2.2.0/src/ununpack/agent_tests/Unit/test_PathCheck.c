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

/**
 * @brief function PathCheck
 */
void testPathCheck()
{
  char *DirPath = "%H%R/testdata4unpack!%U";
  char *NewPath = NULL;
  char HostName[1024];
  char TmpPath[1024];
  char *subs;

  NewPath = PathCheck(DirPath);
  subs = strstr(NewPath, "!");
  gethostname(HostName, sizeof(HostName));

  snprintf(TmpPath, sizeof(TmpPath), "%s%s%s%s", HostName,fo_config_get(sysconfig, "FOSSOLOGY", "path", NULL),"/testdata4unpack", subs);
  FO_ASSERT_STRING_EQUAL(NewPath, TmpPath);
}

/**
 * @brief function Usage
 */
void testUsage()
{
  Usage("ununpack", "2.0");
  //Need added output check of Usage, how to do it?
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
