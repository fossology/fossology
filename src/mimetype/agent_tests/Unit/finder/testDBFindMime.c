/*
 SPDX-FileCopyrightText: © 2011 Hewlett-Packard Development Company, L.P.

 SPDX-License-Identifier: GPL-2.0-only
*/

/* cunit includes */
#include <CUnit/CUnit.h>
#include "finder.h"
#include <string.h>

/**
 * \file
 * \brief testing for the function DBFindMime()
 */

extern char *DBConfFile;

/**
 * \brief initialize
 */
int  DBFindMimeInit()
{
  char *ErrorBuf;

  pgConn = fo_dbconnect(DBConfFile, &ErrorBuf);
  if (!pgConn)
  {
    LOG_FATAL("Unable to connect to database");
    exit(-1);
  }
  MagicCookie = magic_open(MAGIC_PRESERVE_ATIME|MAGIC_MIME);
  DBMime = NULL;

  return 0;
}
/**
 * \brief clean the env
 */
int DBFindMimeClean()
{
  if (pgConn) PQfinish(pgConn);
  DBMime = NULL;
  return 0;
}

/* test functions */

/**
 * \brief for function DBFindMime()
 * \test
 * -# Create one new entry from mimetype table
 * -# Get the mimetype id from DBFindMime() for the inserted type
 * -# Check if the value returned from function matches the actual id
 * -# Call DBFindMime() on a type which does not exists in DB
 * -# Check if -1 is returned
 */
void testDBFindMime()
{
  char SQL[MAXCMD] = {0};
  PGresult *result = NULL;
  char mimetype_name[] = "application/octet-stream";
  /* delete the record mimetype_name is application/octet-stream in mimetype */
  memset(SQL, '\0', MAXCMD);
  snprintf(SQL, MAXCMD, "DELETE FROM mimetype where mimetype_name = '%s';", mimetype_name);
  result =  PQexec(pgConn, SQL);
  if (fo_checkPQcommand(pgConn, result, SQL, __FILE__, __LINE__))
  {
    PQfinish(pgConn);
    exit(-1);
  }
  PQclear(result);
  /* insert one record */
  memset(SQL, '\0', MAXCMD);
  snprintf(SQL, MAXCMD, "INSERT INTO mimetype (mimetype_name) VALUES ('%s');", mimetype_name);
  result =  PQexec(pgConn, SQL);
  if (fo_checkPQcommand(pgConn, result, SQL, __FILE__, __LINE__))
  {
    PQfinish(pgConn);
    exit(-1);
  }
  PQclear(result);
  /* exectue the tested function */
  /* 1. the Mimetype is already in table mimetype */
  int ret = DBFindMime(mimetype_name);
  /* select the record mimetype_name is application/octet-stream */
  memset(SQL, '\0', MAXCMD);
  snprintf(SQL, MAXCMD, "SELECT mimetype_name from mimetype where mimetype_name = ('%s');", mimetype_name);
  result =  PQexec(pgConn, SQL);
  if (fo_checkPQresult(pgConn, result, SQL, __FILE__, __LINE__))
  {
    PQfinish(pgConn);
    exit(-1);
  }
  int mimetype_id = atoi(PQgetvalue(result, 0, 0));
  PQclear(result);

  CU_ASSERT_NOT_EQUAL(ret, mimetype_id);

  /* delete the record mimetype_name is application/octet-stream in mimetype */
  memset(SQL, '\0', MAXCMD);
  snprintf(SQL, MAXCMD, "DELETE FROM mimetype where mimetype_name = '%s';", mimetype_name);
  result =  PQexec(pgConn, SQL);
  if (fo_checkPQcommand(pgConn, result, SQL, __FILE__, __LINE__))
  {
    PQfinish(pgConn);
    exit(-1);
  }
  PQclear(result);

  DBMime = NULL;
  /* 2. the Mimetype is not in table mimetype */
  /* select the record mimetype_name is application/octet-stream */
  ret = DBFindMime(mimetype_name);
  memset(SQL, '\0', MAXCMD);
  snprintf(SQL, MAXCMD, "SELECT mimetype_name from mimetype where mimetype_name = ('%s');", mimetype_name);
  result =  PQexec(pgConn, SQL);
  if (fo_checkPQresult(pgConn, result, SQL, __FILE__, __LINE__))
  {
    PQfinish(pgConn);
    exit(-1);
  }
  mimetype_id = 0;
  mimetype_id = atoi(PQgetvalue(result, 0, 0));
  PQclear(result);

  CU_ASSERT_NOT_EQUAL(ret, mimetype_id);
  /* delete the record mimetype_name is application/octet-stream in mimetype */
  memset(SQL, '\0', MAXCMD);
  snprintf(SQL, MAXCMD, "DELETE FROM mimetype where mimetype_name = '%s';", mimetype_name);
  result =  PQexec(pgConn, SQL);
  if (fo_checkPQcommand(pgConn, result, SQL, __FILE__, __LINE__))
  {
    PQfinish(pgConn);
    exit(-1);
  }
  PQclear(result);
}

/**
 * \brief testcases for function DBFindMime
 */
CU_TestInfo testcases_DBFindMime[] =
{
#if 0
#endif
{"DBFindMime:ExistAndNot", testDBFindMime},
  CU_TEST_INFO_NULL
};

