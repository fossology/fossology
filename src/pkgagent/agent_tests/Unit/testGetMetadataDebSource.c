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
#include "pkgagent.h"

#include <stdio.h>
#include "CUnit/CUnit.h"

/**
 * \file testGetMetadataDebSource.c
 * \brief unit test for GetMetadataDebSource function
 */

/**
 * \brief Test pkgagent.c Function GetMetadataDebSource()
 * get debian source package info from .dsc file
 */
void test_GetMetadataDebSource()
{
  char *repFile = "../testdata/fossology_1.4.1.dsc";
  struct debpkginfo *pi;
  pi = (struct debpkginfo *)malloc(sizeof(struct debpkginfo));
  int predictValue = 0;
  db_conn = fo_dbconnect();
  strcpy(pi->version, "");
  int Result = GetMetadataDebSource(repFile, pi);
  printf("GetMetadataDebSource Result is:%d\n", Result);

  //printf("GetMetadataDebSource Result is:%s\n", pi->version);
  CU_ASSERT_STRING_EQUAL(pi->pkgName, "fossology");
  CU_ASSERT_STRING_EQUAL(pi->pkgArch, "any");
  CU_ASSERT_STRING_EQUAL(pi->version, "1.4.1");
  CU_ASSERT_STRING_EQUAL(pi->maintainer, "Matt Taggart <taggart@debian.org>");
  CU_ASSERT_STRING_EQUAL(pi->homepage, "http://fossology.org");
  CU_ASSERT_EQUAL(pi->dep_size, 13);

  PQfinish(db_conn);
  int i;
  for(i=0; i< pi->dep_size;i++)
    free(pi->depends[i]);
  free(pi->depends);
  memset(pi, 0, sizeof(struct debpkginfo));
  free(pi);
  CU_ASSERT_EQUAL(Result, predictValue);
}

/**
 * \brief testcases for function GetMetadataDebSource
 */
CU_TestInfo testcases_GetMetadataDebSource[] = {
    {"Testing the function GetMetadataDebSource", test_GetMetadataDebSource},
    CU_TEST_INFO_NULL
};

