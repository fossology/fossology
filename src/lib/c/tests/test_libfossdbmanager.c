/*
Author: Daniele Fognini
Copyright (C) 2014, Siemens AG

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
*/

#include <libfossdbmanager.h>

/* library includes */
#include <string.h>
#include <stdio.h>
#include <unistd.h>
#include <glib.h>
#include <libfossdb.h>
#include <fcntl.h>
#include <sys/stat.h>

/* cunit includes */
#include <CUnit/CUnit.h>

#define TESTABLE2(x, y) x "line" #y "_run"
#define TESTABLE1(s, i) TESTABLE2(s,i)
#define TESTTABLE TESTABLE1("test_db_manager_",__LINE__)

extern char* dbConf;

/* <0 unable to perform check
 * 0 does not exists
 * >0 exists
 */
int _tableExists(fo_dbManager* dbManager, char* tableName)
{
  PGresult* result = fo_dbManager_Exec_printf(
    dbManager,
    "SELECT EXISTS("
      "  SELECT * "
      "  FROM information_schema.tables "
      "  WHERE "
      "  table_name = '%s'"
      ")",
    tableName
  );

  if (!result)
    return -1;

  if (PQntuples(result) == 0)
  {
    PQclear(result);
    return -1;
  } else
  {
    int exists = (strcmp("t", PQgetvalue(result, 0, 0)) == 0);
    PQclear(result);
    return exists ? 1 : 0;
  }
}

int _assertFileLines(const char* fileName, const char* expectedContent, int id)
{
  int result = 0;
  int fd = open(fileName, O_RDONLY);
  if (fd)
  {
    char buffer[2048];
    ssize_t n = read(fd, buffer, sizeof(buffer) - 1);
    if (n >= 0)
    {
      buffer[n] = '\0';
      int patternMatch = g_pattern_match_simple(expectedContent, buffer);
      CU_ASSERT_TRUE(patternMatch);
#define SEP "\n------------------------\n"
      if (!patternMatch)
      {
        printf("error expecting log matching" SEP "%s" SEP "in file: '%s' for test #%d\n", expectedContent, fileName, id);
        printf(SEP "got" SEP "%.*s" SEP, MIN((int) sizeof(buffer), (int) n), buffer);
      } else
      {
        result = 1;
      }
#undef SEP
    } else
    {
      CU_FAIL("can not read file");
      printf("can not read '%s'\n", fileName);
    }
    close(fd);
  } else
  {
    CU_FAIL("can not open file");
    printf("can not read '%s'\n", fileName);
  }
  return result;
}

void _setLogFileForTest(fo_dbManager* dbManager, char* logFile)
{
  int logFd = open(logFile, O_WRONLY | O_CREAT | O_TRUNC, S_IRUSR | S_IWUSR);
  if (logFd > 0)
  {
    close(logFd);
    fo_dbManager_setLogFile(dbManager, logFile);
  } else
  {
    CU_FAIL("could not truncate logFile, can not test log output");
  }
}

int _getTestTable(fo_dbManager* dbManager, char** resultTableName, const char* columns)
{
  char* result = NULL;
  int i = 0;
  while ((!result) && (i < 100))
  {
    result = g_strdup_printf("%s_%d", *resultTableName, i++);
    int tableExists = _tableExists(dbManager, result);
    if (tableExists < 0)
    {
      free(result);
      return 0;
    } else if (tableExists > 0)
    {
      free(result);
      result = NULL;
    }
  }
  if (result)
  {
    PGresult* createResult = fo_dbManager_Exec_printf(
      dbManager, "CREATE TABLE %s (%s)", result, columns);
    if (createResult)
    {
      PQclear(createResult);
      *resultTableName = result;
      return 1;
    } else
    {
      free(result);
      return 0;
    }
  } else
  {
    return 0;
  }
}

