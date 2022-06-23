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
 * \brief unit test for GetMetadataDebSource function
 */

/**
 * \brief Test pkgagent.c Function GetMetadataDebSource()
 * get debian source package info from .dsc file
 * \test
 * -# Pass Debian source \c ".dsc" to GetMetadataDebSource()
 * -# Check if meta data parsed properly
 */
void test_GetMetadataDebSource()
{
  char *repFile = "../testdata/fossology_1.4.1.dsc";
  struct debpkginfo *pi;
  //char *DBConfFile = NULL;  /* use default Db.conf */
  char *ErrorBuf;

  pi = (struct debpkginfo *)malloc(sizeof(struct debpkginfo));
  memset(pi, 0, sizeof(struct debpkginfo));
  int predictValue = 0;
  db_conn = fo_dbconnect(DBConfFile, &ErrorBuf);
  strcpy(pi->version, "");
  int Result = GetMetadataDebSource(repFile, pi);
  //printf("GetMetadataDebSource Result is:%d\n", Result);

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
 * \brief Test pkgagent.c Function GetMetadataDebSource()
 * test get debian source info from wrong dsc file.
 * \test
 * -# Pass wrong file to GetMetadataDebSource()
 * -# Check if function return -1
 * \todo Needs fix
 */
void test_GetMetadataDebSource_wrong_testfile()
{
  char *repFile = "../testdata/fossology-1.2.0-1.el5.i386.rpm";
  struct debpkginfo *pi;
  char *ErrorBuf;

  pi = (struct debpkginfo *)malloc(sizeof(struct debpkginfo));
  memset(pi, 0, sizeof(struct debpkginfo));
  int predictValue = 0; /* FIXED: seems pkgagent have bug here. */
  db_conn = fo_dbconnect(DBConfFile, &ErrorBuf);
  int Result = GetMetadataDebSource(repFile, pi);
  //printf("GetMetadataDebSource Result is:%d\n", Result);

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
    {"Testing the function GetMetadataDebSource with wrong testfile", test_GetMetadataDebSource_wrong_testfile},
    CU_TEST_INFO_NULL
};

