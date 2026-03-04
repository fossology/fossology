/*
 SPDX-FileCopyrightText: © 2011 Hewlett-Packard Development Company, L.P.

 SPDX-License-Identifier: GPL-2.0-only
*/
#include "pkgagent.h"

#include <stdio.h>
#include "CUnit/CUnit.h"

extern char *DBConfFile;
/**
 * \file
 * \brief unit test for GetMetadata function
 */

/**
 * \brief Test pkgagent.c Function GetMetadata() Normal parameter
 * \test
 * -# Load a known RPM package using GetMetadata()
 * -# Check if the meta data are parsed properly
 */
void test_GetMetadata_normal()
{
  char *pkg = "./testdata/fossology-1.2.0-1.el5.i386.rpm";
  struct rpmpkginfo *pi;
  //char *DBConfFile = NULL;  /* use default Db.conf */
  char *ErrorBuf;

  pi = (struct rpmpkginfo *)malloc(sizeof(struct rpmpkginfo));
  memset(pi, 0, sizeof(struct rpmpkginfo));
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
  rpmFreeCrypto();
  /* free memroy */
  int i;
  for(i=0; i< pi->req_size;i++)
    free(pi->requires[i]);
  rpmFreeMacros(NULL);
  free(pi->requires);
  free(pi);
  CU_ASSERT_EQUAL(Result, predictValue);
}

/**
 * \brief Test pkgagent.c Function GetMetadata() Wrong test file
 * \test
 * -# Load a Debian package using GetMetadata()
 * -# The function should return -1
 */
void test_GetMetadata_wrong_testfile()
{
  char *pkg = "./testdata/fossology_1.4.1.dsc";
  struct rpmpkginfo *pi;
  pi = (struct rpmpkginfo *)malloc(sizeof(struct rpmpkginfo));
  memset(pi, 0, sizeof(struct rpmpkginfo));
  int predictValue = -1;
  //char *DBConfFile = NULL;  /* use default Db.conf */
  char *ErrorBuf;

  rpmReadConfigFiles(NULL, NULL);
  db_conn = fo_dbconnect(DBConfFile, &ErrorBuf);
  int Result = GetMetadata(pkg, pi);
  //printf("test_GetMetadata Result is:%d\n", Result);
  PQfinish(db_conn);
  rpmFreeCrypto();
  rpmFreeMacros(NULL);
  memset(pi, 0, sizeof(struct rpmpkginfo));
  free(pi);
  CU_ASSERT_EQUAL(Result, predictValue);
}

/**
 * \brief Test pkgagent.c Function GetMetadata() with No test file
 * \test
 * -# Pass NULL to GetMetadata() for pkg
 * -# Function should return -1
 */
void test_GetMetadata_no_testfile()
{
  char *pkg = NULL;
  struct rpmpkginfo *pi;
  //char *DBConfFile = NULL;  /* use default Db.conf */
  char *ErrorBuf;

  pi = (struct rpmpkginfo *)malloc(sizeof(struct rpmpkginfo));
  memset(pi, 0, sizeof(struct rpmpkginfo));
  int predictValue = -1;
  rpmReadConfigFiles(NULL, NULL);
  db_conn = fo_dbconnect(DBConfFile, &ErrorBuf);
  int Result = GetMetadata(pkg, pi);
  //printf("test_GetMetadata Result is:%d\n", Result);
  PQfinish(db_conn);
  rpmFreeCrypto();
  /* free memory */
  int i;
  for(i=0; i< pi->req_size;i++)
    free(pi->requires[i]);
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

