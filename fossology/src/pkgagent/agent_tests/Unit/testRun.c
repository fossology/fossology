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
char *TestSysconf = NULL;
char *TestName = NULL;

/**
 * brief get command output
 */
void command_output(char *command)
{
  FILE *stream;
  char tmp[256];
  int i=0;
 
  stream = popen(command, "r");
  memset(tmp, '\0', sizeof(tmp));
  if (fgets(tmp, 256, stream) != NULL)
  {
    while((tmp[i] != '\n') && (tmp[i] != ' ') && (tmp[i] != EOF))
      i++; 
    TestSysconf = malloc(i);
    memcpy(TestSysconf, tmp, i);
    TestSysconf[i] = '\0';
  }
  pclose(stream);
  return;
}
/**
 * \brief initialize db
 */
int PkgagentDBInit()
{
  char CMD[256];

  memset(CMD, '\0', sizeof(CMD));
  sprintf(CMD, "../../../testing/db/createTestDB.php -c /usr/local/etc/fossology");
  command_output(CMD);
  TestName = strstr(TestSysconf, "Conf") + 4;

  if ( TestSysconf != NULL)
  {
    DBConfFile = malloc(256);
    sprintf(DBConfFile, "%s/Db.conf", TestSysconf);
    printf("EE:%s:FF\n", DBConfFile);
  }
  free(TestSysconf);
  return 0;
}
/**
 * \brief clean db
 */
int PkgagentDBClean()
{
  char CMD[256];
  int rc;

  // remove test database 
  memset(CMD, '\0', sizeof(CMD));
  sprintf(CMD, "../../../testing/db/createTestDB.php -d fosstest%s", TestName);
  rc = system(CMD);
  if (rc != 0)
  {
    printf("Test Database clean ERROR!\n");
    return -1;
  }
  // remove test config files
  memset(CMD, '\0', sizeof(CMD));
  sprintf(CMD, "rm -rf /srv/fossology/testDbConf%s", TestName);
  rc = system(CMD);
  if (rc != 0)
  {
    printf("Test Database Conf files clean ERROR!\n");
    return -1;
  }
  // remove test repo
  memset(CMD, '\0', sizeof(CMD));
  sprintf(CMD, "rm -rf /srv/fossology/testDbRepo%s", TestName);
  rc = system(CMD);
  if (rc != 0)
  {
    printf("Test Repo clean ERROR!\n");
    return -1;
  }
  TestName = NULL;
  TestSysconf = NULL;
  DBConfFile = NULL;

  return 0;
}

/* create test suite */
CU_SuiteInfo suites[] = {
    //{"Testing the function trim:", NULL, NULL, testcases_Trim},
    //{"Testing the function GetFieldValue:", NULL, NULL, testcases_GetFieldValue},
    //{"Testing the function ProcessUpload:", NULL, NULL, testcases_ProcessUpload},
    //{"Testing the function RecordMetadataDEB:", PkgagentDBInit, PkgagentDBClean, testcases_RecordMetadataDEB},
    //{"Testing the function GetMetadataDebSource:", PkgagentDBInit, PkgagentDBClean, testcases_GetMetadataDebSource},
    //{"Testing the function RecordMetadataRPM:", PkgagentDBInit, PkgagentDBClean, testcases_RecordMetadataRPM},
    {"Testing the function GetMetadataDebBinary:", PkgagentDBInit, PkgagentDBClean, testcases_GetMetadataDebBinary},
    //{"Testing the function GetMetadata:", PkgagentDBInit, PkgagentDBClean, testcases_GetMetadata},
    CU_SUITE_INFO_NULL
};

void AddTests(void)
{
  assert(NULL != CU_get_registry());
  assert(!CU_is_test_running());


  if(CUE_SUCCESS != CU_register_suites(suites)){
    fprintf(stderr, "Register suites failed - %s ", CU_get_error_msg());
    exit(EXIT_FAILURE);
  }
}

int main( int argc, char *argv[] )
{
  printf("Test Start\n");
  if(CU_initialize_registry()){

    fprintf(stderr, "\nInitialization of Test Registry failed.\n");
    exit(EXIT_FAILURE);
  }else{
    AddTests();

    CU_set_output_filename("pkgagent_Test");
    CU_list_tests_to_file();
    CU_automated_run_tests();
    //CU_cleanup_registry();
  }
  printf("Test End\n");
  printf("Results:\n");
  printf("  Number of suites run: %d\n", CU_get_number_of_suites_run());
  printf("  Number of tests run: %d\n", CU_get_number_of_tests_run());
  printf("  Number of tests failed: %d\n", CU_get_number_of_tests_failed());
  printf("  Number of asserts: %d\n", CU_get_number_of_asserts());
  printf("  Number of successes: %d\n", CU_get_number_of_successes());
  printf("  Number of failures: %d\n", CU_get_number_of_failures());
  CU_cleanup_registry();
  return 0;
}
