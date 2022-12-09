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
 * \brief Testing for the function DBLoadMime
 */

extern void DBLoadMime();
extern char *DBConfFile;

/**
 * \brief initialize DB
 */
int  DBLoadMimeInit()
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
int DBLoadMimeClean()
{
  if (pgConn) PQfinish(pgConn);
  DBMime = NULL;
  return 0;
}

/* test functions */

/**
 * \brief for function DBLoadMime()
 * \test
 * -# Call DBLoadMime()
 * -# Check if MaxDBMime equals actual count in mimetype table
 */
void testDBLoadMime()
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
  memset(SQL, '\0', MAXCMD);
  snprintf(SQL, MAXCMD, "INSERT INTO mimetype (mimetype_name) VALUES ('%s');", mimetype_name);
  result =  PQexec(pgConn, SQL);
  if (fo_checkPQcommand(pgConn, result, SQL, __FILE__, __LINE__))
  {
    PQfinish(pgConn);
    exit(-1);
  }
  PQclear(result);
  MaxDBMime = 0;
  /* exectue the tested function */
  DBLoadMime();
  /* select the record mimetype_name is application/octet-stream */
  memset(SQL, '\0', MAXCMD);
  snprintf(SQL, MAXCMD, "SELECT mimetype_name from mimetype where mimetype_name = ('%s');", mimetype_name);
  result =  PQexec(pgConn, SQL);
  if (fo_checkPQresult(pgConn, result, SQL, __FILE__, __LINE__))
  {
    PQfinish(pgConn);
    exit(-1);
  }
  int count = PQntuples(result);
  PQclear(result);

  CU_ASSERT_EQUAL(MaxDBMime, count);
  /* delete the record mimetype_name is application/octet-stream in mimetype */
  memset(SQL, '\0', MAXCMD);
  snprintf(SQL, MAXCMD, "DELETE FROM mimetype where mimetype_name = '%s';", mimetype_name);
  result =  PQexec(pgConn, SQL);
  /* reset the evn, that is clear all data in mimetype */
  if (fo_checkPQcommand(pgConn, result, SQL, __FILE__, __LINE__))
  {
    PQfinish(pgConn);
    exit(-1);
  }
  MaxDBMime = 0;
  PQclear(result);
}

/**
 * \brief testcases for function DBLoadGold
 */
CU_TestInfo testcases_DBLoadMime[] =
{
#if 0
#endif
{"DBLoadMime:InsertOctet", testDBLoadMime},
  CU_TEST_INFO_NULL
};