void test_prepare()
{
  PGconn* pgConn;
  char* ErrorBuf;

  pgConn = fo_dbconnect(dbConf, &ErrorBuf);
  fo_dbManager* dbManager = fo_dbManager_new(pgConn);

  char* testTableName = TESTTABLE;
  if (_getTestTable(dbManager, &testTableName, "a int, b bigint, c varchar"))
  {
#define COUNT 3
    fo_dbManager_PreparedStatement* first = NULL;
    char* queryInsert = g_strdup_printf(
      "INSERT INTO %s (a,b,c) VALUES($1,$2,$3)", testTableName);
    char* querySelect = g_strdup_printf(
      "SELECT a,b,c FROM %s WHERE a = $1 AND b = $2 AND c = $3", testTableName);

    CU_ASSERT_TRUE(COUNT > 1);
    int j;
    for (j = 0; j < COUNT; j++)
    {
#undef COUNT
      fo_dbManager_PreparedStatement* stmtInsert = fo_dbManager_PrepareStamement(
        dbManager,
        "testprepare:insert",
        queryInsert,
        int, long, char*
      );
      /* check that prepared statements are cached inside dbManager */
      if (!first)
      {
        first = stmtInsert;
      } else
      {
        CU_ASSERT_EQUAL(first, stmtInsert);
      }

      CU_ASSERT_STRING_EQUAL(fo_dbManager_printStatement(stmtInsert),
        "{ name: 'testprepare:insert', parameterTypes: [[0]={int, %d}, [1]={long, %ld}, [2]={char*, %s}]}");

      fo_dbManager_PreparedStatement* stmtSelect = fo_dbManager_PrepareStamement(
        dbManager,
        "testprepare:select",
        querySelect,
        int, long, char*
      );

      CU_ASSERT_STRING_EQUAL(fo_dbManager_printStatement(stmtSelect),
        "{ name: 'testprepare:select', parameterTypes: [[0]={int, %d}, [1]={long, %ld}, [2]={char*, %s}]}");

      int a = j;
      long b = j * 4;
      char* c = g_strdup_printf("f%d", j);
      PGresult* insert = fo_dbManager_ExecPrepared(stmtInsert, a, b, c);
      if (insert)
      {
        PGresult* result = fo_dbManager_ExecPrepared(stmtSelect, a, b, c);
        if (result)
        {
          CU_ASSERT_EQUAL_FATAL(PQntuples(result), 1);
          CU_ASSERT_EQUAL(atoi(PQgetvalue(result, 0, 0)), a);
          CU_ASSERT_EQUAL(atol(PQgetvalue(result, 0, 1)), b);
          CU_ASSERT_STRING_EQUAL(PQgetvalue(result, 0, 2), c);

          PQclear(result);
        } else
        {
          CU_FAIL("select failed");
        }
        PQclear(insert);
      } else
      {
        CU_FAIL("insert failed");
      }
      free(c);
    }
    free(querySelect);
    free(queryInsert);

    fo_dbManager_Exec_printf(dbManager,
      "DROP TABLE %s",
      testTableName
    );
  } else
  {
    CU_FAIL("could not get test table");
  }

  fo_dbManager_free(dbManager);
  PQfinish(pgConn);
}

PGresult* _insertPrepared(fo_dbManager* dbManager, char* queryInsert, void** data, int a, long b)
{
  fo_dbManager_PreparedStatement* stmtInsert = fo_dbManager_PrepareStamement(
    dbManager,
    "testprepare:insertPerf",
    queryInsert,
    int, long
  );
  return fo_dbManager_ExecPrepared(stmtInsert, a, b);
}

PGresult* _insertPreparedCached(fo_dbManager* dbManager, char* queryInsert, void** data, int a, long b)
{
  if (*data)
  {
    return fo_dbManager_ExecPrepared(*data, a, b);
  } else
  {
    fo_dbManager_PreparedStatement* stmtInsert = fo_dbManager_PrepareStamement(
      dbManager,
      "testprepare:insertPerfCached",
      queryInsert,
      int, long
    );
    *data = stmtInsert;
    return fo_dbManager_ExecPrepared(stmtInsert, a, b);
  }
}

PGresult* _insertPrintf(fo_dbManager* dbManager, char* queryInsert, void** data, int a, long b)
{
  return fo_dbManager_Exec_printf(
    dbManager,
    queryInsert,
    a, b
  );
}

