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

/*
struct rpmpkginfo {
  char pkgName[256];
  char pkgAlias[256];
  char pkgArch[64];
  char version[64];
  char rpmFilename[256];
  char license[512];
  char group[128];
  char packager[1024];
  char release[64];
  char buildDate[128];
  char vendor[128];
  char url[256];
  char sourceRPM[256];
  char summary[MAXCMD];
  char description[MAXCMD];
  long pFileFk;
  char pFile[MAXCMD];
  char **requires;
  int req_size;
};*/

extern int GetMetadata(char *pkg, struct rpmpkginfo *pi);

void test_GetMetadata_normal()
{
  char *pkg = "./testdata/fossology-1.2.0-1.el5.i386.rpm";
  struct rpmpkginfo *pi;
  pi = (struct rpmpkginfo *)malloc(sizeof(struct rpmpkginfo));
  int predictValue = 0;
  rpmReadConfigFiles(NULL, NULL);
  db_conn = fo_dbconnect();
  int Result = GetMetadata(pkg, pi);
  printf("GetMetadata Result is:%d\n", Result);
  PQfinish(db_conn);
  rpmFreeMacros(NULL);
  CU_ASSERT_EQUAL(Result, predictValue);
}

CU_TestInfo testcases_GetMetadata[] = {
    {"Testing the function GetMetadata, paramters are  normal", test_GetMetadata_normal},
    CU_TEST_INFO_NULL
};

