#include <stdio.h>
#include "CUnit/CUnit.h"
#define MAXCMD 8192

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
};

extern int GetMetadata(char *pkg, struct rpmpkginfo *pi);

void test_GetMetadata_normal()
{
    char *pkg = "./fossology-1.2.0-1.el5.i386.rpm";
    struct rpmpkginfo *pi;
    int predictValue = 1;
    int Result = GetMetadata(pkg, pi);
    printf("GetMetadata Result is:%d\n", Result);
    CU_ASSERT_EQUAL(Result, predictValue);
}

CU_TestInfo testcases_GetMetadata[] = {
	{"Testing the function GetMetadata, paramters are  normal", test_GetMetadata_normal}, 
        CU_TEST_INFO_NULL
};