PGresult* _insertWithFunction(
  PGresult* (* insertFunction)(fo_dbManager*, char*, void**, int, long),
  fo_dbManager* dbManager,
  char* queryInsert,
  char* testTableName,
  int count)
{

  char* testTableNameVar = g_strdup(testTableName);

  PGresult* result = NULL;
  if (_getTestTable(dbManager, &testTableNameVar, "a int, b bigint, c timestamp DEFAULT CURRENT_TIMESTAMP"))
  {
    char* queryInsertWithTable = g_strdup_printf(queryInsert, testTableNameVar);
    void* data = NULL;

    int j;
    for (j = 0; j < count; j++)
    {
      int a = j;
      long b = j * 4;
      PGresult* insert = (*insertFunction)(dbManager, queryInsertWithTable, &data, a, b);
      if (insert)
      {
        PQclear(insert);
      } else
      {
        CU_FAIL("insert failed");
        break;
      }
    }

    result = fo_dbManager_Exec_printf(
      dbManager,
      "SELECT MAX(c) - MIN(c) FROM %s", testTableNameVar);

    fo_dbManager_Exec_printf(dbManager,
      "DROP TABLE %s",
      testTableNameVar
    );
  } else
  {
    CU_FAIL("could not get test table");
  }

  free(testTableNameVar);

  return result;
}

void test_perf()
{
  PGconn* pgConn;
  char* ErrorBuf;

  pgConn = fo_dbconnect(dbConf, &ErrorBuf);
  fo_dbManager* dbManager1 = fo_dbManager_new(pgConn);
  fo_dbManager* dbManager2 = fo_dbManager_new(pgConn);
  fo_dbManager* dbManager3 = fo_dbManager_new(pgConn);


  char* testTableNamePrepared = TESTTABLE;
  char* testTableNamePreparedCached = TESTTABLE;
  char* testTableNamePrintf = TESTTABLE;

  char* queryInsertPrepared = "INSERT INTO %s (a,b) VALUES($1,$2)";
  char* queryInsertPrintf = "INSERT INTO %s (a,b) VALUES(%d,%ld)";

  int i;
  for (i = 0; i < 21; i++)
  {
    PGresult* (* insertFunction)(fo_dbManager* dbManager, char* queryInsert, void** data, int a, long b);
    char* testTableName;
    char* queryInsert;
    int count = 500 + 500 * (i / 3) * (i / 3);
    char* name;
    fo_dbManager* dbManager;
    switch (i % 3)
    {
      case 1:
        insertFunction = _insertPrepared;
        testTableName = testTableNamePrepared;
        queryInsert = queryInsertPrepared;
        name = "prepared";
        dbManager = dbManager1;
        break;
      case 2:
        insertFunction = _insertPreparedCached;
        testTableName = testTableNamePreparedCached;
        queryInsert = queryInsertPrepared;
        name = "prepared (ext.cache)";
        dbManager = dbManager2;
        break;
      default:
        insertFunction = _insertPrintf;
        testTableName = testTableNamePrintf;
        queryInsert = queryInsertPrintf;
        name = "static";
        dbManager = dbManager3;
    }
    if (i % 3 == 0)
    {
      printf("timing inserts: %d\t", count);
    }
    printf("%s: ", name);

    PGresult* timeResult = _insertWithFunction(insertFunction, dbManager, queryInsert, testTableName, count);

    if (timeResult)
    {
      if (PQntuples(timeResult) > 0)
      {
        printf("%s", PQgetvalue(timeResult, 0, 0));
      } else
      {
        CU_FAIL("error in querying timings");
      }
      PQclear(timeResult);
    } else
    {
      CU_FAIL("error in querying timings");
    }
    if (i % 3 == 2)
      printf("\n");
    else
      printf("\t");
  }

  fo_dbManager_free(dbManager1);
  fo_dbManager_free(dbManager2);
  fo_dbManager_free(dbManager3);
  PQfinish(pgConn);
}

