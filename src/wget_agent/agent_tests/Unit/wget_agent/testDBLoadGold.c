/*
 SPDX-FileCopyrightText: © 2011-2012 Hewlett-Packard Development Company, L.P.

 SPDX-License-Identifier: GPL-2.0-only
*/

/* cunit includes */
#include <CUnit/CUnit.h>
#include "wget_agent.h"
#include "../utility.h"
#include <string.h>
#include <ctype.h>
#include "libfodbreposysconf.h"

// #define AGENT_DIR "../"
/**
 * \file
 * \brief testing for the function DBLoadGold
 * \dir wget_agent/agent_tests/Unit/wget_agent/
 * \brief Contains actual test cases
 */

static PGresult *result = NULL;
extern fo_conf* sysconfig;

static fo_dbManager* dbManager;
/**
 * \brief initialize
 *
 * At first download one file(dir), save as one tar file
 */
int  DBLoadGoldInit()
{
  char URL[URLMAX];
  char TempFileDir[STRMAX];
  char TempFile[STRMAX];

  /** -# Create db */
  dbManager = createTestEnvironment(AGENT_DIR, "wget_agent", 1);
  if (!dbManager) {
    LOG_FATAL("Unable to connect to database");
    return 1;
  }

  pgConn = fo_dbManager_getWrappedConnection(dbManager);

  /** -# Set  */
  strcpy(GlobalParam, "-l 1 -A *.list -R *.deb");
  strcpy(URL, "https://mirrors.kernel.org/fossology/releases/3.0.0/ubuntu/14.04/");
  strcpy(TempFileDir, "./test_result/");
  strcpy(TempFile, "./test_result/fossology.sources.list");
  GetURL(TempFile, URL, TempFileDir);
  strcpy(GlobalTempFile,"./test_result/fossology.sources.list");
  strcpy(GlobalURL, "https://mirrors.kernel.org/fossology/releases/3.0.0/ubuntu/14.04/");

  /** -# Delete the record that upload_filename is wget.tar, pre testing */
  memset(SQL,'\0',STRMAX);
  snprintf(SQL,STRMAX, "DELETE FROM upload where upload_filename = 'fossology.sources.list';");
  result = PQexec(pgConn, SQL);
  if (fo_checkPQcommand(pgConn, result, SQL, __FILE__ ,__LINE__))
  {
    printf("Perpare delete information ERROR!\n");
    return 1;
  }
  PQclear(result);

  /** -# Insert upload wget.tar */
  memset(SQL,'\0',STRMAX);
  snprintf(SQL,STRMAX,"INSERT INTO upload (upload_filename,upload_mode,upload_ts) VALUES ('fossology.sources.list',40,now());");
  result = PQexec(pgConn, SQL);
  if (fo_checkPQcommand(pgConn, result, SQL, __FILE__ ,__LINE__))
  {
    printf("Perpare upload information ERROR!\n");
    return 1;
  }
  PQclear(result);
  /** -# Get upload id */
  memset(SQL,'\0',STRMAX);
  snprintf(SQL,STRMAX,"SELECT upload_pk from upload where upload_filename = 'fossology.sources.list';");
  result = PQexec(pgConn, SQL);
  if (fo_checkPQresult(pgConn, result, SQL, __FILE__ ,__LINE__))
  {
    printf("Perpare upload information ERROR!\n");
    return 1;
  }
  GlobalUploadKey = atoi(PQgetvalue(result,0,0));
  PQclear(result);

  GError* error = NULL;
  char* foConf = get_confFile();

  char cmd[STRMAX+1];
  snprintf(cmd, STRMAX, "sed -i 's|depth.*|depth=3|' %s", foConf);
  if (system(cmd) != 0) {
    printf("cannot reset depth to 3 with %s\n", cmd);
    return 1;
  }

  sysconfig = fo_config_load(foConf, &error);

  if (error) {
    printf("cannot load config from '%s' error: %s\n", foConf, error->message);
    return 1;
  }

  return 0;
}
/**
 * \brief Clean the env
 */
