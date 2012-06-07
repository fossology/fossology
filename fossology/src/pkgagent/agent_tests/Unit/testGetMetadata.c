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

extern char *DBConfFile;
/**
 * \file testGetMetadata.c
 * \brief unit test for GetMetadata function
 */

/**
 * \brief Test pkgagent.c Function GetMetadata() Normal parameter
 */
void test_GetMetadata_normal()
{
  char *pkg = "../testdata/fossology-1.2.0-1.el5.i386.rpm";
  struct rpmpkginfo *pi;
  //char *DBConfFile = NULL;  /* use default Db.conf */
  char *ErrorBuf;

  pi = (struct rpmpkginfo *)malloc(sizeof(struct rpmpkginfo));
  int predictValue = 0;
  rpmReadConfigFiles(NULL, NULL);
  db_conn = fo_dbconnect(DBConfFile, &ErrorBuf);
  int Result = GetMetadata(pkg, pi);
  //printf("test_GetMetadata Result is:%d\n", Result);
  //printf("test_GetMetadata Result is temp:%s\n", pi->pkgArch); 
  CU_ASSERT_STRING_EQUAL(pi->pkgName, "fossology");
  CU_ASSERT_STRING_EQUAL(pi->pkgArch, "i386");
  CU_ASSERT_STRING_EQUAL(pi->version, "1.2.0");
  CU_ASSERT_STRING_EQUAL(pi->license, "GPLv2");
  CU_ASSERT_STRING_EQUAL(pi->group, "Applications/Engineering");
  CU_ASSERT_STRING_EQUAL(pi->release, "1.el5");
  CU_ASSERT_STRING_EQUAL(pi->buildDate, "Mon Jul 12 03:30:32 2010");
  CU_ASSERT_STRING_EQUAL(pi->url, "http://www.fossology.org");
  CU_ASSERT_STRING_EQUAL(pi->sourceRPM, "fossology-1.2.0-1.el5.src.rpm");
  CU_ASSERT_EQUAL(pi->req_size, 44);
  PQfinish(db_conn);
#ifdef _RPM_4_4_COMPAT
  rpmFreeCrypto();
  /* free memroy */
  int i;
  for(i=0; i< pi->req_size;i++)
    free(pi->requires[i]);
#endif /* After RPM4.4 version*/
  rpmFreeMacros(NULL);
  free(pi->requires);
  free(pi);
  CU_ASSERT_EQUAL(Result, predictValue);
}

/**
 * \brief Test pkgagent.c Function GetMetadata() Wrong test file
 */
void test_GetMetadata_wrong_testfile()
{
  char *pkg = "../testdata/fossology_1.4.1.dsc";
  struct rpmpkginfo *pi;
  pi = (struct rpmpkginfo *)malloc(sizeof(struct rpmpkginfo));
  int predictValue = -1;
  //char *DBConfFile = NULL;  /* use default Db.conf */
  char *ErrorBuf;

  rpmReadConfigFiles(NULL, NULL);
  db_conn = fo_dbconnect(DBConfFile, &ErrorBuf);
  int Result = GetMetadata(pkg, pi);
  //printf("test_GetMetadata Result is:%d\n", Result);
  PQfinish(db_conn);
#ifdef _RPM_4_4_COMPAT
  rpmFreeCrypto();
#endif /* After RPM4.4 version*/
  rpmFreeMacros(NULL);
  memset(pi, 0, sizeof(struct rpmpkginfo));
  free(pi); 
  CU_ASSERT_EQUAL(Result, predictValue);
}

/**
 * \brief Test pkgagent.c Function GetMetadata() with No test file
 */
void test_GetMetadata_no_testfile()
{
  char *pkg = NULL;
  struct rpmpkginfo *pi;
  //char *DBConfFile = NULL;  /* use default Db.conf */
  char *ErrorBuf;

  pi = (struct rpmpkginfo *)malloc(sizeof(struct rpmpkginfo));
  int predictValue = -1;
  rpmReadConfigFiles(NULL, NULL);
  db_conn = fo_dbconnect(DBConfFile, &ErrorBuf);
  int Result = GetMetadata(pkg, pi);
  //printf("test_GetMetadata Result is:%d\n", Result);
  PQfinish(db_conn);
#ifdef _RPM_4_4_COMPAT
  rpmFreeCrypto();
  /* free memroy */
  int i;
  for(i=0; i< pi->req_size;i++)
    free(pi->requires[i]);
#endif /* After RPM4.4 version*/
  rpmFreeMacros(NULL);
  free(pi->requires);
  free(pi);
  CU_ASSERT_EQUAL(Result, predictValue);
}

/**
 * \brief testcases for function GetMetadata
 */
CU_TestInfo testcases_GetMetadata[] = {
    {"Testing the function GetMetadata, paramters are  normal", test_GetMetadata_normal},
    {"Testing the function GetMetadata, test file is not rpm file", test_GetMetadata_wrong_testfile},
    {"Testing the function GetMetadata, test file doesn't exist", test_GetMetadata_no_testfile},
    CU_TEST_INFO_NULL
};

