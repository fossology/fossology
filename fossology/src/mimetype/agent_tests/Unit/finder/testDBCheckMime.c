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

/* cunit includes */
#include <CUnit/CUnit.h>
#include "finder.h"
#include <string.h>

/**
 * \file testDBCheckMime.c
 * \brief testing for the function DBCheckMime
 */

/**
 * \brief initialize
 */
int  DBCheckMimeInit()
{
  pgConn = fo_dbconnect();
  if (!pgConn)
  {
    FATAL("Unable to connect to database");
    exit(-1);
  }
	MagicCookie = magic_open(MAGIC_PRESERVE_ATIME|MAGIC_MIME);

  return 0;
}
/**
 * \brief clean the env
 */
int DBCheckMimeClean()
{
  if (pgConn) PQfinish(pgConn);
  return 0;
}

/* test functions */

/**
 * \brief for function DBCheckMime 
 * 1. case 1: the file is a executable file
 */
void testDBCheckMime()
{
	char SQL[MAXCMD] = {0};
  PGresult *result = NULL;
  char file_path[MAXCMD] = "../../agent/mimetype";
  char mimetype_name[] = "application/octet-stream";
	Akey = -1;
  memset(SQL, '\0', MAXCMD);
  snprintf(SQL, MAXCMD, "DELETE FROM mimetype WHERE mimetype_name= '%s';", mimetype_name);
  result =  PQexec(pgConn, SQL);
  if (fo_checkPQcommand(pgConn, result, SQL, __FILE__, __LINE__))
  {
    PQfinish(pgConn);
    exit(-1);
  }
  int count = PQntuples(result); /**! if the mimetype of the file_path is in table mimetype */

  CU_ASSERT_EQUAL(count, 0);
  PQclear(result);
  DBCheckMime(file_path);
  memset(SQL,'\0',sizeof(SQL));
  snprintf(SQL,sizeof(SQL)-1,"SELECT * FROM mimetype WHERE mimetype_name= '%s';", mimetype_name);
  result =  PQexec(pgConn, SQL);
  if (fo_checkPQresult(pgConn, result, SQL, __FILE__, __LINE__))
  {
    PQfinish(pgConn);
    exit(-1);
  }
  count = PQntuples(result); /**! if the mimetype of the file_path is in table mimetype */
  CU_ASSERT_EQUAL(count, 1);
  PQclear(result);
}

/**
 * \brief testcases for function DBLoadGold
 */
CU_TestInfo testcases_DBCheckMime[] =
{
#if 0
#endif
{"Testing the function DBCheckMime:", testDBCheckMime},
  CU_TEST_INFO_NULL
};

