/*********************************************************************
Copyright (C) 2012 Hewlett-Packard Development Company, L.P.

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
#include "run_tests.h"
#include "../agent/externs.h"
/**
 * \file test_DBInsert.c
 * \brief tests for ununpack DB access function
 */

static PGresult *result = NULL;
static long upload_pk = -1;
static long pfile_pk = -1;
extern char *DBConfFile;

/**
 * \brief initialize
 */
int  DBInsertInit()
{
  char *ErrorBuf;
  char *upload_filename = "argmatch.c.gz";
  int upload_mode = 104;
  char *upload_origin = "argmatch.c.gz";
  char *tmp;

  pgConn = fo_dbconnect(DBConfFile, &ErrorBuf);
  if (!pgConn)
  {
    LOG_FATAL("Unable to connect to database");
    exit(-1);
  }
 
  /** insert upload info */
  memset(SQL,'\0',MAXSQL);
  snprintf(SQL,MAXSQL,"INSERT INTO upload (upload_filename,upload_mode,upload_origin) VALUES ('%s', %d, '%s');",upload_filename, upload_mode, upload_origin);
  result =  PQexec(pgConn, SQL);
  if (fo_checkPQcommand(pgConn, result, SQL, __FILE__ ,__LINE__))
  {
    printf("Insert upload information ERROR!\n");
    return (-1);
  }
  PQclear(result); 

  /* select the upload pk */
  memset(SQL,'\0',MAXSQL);
  snprintf(SQL,MAXSQL,"SELECT upload_pk FROM upload WHERE upload_filename = '%s';",
        upload_filename);
  result =  PQexec(pgConn, SQL);  /* SELECT */
  if (fo_checkPQresult(pgConn, result, SQL, __FILE__, __LINE__)) return(-1);

  tmp = PQgetvalue(result,0,0);
  if(tmp)
  {
    Upload_Pk = tmp;
    upload_pk = atol(tmp);
  }
  PQclear(result);
  return 0;
}

/**
 * \brief clean the database
 */
int DBInsertClean()
{
  memset(SQL,'\0',MAXSQL);
  snprintf(SQL,MAXSQL,"BEGIN;");
  result =  PQexec(pgConn, SQL);
  if (fo_checkPQcommand(pgConn, result, SQL, __FILE__ ,__LINE__))
  {
    printf("Remove pfile database information ERROR!\n");
    return (-1);
  }
  PQclear(result);

  /** delete uploadtree info */
  memset(SQL,'\0',MAXSQL);
  snprintf(SQL,MAXSQL,"DELETE FROM uploadtree WHERE upload_fk = %ld;", upload_pk);
  result =  PQexec(pgConn, SQL);
  if (fo_checkPQcommand(pgConn, result, SQL, __FILE__ ,__LINE__))
  {
    printf("Remove pfile database information ERROR!\n");
    return (-1);
  }
  PQclear(result);

  /** delete upload info */
  memset(SQL,'\0',MAXSQL);
  snprintf(SQL,MAXSQL,"DELETE FROM upload WHERE upload_pk = %ld;", upload_pk);
  result =  PQexec(pgConn, SQL);
  if (fo_checkPQcommand(pgConn, result, SQL, __FILE__ ,__LINE__))
  {
    printf("Remove pfile database information ERROR!\n");
    return (-1);
  }
  PQclear(result);

  /** delete pfile info */
  memset(SQL,'\0',MAXSQL);
  snprintf(SQL,MAXSQL,"DELETE FROM pfile WHERE pfile_pk = %ld;", pfile_pk);
  result =  PQexec(pgConn, SQL);
  if (fo_checkPQcommand(pgConn, result, SQL, __FILE__ ,__LINE__))
  {
    printf("Remove pfile database information ERROR!\n");
    return (-1);
  }
  PQclear(result);


  memset(SQL,'\0',MAXSQL);
  snprintf(SQL,MAXSQL,"COMMIT;");
  result =  PQexec(pgConn, SQL);
  if (fo_checkPQcommand(pgConn, result, SQL, __FILE__ ,__LINE__))
  {
    printf("Perpare pfile information ERROR!\n");
    return (-1);
  }
  PQclear(result);

  if (pgConn) PQfinish(pgConn);
  return 0;
}

/**
 * \brief test DBInsertPfile function
 */
void testDBInsertPfile()
{
  ContainerInfo *CI = NULL;
  struct stat Stat = {0};
  ParentInfo PI = {0, 1287725739, 1287725739, 0, 0};
  ContainerInfo CITest = {"../test-data/testdata4unpack/argmatch.c.gz", "./test-result/", "argmatch.c.gz", "argmatch.c.gz.dir", 1, 1, 0, 0, Stat, PI, 0, 0, 0, 0, 0, 0};
  CI = &CITest;
  char *Fuid = "657db64230b9d647362bfe0ebb82f7bd1d879400.a0f2e4d071ba2e68910132a8da5784a6.2920";
  int result = DBInsertPfile(CI, Fuid);  
  CU_ASSERT_EQUAL(result, 1);
}

/**
 * \brief test DBInsertUploadTree function
 */
void testDBInsertUploadTree()
{
  ContainerInfo *CI = NULL;
  struct stat Stat = {0};
  ParentInfo PI = {0, 1287725739, 1287725739, 0, 0};
  ContainerInfo CITest = {"../test-data/testdata4unpack/argmatch.c.gz", "./test-result/", "argmatch.c.gz", "argmatch.c.gz.dir", 1, 1, 0, 0, Stat, PI, 0, 0, 0, 0, 0, 0};
  CI = &CITest;
  int result = DBInsertUploadTree(CI, 1);
  CU_ASSERT_EQUAL(result, 0);
}

/* ************************************************************************** */
/* **** cunit test cases **************************************************** */
/* ************************************************************************** */

CU_TestInfo DBInsertPfile_testcases[] =
{
  {"DBInsertPfile:", testDBInsertPfile},
  CU_TEST_INFO_NULL
};
CU_TestInfo DBInsertUploadTree_testcases[] =
{
  {"DBInsertUploadTree:", testDBInsertUploadTree},
  CU_TEST_INFO_NULL
};