int DBLoadGoldClean()
{
  memset(GlobalTempFile, 0, STRMAX);
  memset(GlobalURL, 0, URLMAX);
  memset(GlobalParam, 0, STRMAX);
  char TempFileDir[STRMAX];

  strcpy(TempFileDir, "./test_result");
  if (file_dir_existed(TempFileDir))
  {
    RemoveDir(TempFileDir);
  }

  char repoDir[STRMAX+1];
  if (snprintf(repoDir, STRMAX, "%s/repo", get_sysconfdir())>0) {
    RemoveDir(repoDir);
  }

  dropTestEnvironment(dbManager, AGENT_DIR, "wget_agent");
  GlobalUploadKey = -1;

  return 0;
}

/**
 * \brief Convert a string to lower case
 * \param[in,out] string The string will be converted to lower case
 */
void string_tolower(char *string)
{
  int length = strlen(string);
  int i = 0;
  for (i = 0; i < length; i++)
  {
    string[i] = tolower(string[i]);
  }
}

/* test functions */

/**
 * \brief Function to test DBLoadGold
 * \test
 * -# Call DBLoadGold()
 * -# Get the data from pfile table
 * -# Check if the file exists in file system
 */
void testDBLoadGold()
{
  //printf("db start\n");
  DBLoadGold();
  //printf("db end\n");
  char SQL[STRMAX];
  char *pfile_sha1;
  char *pfile_md5;
  memset(SQL, 0, STRMAX);
  PGresult *result;
  snprintf(SQL, STRMAX-1, "select pfile_sha1, pfile_md5 from pfile where pfile_pk in (select pfile_fk from "
      "upload where upload_pk = %ld);", GlobalUploadKey);
  result =  PQexec(pgConn, SQL); /* SELECT */
  if (fo_checkPQresult(pgConn, result, SQL, __FILE__, __LINE__))
  {
    if (pgConn) PQfinish(pgConn);
    SafeExit(-1);
  }
  pfile_sha1 = PQgetvalue(result,0,0);
  pfile_md5 = PQgetvalue(result,0,1);
  //printf("pfile_sha1, pfile_md5 are:%s, %s\n", pfile_sha1, pfile_md5 );
  string_tolower(pfile_sha1);
  string_tolower(pfile_md5);
  //printf("pfile_sha1, pfile_md5 are:%s, %s\n", pfile_sha1, pfile_md5 );
  char file_name_file[STRMAX] = {0};
  char file_name_gold[STRMAX] = {0};
  char string0[3] = {0};
  char string1[3] = {0};
  char string2[3] = {0};
  char *string4 = get_sysconfdir();
  strncpy(string0, pfile_sha1, 2);
  strncpy(string1, pfile_sha1 + 2, 2);
  strncpy(string2, pfile_sha1 + 4, 2);
  //printf("string0, string1, string2 are:%s, %s, %s\n", string0, string1, string2);
  sprintf(file_name_file, "%s/repo/files/%s/%s/%s/%s.%s.10240", string4, string0, string1, string2, pfile_sha1, pfile_md5);
  sprintf(file_name_gold, "%s/repo/gold/%s/%s/%s/%s.%s.10240", string4, string0, string1, string2, pfile_sha1, pfile_md5);
  int existed = file_dir_existed(file_name_file);
  CU_ASSERT_EQUAL(existed, 1); /* the file into repo? */
  if (existed)
  {
    RemoveDir(file_name_file);
  }
  existed = 0;
  existed = file_dir_existed(file_name_gold);
  CU_ASSERT_EQUAL(existed, 1); /* the file into repo? */
  //printf("file_name_file, file_name_gold are:%s,%s\n", file_name_file, file_name_gold);
  if (existed)
  {
    RemoveDir(file_name_gold);
  }
  PQclear(result);
  //printf("testDBLoadGold end\n");
}

/**
 * \brief testcases for function DBLoadGold
 */
CU_TestInfo testcases_DBLoadGold[] =
{
#if 0
#endif
{"DBLoadGold:Insert", testDBLoadGold},
  CU_TEST_INFO_NULL
};