void test_simple_inject()
{
  PGconn* pgConn;
  char* ErrorBuf;

  pgConn = fo_dbconnect(dbConf, &ErrorBuf);
  fo_dbManager* dbManager = fo_dbManager_new(pgConn);

  char* testTableName = TESTTABLE;
  if (_getTestTable(dbManager, &testTableName, "a int, b bigint, c varchar"))
  {
    int a = 0;
    long b = 1;
    char* attemptInject = g_strdup_printf(
      "a'; INSERT INTO %s (a,b,c) VALUES (42,0,'2",
      testTableName
    );

    char* escaped = fo_dbManager_StringEscape(dbManager, attemptInject);
    PGresult* insert = fo_dbManager_Exec_printf(dbManager,
      "INSERT INTO %s (a,b,c) VALUES (%d,%ld,'%s')",
      testTableName,
      a, b, escaped
    );
    g_free(escaped);

    if (insert)
    {

      PGresult* select = fo_dbManager_Exec_printf(dbManager,
        "SELECT c FROM %s",
        testTableName
      );
      if (select)
      {
        int count = PQntuples(select);
        switch (count)
        {
          case 0:
          CU_FAIL("no result, but 1 expected");
            break;
          case 1:
          CU_ASSERT_EQUAL(
            0,
            strncmp(
              "a'; INSERT INTO",
              PQgetvalue(select, 0, 0),
              strlen("a'; INSERT INTO")
            )
          );
            break;
          default:
          CU_FAIL("could sql inject");
        }

        PQclear(select);
      } else
      {
        CU_FAIL("could not select");
      }

      PQclear(insert);
    } else
    {
      CU_FAIL("could not insert valid values");
    }

    fo_dbManager_Exec_printf(dbManager,
      "DROP TABLE %s",
      testTableName
    );
  } else
  {
    CU_FAIL("could not get test table");
  }

  fo_dbManager_free(dbManager);
  PQfinish(pgConn);
}

void test_fork_error()
{
  fo_dbManager* dbManager = fo_dbManager_new_withConf(NULL, "not a file.conf");
  char* logFile = "/tmp/" TESTTABLE;
  _setLogFileForTest(dbManager, logFile);

  fo_dbManager_fork(dbManager);

  fo_dbManager_free(dbManager);

  _assertFileLines(
    logFile,
    "FATAL: Can not open connection\n"
      "Database conf file: not a file.conf, No such file or directory\n"
      "While forking dbManager using config: 'not a file.conf'\n",
    0
  );
}

void test_fork()
{
  PGconn* pgConn;
  char* ErrorBuf;

  pgConn = fo_dbconnect(dbConf, &ErrorBuf);
  fo_dbManager* dbManager0 = fo_dbManager_new_withConf(pgConn, dbConf);

  char* testTableName = TESTTABLE;
  if (_getTestTable(dbManager0, &testTableName, "a int, b bigint, c varchar"))
  {
    fo_dbManager* dbManager1 = fo_dbManager_fork(dbManager0);

    CU_ASSERT_NOT_EQUAL(dbManager0, dbManager1);
    CU_ASSERT_PTR_NOT_NULL(fo_dbManager_getWrappedConnection(dbManager1));
    CU_ASSERT_NOT_EQUAL(fo_dbManager_getWrappedConnection(dbManager0),
      fo_dbManager_getWrappedConnection(dbManager1));

    char* queryInsert = g_strdup_printf(
      "INSERT INTO %s (a,b,c) VALUES($1,$2,$3)", testTableName);

    fo_dbManager_PreparedStatement* stmtInsert0 = fo_dbManager_PrepareStamement(
      dbManager0,
      "testfork",
      queryInsert,
      int, long, char*
    );

    if (dbManager1)
    {
      fo_dbManager_PreparedStatement* stmtInsert1 = fo_dbManager_PrepareStamement(
        dbManager1,
        "testfork",
        queryInsert,
        int, long, char*
      );
      CU_ASSERT_PTR_NOT_NULL(stmtInsert1);
      CU_ASSERT_NOT_EQUAL(stmtInsert0, stmtInsert1);

      int a = 1;
      long b = 2;
      char* c = "a";
      CU_ASSERT_PTR_NOT_NULL(fo_dbManager_ExecPrepared(
        stmtInsert0,
        a, b, c
      ));
      CU_ASSERT_PTR_NOT_NULL(fo_dbManager_ExecPrepared(
        stmtInsert1,
        a + 1, b, c
      ));

      PQfinish(fo_dbManager_getWrappedConnection(dbManager1));
      fo_dbManager_free(dbManager1);
    } else
    {
      CU_FAIL("coud not fork dbManager");
    }
    fo_dbManager_Exec_printf(dbManager0,
      "DROP TABLE %s",
      testTableName
    );
  } else
  {
    CU_FAIL("could not get test table");
  }

  fo_dbManager_free(dbManager0);
  PQfinish(pgConn);
}

