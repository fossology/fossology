/*
 SPDX-FileCopyrightText: © 2011 Hewlett-Packard Development Company, L.P.
 SPDX-FileCopyrightText: © 2018 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

#include "testRun.h"
#include "finder.h"
#include <unistd.h>

/**
 * \dir mimetype/agent_tests/Unit
 * \brief Unit tests for mimetype agent
 * \dir mimetype/agent_tests/Unit/finder
 * \brief Actual test cases
 * \file
 * \brief Main function for in this testing module
 */

char *DBConfFile = NULL;

/**
 * \brief all test suites for mimetype
 */

#if CU_VERSION_P == 213
CU_SuiteInfo suites[] = {
    // for finder.c
    {"DBCheckMime", NULL, NULL, (CU_SetUpFunc)DBCheckMimeInit, (CU_TearDownFunc)DBCheckMimeClean, testcases_DBCheckMime},
    {"DBLoadMime", NULL, NULL, (CU_SetUpFunc)DBLoadMimeInit, (CU_TearDownFunc)DBLoadMimeClean, testcases_DBLoadMime},
    {"DBFindMime", NULL, NULL, (CU_SetUpFunc)DBFindMimeInit, (CU_TearDownFunc)DBFindMimeClean, testcases_DBFindMime},
    {"CheckMimeType", NULL, NULL, (CU_SetUpFunc)DBInit, (CU_TearDownFunc)DBClean, testcases_CheckMimeTypes},
    {"DBCheckFileExtention", NULL, NULL, (CU_SetUpFunc)DBInit, (CU_TearDownFunc)DBClean, testcases_DBCheckFileExtention},
    {"Utilities", NULL, NULL, NULL, NULL, testcases_Utilities},
    CU_SUITE_INFO_NULL
};
#else
CU_SuiteInfo suites[] = {
    // for finder.c
    {"DBCheckMime", DBCheckMimeInit, DBCheckMimeClean, testcases_DBCheckMime},
    {"DBLoadMime", DBLoadMimeInit, DBLoadMimeClean, testcases_DBLoadMime},
    {"DBFindMime", DBFindMimeInit, DBFindMimeClean, testcases_DBFindMime},
    {"CheckMimeType", DBInit, DBClean, testcases_CheckMimeTypes},
    {"DBCheckFileExtention", DBInit, DBClean, testcases_DBCheckFileExtention},
    {"Utilities", NULL, NULL, testcases_Utilities},
    CU_SUITE_INFO_NULL
};
#endif

/*
 * \brief Create required tables
 */
void createTables()
{
  char *ErrorBuf;
  char SQL[1024];
  PGresult *result = NULL;

  pgConn = fo_dbconnect(DBConfFile, &ErrorBuf);

  memset(SQL,'\0',1024);
  sprintf(SQL, "%s",
      "CREATE TABLE uploadtree"
      " (uploadtree_pk SERIAL, parent integer, realparent integer, upload_fk integer, pfile_fk integer, ufile_mode integer,"
      " lft integer, rgt integer, ufile_name text);"
      );
  result =  PQexec(pgConn, SQL);
  if (fo_checkPQcommand(pgConn, result, SQL, __FILE__ ,__LINE__))
  {
    printf("Create uploadtree ERROR!\n");
    return;
  }
  PQclear(result);

  memset(SQL,'\0',1024);
  sprintf(SQL, "%s",
      "CREATE TABLE upload"
      " (upload_pk SERIAL, upload_desc text, upload_filename text, user_fk integer, upload_mode integer,"
      " upload_ts timestamp with time zone DEFAULT now(), pfile_fk integer, upload_origin text,"
      " uploadtree_tablename character varying(18) DEFAULT 'uploadtree_a'::character varying, expire_date date,"
      " expire_action character(1), public_perm integer);"
      );
  result =  PQexec(pgConn, SQL);
  if (fo_checkPQcommand(pgConn, result, SQL, __FILE__ ,__LINE__))
  {
    printf("Create uploadtree ERROR!\n");
    return;
  }
  PQclear(result);

  memset(SQL,'\0',1024);
  sprintf(SQL, "%s",
      "CREATE TABLE pfile"
      " (pfile_pk SERIAL, pfile_md5 character(32), pfile_sha1 character(40), pfile_size bigint, pfile_mimetypefk integer);"
      );
  result =  PQexec(pgConn, SQL);
  if (fo_checkPQcommand(pgConn, result, SQL, __FILE__ ,__LINE__))
  {
    printf("Create pfile ERROR!\n");
    return;
  }
  PQclear(result);

  memset(SQL,'\0',1024);
  sprintf(SQL, "%s", "CREATE TABLE mimetype (mimetype_pk SERIAL, mimetype_name text);");
  result =  PQexec(pgConn, SQL);
  if (fo_checkPQcommand(pgConn, result, SQL, __FILE__ ,__LINE__))
  {
    printf("Create mimetype ERROR!\n");
    return;
  }
  PQclear(result);

  memset(SQL,'\0',1024);
  sprintf(SQL, "%s",
      "INSERT INTO public.mimetype (mimetype_pk, mimetype_name) VALUES"
      " (2, 'application/gzip'), (3, 'application/x-gzip'), (4, 'application/x-compress'), (5, 'application/x-bzip'),"
      " (6, 'application/x-bzip2'), (7, 'application/x-upx'), (8, 'application/pdf'), (9, 'application/x-pdf'),"
      " (10, 'application/x-zip'), (11, 'application/zip'), (12, 'application/x-tar'), (13, 'application/x-gtar'),"
      " (14, 'application/x-cpio'), (15, 'application/x-rar'), (16, 'application/x-cab'), (17, 'application/x-7z-compressed'),"
      " (18, 'application/x-7z-w-compressed'), (19, 'application/x-rpm'), (20, 'application/x-archive'),"
      " (21, 'application/x-debian-package'), (22, 'application/x-iso'), (23, 'application/x-iso9660-image'),"
      " (24, 'application/x-fat'), (25, 'application/x-ntfs'), (26, 'application/x-ext2'), (27, 'application/x-ext3'),"
      " (28, 'application/x-x86_boot'), (29, 'application/x-debian-source'), (30, 'application/x-xz'), (31, 'application/jar'),"
      " (32, 'application/java-archive'), (33, 'application/x-dosexec'), (34, 'text/plain');"
      );
  result =  PQexec(pgConn, SQL);
  if (fo_checkPQcommand(pgConn, result, SQL, __FILE__ ,__LINE__))
  {
    printf("Insert mimetype ERROR!\n");
    return;
  }
  PQclear(result);
}

/*
 * \brief Main test function
 */
int main( int argc, char *argv[] )
{
  char cwd[2048];
  char* confDir = NULL;
  char CMD[2048];
  int rc;

  if(getcwd(cwd, sizeof(cwd)) != NULL)
  {
    confDir = createTestConfDir(cwd, "mimetype");
  }

  create_db_repo_sysconf(0, "mimetype", confDir);
  DBConfFile = get_dbconf();

  createTables();
  sprintf(CMD,"rm -rf %s", confDir);
  rc = system(CMD);

  rc = focunit_main(argc, argv, "mimetype_Tests", suites) ;
  drop_db_repo_sysconf(get_db_name());

  return rc;
}

