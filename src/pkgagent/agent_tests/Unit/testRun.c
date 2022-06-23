/*
 SPDX-FileCopyrightText: © 2011-2013 Hewlett-Packard Development Company, L.P.

 SPDX-License-Identifier: GPL-2.0-only
*/
/**
 * \dir
 * \brief Unit test for pkgagent
 */
#include <stdio.h>
#include <stdlib.h>
#include <assert.h>
#include "CUnit/CUnit.h"
#include "CUnit/Automated.h"

#include "libfossology.h"
#include "libfocunit.h"
#include "libfodbreposysconf.h"

/* test case sets */
extern CU_TestInfo testcases_GetFieldValue[];
extern CU_TestInfo testcases_GetMetadata[];
//extern CU_TestInfo testcases_ProcessUpload[];
extern CU_TestInfo testcases_RecordMetadataRPM[];
extern CU_TestInfo testcases_RecordMetadataDEB[];
extern CU_TestInfo testcases_GetMetadataDebSource[];
extern CU_TestInfo testcases_GetMetadataDebBinary[];

char *DBConfFile = NULL;
fo_dbManager* dbManager = NULL;

#define AGENT_DIR "../../"

/**
 * \brief initialize db
 */
int PkgagentDBInit()
{
  dbManager = createTestEnvironment(AGENT_DIR, "pkgagent", 1);
  DBConfFile = get_dbconf();
  return dbManager ? 0 : 1;
}

/**
 * \brief clean db
 */
int PkgagentDBClean()
{
  if (dbManager) {
    dropTestEnvironment(dbManager, AGENT_DIR, "pkgagent");
  }
  return 0;
}

/* create test suite */
#if CU_VERSION_P == 213
CU_SuiteInfo suites[] = {
    {"Testing the function GetFieldValue:", NULL, NULL, NULL, NULL, testcases_GetFieldValue},
    //{"Testing the function ProcessUpload:", NULL, NULL, NULL, NULL, testcases_ProcessUpload},
    {"Testing the function RecordMetadataDEB:", NULL, NULL, (CU_SetUpFunc)PkgagentDBInit, (CU_TearDownFunc)PkgagentDBClean, testcases_RecordMetadataDEB},
    {"Testing the function GetMetadataDebSource:", NULL, NULL, (CU_SetUpFunc)PkgagentDBInit, (CU_TearDownFunc)PkgagentDBClean, testcases_GetMetadataDebSource},
    {"Testing the function RecordMetadataRPM:", NULL, NULL, (CU_SetUpFunc)PkgagentDBInit, (CU_TearDownFunc)PkgagentDBClean, testcases_RecordMetadataRPM},
    {"Testing the function GetMetadataDebBinary:", NULL, NULL, (CU_SetUpFunc)PkgagentDBInit, (CU_TearDownFunc)PkgagentDBClean, testcases_GetMetadataDebBinary},
    {"Testing the function GetMetadata:", NULL, NULL, (CU_SetUpFunc)PkgagentDBInit, (CU_TearDownFunc)PkgagentDBClean, testcases_GetMetadata},
    CU_SUITE_INFO_NULL
};
#else
CU_SuiteInfo suites[] = {
    {"Testing the function GetFieldValue:", NULL, NULL, testcases_GetFieldValue},
    //{"Testing the function ProcessUpload:", NULL, NULL, testcases_ProcessUpload},
    {"Testing the function RecordMetadataDEB:", PkgagentDBInit, PkgagentDBClean, testcases_RecordMetadataDEB},
    {"Testing the function GetMetadataDebSource:", PkgagentDBInit, PkgagentDBClean, testcases_GetMetadataDebSource},
    {"Testing the function RecordMetadataRPM:", PkgagentDBInit, PkgagentDBClean, testcases_RecordMetadataRPM},
    {"Testing the function GetMetadataDebBinary:", PkgagentDBInit, PkgagentDBClean, testcases_GetMetadataDebBinary},
    {"Testing the function GetMetadata:", PkgagentDBInit, PkgagentDBClean, testcases_GetMetadata},
    CU_SUITE_INFO_NULL
};
#endif

int main( int argc, char *argv[] )
{
  return focunit_main(argc, argv, "pkgagent_Tests", suites) ;
}