void _test_wrongQueries_runner(char* (* test)(fo_dbManager**, const char*), int testNumber)
{
  PGconn* pgConn;
  char* ErrorBuf;

  pgConn = fo_dbconnect(dbConf, &ErrorBuf);
  fo_dbManager* dbManager = fo_dbManager_new_withConf(pgConn, dbConf);

  char* testTable = g_strdup_printf(TESTTABLE "runner_%d", testNumber);
  int gotTable = _getTestTable(dbManager, &testTable, "a int, b bigint");

  if (gotTable)
  {
    fo_dbManager* dbManagerTest = fo_dbManager_fork(dbManager);
    char* logFile = g_strdup_printf("./%s.log", testTable);
    _setLogFileForTest(dbManagerTest, logFile);

    char* expectedLog = (*test)(&dbManagerTest, testTable);

    if (dbManagerTest)
      fo_dbManager_finish(dbManagerTest);

    if (_assertFileLines(logFile, expectedLog, testNumber))
    {
      fo_dbManager_Exec_printf(dbManager, "DROP TABLE %s", testTable);
      unlink(logFile);
    }

    fo_dbManager_finish(dbManager);

    free(logFile);
    free(expectedLog);
    free(testTable);

  } else
  {
    CU_FAIL("could not get test table");
    printf("could not get test table" " for test %d\n", testNumber);
    fo_dbManager_finish(dbManager);
  }
}

char* _test_wrongQueries_SyntaxError0(fo_dbManager** dbManager, const char* testTableName)
{
  CU_ASSERT_PTR_NULL(fo_dbManager_Exec_printf(*dbManager,
    "CREATE TABLE 's' (id integer, 's' integer"));

  return g_strdup(
    "ERROR: * syntax error at or near \"'s'\"\n"
      "* CREATE TABLE 's' (id integer, 's' integer\n"
      "*^\n"
      "On: CREATE TABLE 's' (id integer, 's' integer\n"
  );
}


char* _test_wrongQueries_SyntaxError(fo_dbManager** dbManager, const char* testTableName)
{
  CU_ASSERT_PTR_NULL(fo_dbManager_Exec_printf(*dbManager,
    "INSERT INTO wrong (id,'%s') VALUES (%d,5)",
    "value",
    6));

  return g_strdup(
    "ERROR: * syntax error at or near \"'value'\"\n"
      "* INSERT INTO wrong (id,'value') VALUES (6,5)\n"
      "*^\n"
      "On: INSERT INTO wrong (id,'value') VALUES (6,5)\n"
  );
}

char* _test_wrongQueries_noConnectionToServer(fo_dbManager** dbManager, const char* testTableName)
{
  PQfinish(fo_dbManager_getWrappedConnection(*dbManager));
  CU_ASSERT_PTR_NULL(fo_dbManager_Exec_printf(*dbManager,
    "CREATE TABLE new_table_with_a_null_connection"));

  fo_dbManager_free(*dbManager);
  *dbManager = NULL;

  return g_strdup(
    "FATAL: no connection to the server\n"
      "On: CREATE TABLE new_table_with_a_null_connection\n"
  );
}

