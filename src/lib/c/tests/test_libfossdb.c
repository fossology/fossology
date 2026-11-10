/*
 SPDX-FileCopyrightText: © 2011 Hewlett-Packard Development Company, L.P.

 SPDX-License-Identifier: GPL-2.0-only
*/

/**
* @file
* @brief Unit tests for the libfossagent.
*/

/* includes for files that will be tested */
#include <libfossdb.h>
#include <sqlCopy.h>

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
* @brief fo_tableExists() tests
* @test
* -# Check for an existing table
* -# Check for table that does not exist
* -# Check for a non table entities (sequence, constraint, ...)
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
/**
* @brief fo_sqlCopyExecute() tests
* @test
* -# Check fo_sqlCopyExecute with NULL pointer
* -# Check fo_sqlCopyExecute with empty buffer
* -# Check fo_sqlCopyExecute with valid copy and verify inserted records
* @return void
*/
void test_fo_sqlCopyExecute()
{
  /* Test NULL pointer handling */
  CU_ASSERT_EQUAL(fo_sqlCopyExecute(NULL), 0);

  char* DBConfFile = dbConf;
  char* ErrorBuf = NULL;
  PGconn* pgConn = fo_dbconnect(DBConfFile, &ErrorBuf);
  CU_ASSERT_PTR_NOT_NULL_FATAL(pgConn);

  const char* testTable = "test_sqlcopy_exec";
  char sql[512];
  snprintf(sql, sizeof(sql), "CREATE TABLE %s (id integer, val text)", testTable);
  PGresult* result = PQexec(pgConn, sql);
  CU_ASSERT_PTR_NOT_NULL_FATAL(result);
  CU_ASSERT_FALSE_FATAL(fo_checkPQcommand(pgConn, result, "create table", __FILE__, __LINE__));

  /* Test empty copy */
  psqlCopy_t pCopy = fo_sqlCopyCreate(pgConn, (char*)testTable, 1024, 2, "id", "val");
  CU_ASSERT_PTR_NOT_NULL_FATAL(pCopy);
  CU_ASSERT_EQUAL(fo_sqlCopyExecute(pCopy), 1);

  /* Test adding rows and executing copy */
  CU_ASSERT_EQUAL(fo_sqlCopyAdd(pCopy, "1\tfirst_value\n"), 1);
  CU_ASSERT_EQUAL(fo_sqlCopyAdd(pCopy, "2\tsecond_value\n"), 1);
  CU_ASSERT_EQUAL(fo_sqlCopyExecute(pCopy), 1);

  /* Verify records inserted */
  snprintf(sql, sizeof(sql), "SELECT count(*) FROM %s", testTable);
  result = PQexec(pgConn, sql);
  CU_ASSERT_PTR_NOT_NULL_FATAL(result);
  CU_ASSERT_EQUAL(atoi(PQgetvalue(result, 0, 0)), 2);
  PQclear(result);

  fo_sqlCopyDestroy(pCopy, 0);

  snprintf(sql, sizeof(sql), "DROP TABLE %s", testTable);
  result = PQexec(pgConn, sql);
  if (result)
  {
    PQclear(result);
  }

  PQfinish(pgConn);
}

/* ************************************************************************** */
/* *** cunit test info ****************************************************** */
/* ************************************************************************** */
CU_TestInfo libfossdb_testcases[] =
  {
    {"fo_tableExists()", test_fo_tableExists},
    {"fo_sqlCopyExecute()", test_fo_sqlCopyExecute},
    CU_TEST_INFO_NULL
  };
