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
extern CU_TestInfo testcases_Trim[];
//extern CU_TestInfo testcases_ProcessUpload[];
extern CU_TestInfo testcases_RecordMetadataRPM[];
extern CU_TestInfo testcases_RecordMetadataDEB[];
extern CU_TestInfo testcases_GetMetadataDebSource[];
extern CU_TestInfo testcases_GetMetadataDebBinary[];

char *DBConfFile = NULL;

/**
 * \brief initialize db
 */
int PkgagentDBInit()
{
  create_db_repo_sysconf(1, "pkgagent");
  DBConfFile = get_dbconf();
  return 0;
}
/**
 * \brief clean db
 */
int PkgagentDBClean()
{
  drop_db_repo_sysconf(get_db_name());
  return 0;
}

/* create test suite */
CU_SuiteInfo suites[] = {
    {"Testing the function trim:", NULL, NULL, testcases_Trim},
    {"Testing the function GetFieldValue:", NULL, NULL, testcases_GetFieldValue},
    //{"Testing the function ProcessUpload:", NULL, NULL, testcases_ProcessUpload},
    {"Testing the function RecordMetadataDEB:", PkgagentDBInit, PkgagentDBClean, testcases_RecordMetadataDEB},
    {"Testing the function GetMetadataDebSource:", PkgagentDBInit, PkgagentDBClean, testcases_GetMetadataDebSource},
    {"Testing the function RecordMetadataRPM:", PkgagentDBInit, PkgagentDBClean, testcases_RecordMetadataRPM},
    {"Testing the function GetMetadataDebBinary:", PkgagentDBInit, PkgagentDBClean, testcases_GetMetadataDebBinary},
    {"Testing the function GetMetadata:", PkgagentDBInit, PkgagentDBClean, testcases_GetMetadata},
    CU_SUITE_INFO_NULL
};

int main( int argc, char *argv[] )
{
  return focunit_main(argc, argv, "pkgagent_Tests", suites) ;
}