char* _test_wrongQueries_noConnectionToServerOnPrepare(fo_dbManager** dbManager, const char* testTableName)
{
  PQfinish(fo_dbManager_getWrappedConnection(*dbManager));

  char* query = g_strdup_printf("SELECT * FROM %s", testTableName);
  fo_dbManager_PreparedStatement* statement = fo_dbManager_PrepareStamement(
    *dbManager,
    "noConnPrepare",
    query
  );
  free(query);

  CU_ASSERT_PTR_NULL(statement);

  fo_dbManager_free(*dbManager);
  *dbManager = NULL;

  return g_strdup_printf(
    "FATAL: no connection to the server\n"
      "Preparing of '{ name: 'noConnPrepare', parameterTypes: []}' AS 'SELECT * FROM %s'\n",
    testTableName
  );
}

char* _test_wrongQueries_noConnectionToServerOnExecute(fo_dbManager** dbManager, const char* testTableName)
{
  char* query = g_strdup_printf("SELECT * FROM %s", testTableName);
  fo_dbManager_PreparedStatement* statement = fo_dbManager_PrepareStamement(
    *dbManager,
    "noConn",
    query
  );
  free(query);

  CU_ASSERT_PTR_NOT_NULL_FATAL(statement);

  PQfinish(fo_dbManager_getWrappedConnection(*dbManager));

  CU_ASSERT_PTR_NULL(fo_dbManager_ExecPrepared(statement));

  fo_dbManager_free(*dbManager);
  *dbManager = NULL;

  return g_strdup(
    "FATAL: no connection to the server\n"
      "Executing prepared '{ name: 'noConn', parameterTypes: []}' with params {}\n"
  );
}

char* _test_wrongQueries_noParametersFor1ParameterStmt(fo_dbManager** dbManager, const char* testTableName)
{
  char* query = g_strdup_printf("SELECT a FROM %s WHERE a=$1", testTableName);
  CU_ASSERT_PTR_NULL(fo_dbManager_ExecPrepared(
    fo_dbManager_PrepareStamement(
      *dbManager,
      "name2",
      query
    )
  ));
  free(query);

  return g_strdup(
    "ERROR: ERROR:  bind message supplies 0 parameters, but prepared statement \"name2\" requires 1\n"
      "Executing prepared '{ name: 'name2', parameterTypes: []}' with params {}\n"
  );
}

char* _test_wrongQueries_prepareWithNotExistingColumn(fo_dbManager** dbManager, const char* testTableName)
{
  char* query = g_strdup_printf("SELECT c FROM %s WHERE a=$1", testTableName);
  CU_ASSERT_PTR_NULL(fo_dbManager_PrepareStamement(
    *dbManager,
    "name",
    query
  ));
  free(query);

  return g_strdup_printf(
    "ERROR: * column \"c\" does not exist\n"
      "*SELECT c FROM %s WHERE *\n"
      "*^\n"
      "Preparing of '{ name: 'name', parameterTypes: []}' AS 'SELECT c FROM %s WHERE a=$1'\n",
    testTableName, testTableName
  );
}

char* _test_wrongQueries_2ParametersForNoParametersQuery(fo_dbManager** dbManager, const char* testTableName)
{
  char* query = g_strdup_printf("SELECT a FROM %s WHERE a=1", testTableName);
  CU_ASSERT_PTR_NULL(fo_dbManager_ExecPrepared(
    fo_dbManager_PrepareStamement(
      *dbManager,
      "name3",
      query,
    int, size_t
  ),
  (int) 5, (size_t) 6
  ));
  free(query);

  return g_strdup(
    "ERROR: ERROR:  bind message supplies 2 parameters, but prepared statement \"name3\" requires 0\n"
      "Executing prepared '{ name: 'name3', parameterTypes: [[0]={int, %d}, [1]={size_t, %zu}]}' with params {[0]='5', [1]='6'}\n"
  );
}

char* _test_wrongQueries_unparsableTypes(fo_dbManager** dbManager, const char* testTableName)
{
  CU_ASSERT_PTR_NULL(fo_dbManager_PrepareStamement(
    *dbManager,
    "name4",
    "irrelevant",
    int, ,long
  ));

  return g_strdup(
    "FATAL: dbManager could not comprehend parameter types 'int, ,long'\n"
      "Trying to prepare 'name4' as 'irrelevant'\n"
  );
}

