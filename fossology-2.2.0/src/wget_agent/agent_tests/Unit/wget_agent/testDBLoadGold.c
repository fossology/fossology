/*********************************************************************
Copyright (C) 2011-2012 Hewlett-Packard Development Company, L.P.

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

/* cunit includes */
#include <CUnit/CUnit.h>
#include "wget_agent.h"
#include "utility.h"
#include <string.h>
#include <ctype.h>
#include "libfodbreposysconf.h"

/**
 * \file testDBLoadGold.c
 * \brief testing for the function DBLoadGold
 */

static PGresult *result = NULL;
static fo_conf* config;

/**
 * \brief initialize
 * at first download one file(dir), save as one tar file
 */
int  DBLoadGoldInit()
{
  char URL[MAXCMD];
  char TempFileDir[MAXCMD];
  char TempFile[MAXCMD];
  char *DBConfFile = NULL;  /* use default Db.conf */
  char *ErrorBuf;
  GError* error = NULL;

  /** create db */
  create_db_repo_sysconf(1, "wget_agent");
  DBConfFile = get_dbconf();

  strcpy(GlobalParam, "-l 1 -A gz -R fosso*,index.html*");
  strcpy(URL, "http://www.fossology.org/testdata/wgetagent/debian/");
  strcpy(TempFileDir, "./test_result/");
  strcpy(TempFile, "./test_result/wget.tar");
  GetURL(TempFile, URL, TempFileDir);
  strcpy(GlobalTempFile,"./test_result/wget.tar");
  strcpy(GlobalURL, "http://www.fossology.org/testdata/wgetagent/debian/");

  pgConn = fo_dbconnect(DBConfFile, &ErrorBuf);
  if (!pgConn)
  {
    LOG_FATAL("Unable to connect to database");
    SafeExit(20);
  }
  /** delete the record that upload_filename is wget.tar, pre testing */
  memset(SQL,'\0',MAXCMD);
  snprintf(SQL,MAXCMD, "DELETE FROM upload where upload_filename = 'wget.tar';");
  result =  PQexec(pgConn, SQL);
  if (fo_checkPQcommand(pgConn, result, SQL, __FILE__ ,__LINE__))
  {
    printf("Perpare delete information ERROR!\n");
    if (pgConn) PQfinish(pgConn);
    exit(-1);
  }
  PQclear(result);

  /** insert upload wget.tar */
  memset(SQL,'\0',MAXCMD);
  snprintf(SQL,MAXCMD,"INSERT INTO upload (upload_filename,upload_mode,upload_ts) VALUES ('wget.tar',40,now());");
  result =  PQexec(pgConn, SQL);
  if (fo_checkPQcommand(pgConn, result, SQL, __FILE__ ,__LINE__))
  {
    printf("Perpare upload information ERROR!\n");
    if (pgConn) PQfinish(pgConn);
    exit(-1);
  }
  PQclear(result);
  /** get upload id */
  memset(SQL,'\0',MAXCMD);
  snprintf(SQL,MAXCMD,"SELECT upload_pk from upload where upload_filename = 'wget.tar';");
  result =  PQexec(pgConn, SQL);
  if (fo_checkPQresult(pgConn, result, SQL, __FILE__ ,__LINE__))
  {
    printf("Perpare upload information ERROR!\n");
    if (pgConn) PQfinish(pgConn);
    exit(-1);
  }
  GlobalUploadKey = atoi(PQgetvalue(result,0,0));
  PQclear(result);
  config = fo_config_load(DBConfFile, &error);

  return 0;
}
/**
 * \brief clean the env
 */
int DBLoadGoldClean()
{
  memset(GlobalTempFile, 0, MAXCMD);
  memset(GlobalURL, 0, MAXCMD);
  memset(GlobalParam, 0, MAXCMD);
  long pfile_id = 0;
  char TempFileDir[MAXCMD];
  /** get pfile_pk about this upload, for this upload, only one file */
  memset(SQL,'\0',MAXCMD);
  snprintf(SQL,MAXCMD, "select pfile_fk from upload where upload_pk = %ld;", GlobalUploadKey);
  result =  PQexec(pgConn, SQL);
  if (fo_checkPQresult(pgConn, result, SQL, __FILE__ ,__LINE__))
  {
    printf("get pfile id ERROR!\n");
    if (pgConn) PQfinish(pgConn);
    exit(-1);
  }
  pfile_id = atoi(PQgetvalue(result,0,0)); 
  PQclear(result);
  /** delete the record that upload_filename is wget.tar, post testing */
  memset(SQL,'\0',MAXCMD);
  snprintf(SQL,MAXCMD, "DELETE FROM upload where upload_filename = 'wget.tar';");
  result =  PQexec(pgConn, SQL);
  if (fo_checkPQcommand(pgConn, result, SQL, __FILE__ ,__LINE__))
  {
    printf("Perpare delete information ERROR!\n");
    if (pgConn) PQfinish(pgConn);
    exit(-1);
  }
  PQclear(result);

  strcpy(TempFileDir, "./test_result");
  if (file_dir_existed(TempFileDir))
  {
    RemoveDir(TempFileDir);
  }

  /** delete the pfile record during this test */
  memset(SQL,'\0',MAXCMD);
  snprintf(SQL, MAXCMD-1, "DELETE FROM pfile where pfile_pk = %ld ", pfile_id);
  result =  PQexec(pgConn, SQL);
  if (fo_checkPQcommand(pgConn, result, SQL, __FILE__, __LINE__))
  {
    if (pgConn) PQfinish(pgConn);
    SafeExit(-1);
  }
  PQclear(result);

  if (pgConn) PQfinish(pgConn);
  if (config)  fo_config_free(config);

  drop_db_repo_sysconf(get_db_name());
  GlobalUploadKey = -1;

  return 0;
}

/**
 * \brief convert a string to lowcase
 *
 * \param char *string - the string will be converted to lowcase
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
 * \brief for function DBLoadGold
 */
void testDBLoadGold()
{
  //printf("db start\n");
  DBLoadGold();
  //printf("db end\n");
  char SQL[MAXCMD];
  char *pfile_sha1;
  char *pfile_md5;
  memset(SQL, 0, MAXCMD);
  PGresult *result;
  snprintf(SQL, MAXCMD-1, "select pfile_sha1, pfile_md5 from pfile where pfile_pk in (select pfile_fk from upload where upload_pk = %ld);", GlobalUploadKey);
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
  char file_name_file[MAXCMD] = {0};
  char file_name_gold[MAXCMD] = {0};
  char string0[3] = {0};
  char string1[3] = {0};
  char string2[3] = {0};
  char *string4 = get_repodir();
  strncpy(string0, pfile_sha1, 2);
  strncpy(string1, pfile_sha1 + 2, 2);
  strncpy(string2, pfile_sha1 + 4, 2);
  //printf("string0, string1, string2 are:%s, %s, %s\n", string0, string1, string2);
  sprintf(file_name_file, "%s/localhost/files/%s/%s/%s/%s.%s.10240", string4, string0, string1, string2, pfile_sha1, pfile_md5);
  sprintf(file_name_gold, "%s/localhost/gold/%s/%s/%s/%s.%s.10240", string4, string0, string1, string2, pfile_sha1, pfile_md5);
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

