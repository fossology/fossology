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

/**
* @file test_libfossagent.c
* @brief unit tests for the libfossagent.
*/

/* includes for files that will be tested */
#include <libfossdb.h>

/* library includes */
#include <string.h>
#include <stdio.h>
#include <unistd.h>

/* cunit includes */
#include <CUnit/CUnit.h>

#ifndef COMMIT_HASH
#define COMMIT_HASH "COMMIT_HASH Unknown"
#endif

extern char* dbConf;

/**
* @brief fo_tableExists() tests:
*   - Check for an existing table
*   - Check for table that does not exist
*   - Check for a non table entities (sequence, constraint, ...)
* @return void
*/
void test_fo_tableExists()
{
  PGconn* pgConn;
  int nonexistant_table;
  int existing_table;
  char* DBConfFile = dbConf;
  char* ErrorBuf;

  pgConn = fo_dbconnect(DBConfFile, &ErrorBuf);

  CU_ASSERT_PTR_NOT_NULL(pgConn);

  nonexistant_table = fo_tableExists(pgConn, "nonexistanttable");
  CU_ASSERT_FALSE(nonexistant_table);

  PGresult* result = PQexec(pgConn, "CREATE table exists()");
  CU_ASSERT_PTR_NOT_NULL_FATAL(result);
  CU_ASSERT_FALSE_FATAL(fo_checkPQcommand(pgConn, result, "create", __FILE__, __LINE__));

  existing_table = fo_tableExists(pgConn, "exists");
  CU_ASSERT_TRUE(existing_table);

  PQfinish(pgConn);
  return;
}


/* ************************************************************************** */
/* *** cunit test info ****************************************************** */
/* ************************************************************************** */
CU_TestInfo libfossdb_testcases[] =
  {
    {"fo_tableExists()", test_fo_tableExists},
    CU_TEST_INFO_NULL
  };