char* _test_wrongQueries_transactions(fo_dbManager** dbManager, const char* testTableName)
{
  CU_ASSERT_TRUE(fo_dbManager_begin(*dbManager));
  CU_ASSERT_TRUE(fo_dbManager_begin(*dbManager));

  PQclear(fo_dbManager_Exec_printf(*dbManager, "INSERT INTO %s (a, b) VALUES (1,2)", testTableName));
  CU_ASSERT_TRUE(fo_dbManager_commit(*dbManager));

  CU_ASSERT_TRUE(fo_dbManager_begin(*dbManager));
  PQclear(fo_dbManager_Exec_printf(*dbManager, "INSERT INTO %s (a, b) VALUES (1,2)", testTableName));
  PQclear(fo_dbManager_Exec_printf(*dbManager, "INSERT INTO %s (a, c) VALUES (1,2)", testTableName));
  PQclear(fo_dbManager_Exec_printf(*dbManager, "INSERT INTO %s (a, b) VALUES (1,2)", testTableName));

  CU_ASSERT_TRUE(fo_dbManager_rollback(*dbManager));

  PGresult* res = fo_dbManager_Exec_printf(*dbManager, "SELECT * FROM %s", testTableName);

  CU_ASSERT_TRUE(PQntuples(res)==1);
  PQclear(res);

 return g_strdup_printf(
   "NOTICE: WARNING:  there is already a transaction in progress\n"
   "ERROR: ERROR:  column \"c\" of relation \"%s\" does not exist\n"
   "LINE 1: *\n"
   "*^\n"
   "On: INSERT INTO %s (a, c) VALUES (1,2)\n"
   "ERROR: ERROR:  current transaction is aborted, commands ignored until end of transaction block\n"
   "On: INSERT INTO %s (a, b) VALUES (1,2)\n", testTableName, testTableName, testTableName
 );
}

void test_wrongQueries()
{
  int testNumber = 0;
  _test_wrongQueries_runner(_test_wrongQueries_SyntaxError0, ++testNumber);
  _test_wrongQueries_runner(_test_wrongQueries_SyntaxError, ++testNumber);
  _test_wrongQueries_runner(_test_wrongQueries_noConnectionToServer, ++testNumber);
  _test_wrongQueries_runner(_test_wrongQueries_noConnectionToServerOnPrepare, ++testNumber);
  _test_wrongQueries_runner(_test_wrongQueries_noConnectionToServerOnExecute, ++testNumber);
  _test_wrongQueries_runner(_test_wrongQueries_prepareWithNotExistingColumn, ++testNumber);
  _test_wrongQueries_runner(_test_wrongQueries_noParametersFor1ParameterStmt, ++testNumber);
  _test_wrongQueries_runner(_test_wrongQueries_2ParametersForNoParametersQuery, ++testNumber);
  _test_wrongQueries_runner(_test_wrongQueries_unparsableTypes, ++testNumber);
  _test_wrongQueries_runner(_test_wrongQueries_transactions, ++testNumber);
}

void test_executeNull()
{
  CU_ASSERT_PTR_NULL(
    fo_dbManager_ExecPrepared(
      NULL
    )
  );
}

void test_stringEscape_corners()
{
  PGconn* pgConn;
  char* ErrorBuf;

  pgConn = fo_dbconnect(dbConf, &ErrorBuf);
  fo_dbManager* dbManager = fo_dbManager_new(pgConn);

  char* empty = fo_dbManager_StringEscape(dbManager, "");
  CU_ASSERT_PTR_NOT_NULL_FATAL(empty);
  CU_ASSERT_EQUAL(strlen(empty), 0);
  free(empty);

  char* onlyQuotes = fo_dbManager_StringEscape(dbManager, "''''''");
  CU_ASSERT_PTR_NOT_NULL_FATAL(onlyQuotes);
  CU_ASSERT_TRUE(strlen(onlyQuotes) > 0);
  free(onlyQuotes);

  fo_dbManager_free(dbManager);
  PQfinish(pgConn);
}

void test_parsing()
{
  GArray* result;

  CU_ASSERT_TRUE(fo_dbManager_parseParamStr("", &result));
  CU_ASSERT_EQUAL(result->len, 0);
  g_array_free(result, TRUE);

  CU_ASSERT_TRUE(fo_dbManager_parseParamStr(" ", &result));
  CU_ASSERT_EQUAL(result->len, 0);
  g_array_free(result, TRUE);

  CU_ASSERT_TRUE(fo_dbManager_parseParamStr("\n", &result));
  CU_ASSERT_EQUAL(result->len, 0);
  g_array_free(result, TRUE);

  CU_ASSERT_FALSE(fo_dbManager_parseParamStr(",", &result));
  CU_ASSERT_EQUAL(result->len, 0);
  g_array_free(result, TRUE);

  CU_ASSERT_TRUE(fo_dbManager_parseParamStr("int", &result));
  CU_ASSERT_EQUAL(result->len, 1);
  g_array_free(result, TRUE);

  CU_ASSERT_TRUE(fo_dbManager_parseParamStr("  int  ", &result));
  CU_ASSERT_EQUAL(result->len, 1);
  g_array_free(result, TRUE);

  CU_ASSERT_TRUE(fo_dbManager_parseParamStr("long, int\t ,unsigned\n\t  int ,long ", &result));
  CU_ASSERT_EQUAL(result->len, 4);
  g_array_free(result, TRUE);

  CU_ASSERT_TRUE(fo_dbManager_parseParamStr("unsigned int", &result));
  CU_ASSERT_EQUAL(result->len, 1);
  g_array_free(result, TRUE);

  CU_ASSERT_FALSE(fo_dbManager_parseParamStr("unsignedint", &result));
  CU_ASSERT_EQUAL(result->len, 0);
  g_array_free(result, TRUE);

  CU_ASSERT_FALSE(fo_dbManager_parseParamStr("int,", &result));
  CU_ASSERT_EQUAL(result->len, 0);
  g_array_free(result, TRUE);

  CU_ASSERT_FALSE(fo_dbManager_parseParamStr("int,,long", &result));
  CU_ASSERT_EQUAL(result->len, 0);
  g_array_free(result, TRUE);

  CU_ASSERT_FALSE(fo_dbManager_parseParamStr("int, ,long", &result));
  CU_ASSERT_EQUAL(result->len, 0);
  g_array_free(result, TRUE);

  CU_ASSERT_FALSE(fo_dbManager_parseParamStr("int a", &result));
  CU_ASSERT_EQUAL(result->len, 0);
  g_array_free(result, TRUE);

  CU_ASSERT_FALSE(fo_dbManager_parseParamStr("i", &result));
  CU_ASSERT_EQUAL(result->len, 0);
  g_array_free(result, TRUE);

  CU_ASSERT_FALSE(fo_dbManager_parseParamStr("in", &result));
  CU_ASSERT_EQUAL(result->len, 0);
  g_array_free(result, TRUE);

  CU_ASSERT_FALSE(fo_dbManager_parseParamStr("inta", &result));
  CU_ASSERT_EQUAL(result->len, 0);
  g_array_free(result, TRUE);

  CU_ASSERT_FALSE(fo_dbManager_parseParamStr("long, inta , int ,long", &result));
  CU_ASSERT_EQUAL(result->len, 0);
  g_array_free(result, TRUE);
}

/* ************************************************************************** */
/* *** cunit test info ****************************************************** */
/* ************************************************************************** */
CU_TestInfo libfossdbmanager_testcases[] =
  {
    {"prepare", test_prepare},
    {"simple injection", test_simple_inject},
    {"handling of wrong queries", test_wrongQueries},
    {"execute a null statement", test_executeNull},
    {"strange string escaping", test_stringEscape_corners},
    {"parsing types", test_parsing},
//    { "performance test", test_perf },
    {"fork dbManager", test_fork},
    {"fork dbManager without configuration", test_fork_error},
    CU_TEST_INFO_NULL
  };
