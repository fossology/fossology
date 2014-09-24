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

#include <glib.h>
#include <stdio.h>
#include <stdlib.h>
#include <stdarg.h>
#include <string.h>
#include <ctype.h>
#include <libfossdbmanager.h>
#include <stdint.h>
#include <stddef.h>
#include <libfossdb.h>

typedef struct {
  int type;
  char* name;
  char* fmt;
} param;

struct fo_dbmanager_preparedstatement {
  fo_dbManager* dbManager;
  param* params;
  char* name;
  int paramc;
};

struct fo_dbmanager {
  PGconn* dbConnection;
  GHashTable* cachedPrepared;
  char* dbConf;
  FILE* logFile;
};

#define LOG(level, str, ...) \
  do {\
    FILE* logFile = dbManager->logFile; \
    if (logFile) \
      fprintf(logFile, level ": " str, __VA_ARGS__); \
    else \
      printf(level ": " str, __VA_ARGS__); \
  } while(0)

#define LOG_ERROR(str, ...) LOG("ERROR", str, __VA_ARGS__)
#define LOG_FATAL(str, ...) LOG("FATAL", str, __VA_ARGS__)

#ifdef DEBUG
#define LOG_DEBUG(str, ...) LOG("DEBUG", str, __VA_ARGS__)
#else
#define LOG_DEBUG(str, ...) \
  do {\
  } while(0)
#endif

static void cachedPrepared_free(gpointer ptr) {
  fo_dbManager_PreparedStatement* stmt = ptr;
  free(stmt->name);
  free(stmt->params);
  free(stmt);
}

fo_dbManager* fo_dbManager_new_withConf(PGconn* dbConnection, const char* dbConf){
  fo_dbManager* dbManager = fo_dbManager_new(dbConnection);
  dbManager->dbConf = g_strdup(dbConf);
  return dbManager;
}

fo_dbManager* fo_dbManager_new(PGconn* dbConnection){
  fo_dbManager* result = malloc(sizeof(fo_dbManager));

  result->cachedPrepared = g_hash_table_new_full(
    g_str_hash,
    g_str_equal,
    NULL, // the key is the same pointer as statement->name
    cachedPrepared_free
  );

  result->dbConnection = dbConnection;
  result->logFile = NULL;
  result->dbConf = NULL;
  return result;
}

fo_dbManager* fo_dbManager_fork(fo_dbManager* dbManager) {
  fo_dbManager* result = NULL;
  char* error = NULL;
  PGconn* newDbConnection = fo_dbconnect(dbManager->dbConf, &error);
  if (newDbConnection) {
    result = fo_dbManager_new_withConf(newDbConnection, dbManager->dbConf);
  } else {
    LOG_FATAL("Can not open connection\n%s\nWhile forking dbManager using config: '%s'\n",
              error, dbManager->dbConf);
    free(error);
  }
  return result;
}

int fo_dbManager_setLogFile(fo_dbManager* dbManager, const char* logFileName) {
  if (dbManager->logFile)
    fclose(dbManager->logFile);

  if (logFileName) {
    dbManager->logFile = fopen(logFileName, "a");
    return dbManager->logFile != NULL;
  } else {
    dbManager->logFile = NULL;
    return 1;
  }
}

PGconn* fo_dbManager_getWrappedConnection(fo_dbManager* dbManager) {
  return dbManager->dbConnection;
}

void fo_dbManager_free(fo_dbManager* dbManager) {
  g_hash_table_unref(dbManager->cachedPrepared);
  if (dbManager->dbConf)
    free(dbManager->dbConf);
  if (dbManager->logFile)
    fclose(dbManager->logFile);
  free(dbManager);
}

void fo_dbManager_finish(fo_dbManager* dbManager) {
  PQfinish(dbManager->dbConnection);
  fo_dbManager_free(dbManager);
}

static char* array_print(char** parameters, int count) {
  GString* resultCreator = g_string_new("{");
  int i;
  for (i=0; i<count; i++) {
    if (i>0)
      g_string_append(resultCreator, ", ");
    g_string_append_printf(resultCreator, "[%d]='%s'", i, parameters[i]);
  }
  g_string_append(resultCreator, "}");
  return g_string_free(resultCreator, FALSE);
}

static void array_free(char** parameters, int count) {
 int i;
  for (i=0; i<count; i++)
    free(parameters[i]);
  free(parameters);
}

param supported[] = {
#define ADDSUPPORTED(n, type, fmt) \
  {n, #type, fmt},
ADDSUPPORTED(0, long, "%ld")
ADDSUPPORTED(1, int, "%d")
ADDSUPPORTED(2, char*, "%s")
ADDSUPPORTED(3, size_t, "%zu")
ADDSUPPORTED(4, unsigned, "%u")
ADDSUPPORTED(5, unsigned int, "%u")
ADDSUPPORTED(6, unsigned long, "%lu")
/* remember to keep these synchronized in buildStringArray() */
#undef ADDSUPPORTED
  {0, NULL, NULL},
};

static inline char** buildStringArray(int paramCount, param* params, va_list vars) {
  char** result = malloc(sizeof(char*)*paramCount);
  int i;
  for (i=0; i<paramCount; i++){
    param currentParamDesc = params[i];
    int type = currentParamDesc.type;
    char* format = currentParamDesc.fmt;
    switch (type) {
#define ADDCASE(n,type) \
      case n:\
        { \
          type t = va_arg(vars, type);\
          result[i] = g_strdup_printf(format, t);\
        }\
        break;
      ADDCASE(0,long)
      ADDCASE(1,int)
      ADDCASE(2,char*)
      ADDCASE(3,size_t)
      ADDCASE(4,unsigned)
      ADDCASE(5,unsigned int)
      ADDCASE(6,unsigned long)
#undef ADDCASE
      default:
        printf("internal error on typeid=%d\n", type);
        array_free(result, i-1);
        return NULL;
    }
  }

  return result;
}

char* fo_dbManager_printStatement(fo_dbManager_PreparedStatement* preparedStatement) {
  GString* resultCreator = g_string_new("");
  g_string_append_printf(resultCreator,
                         "{ name: '%s', parameterTypes: [",
                         preparedStatement->name);
  int i;
  for (i=0; i<preparedStatement->paramc; i++) {
    param current = preparedStatement->params[i];
    if (i>0) g_string_append(resultCreator, ", ");
    g_string_append_printf(resultCreator,
                           "[%d]={%s, %s}",
                           i, current.name, current.fmt);
  }
  g_string_append_printf(resultCreator, "]}");
  return g_string_free(resultCreator, FALSE);
}

int fo_dbManager_tableExists(fo_dbManager* dbManager, const char* tableName) {
  int result = 0;

  char* escapedTableName = fo_dbManager_StringEscape(dbManager, tableName);

  if (escapedTableName) {
    PGresult* queryResult = fo_dbManager_Exec_printf(
      dbManager,
      "select count(*) from information_schema.tables where table_catalog='%s' and table_name='%s'",
      PQdb(dbManager->dbConnection),
      escapedTableName
    );

    if (queryResult) {
      if (PQntuples(queryResult)==1) {
        if (atol(PQgetvalue(queryResult, 0, 0)) == 1) {
          result = 1;
        }
      }
      PQclear(queryResult);
    }
    free(escapedTableName);
  }

  return result;
}

PGresult* fo_dbManager_Exec_printf(fo_dbManager* dbManager, const char* sqlQueryStringFormat, ...) {
  char* sqlQueryString;
  PGconn* dbConnection = dbManager->dbConnection;

  va_list argptr;
  va_start(argptr, sqlQueryStringFormat);
  sqlQueryString = g_strdup_vprintf(sqlQueryStringFormat, argptr);
  va_end(argptr);
  if (sqlQueryString == NULL) {
    return NULL;
  }

  PGresult* result = PQexec(dbConnection, sqlQueryString);

  if (!result)
  {
    LOG_FATAL("%sOn: %s\n", PQerrorMessage(dbConnection), sqlQueryString);
    g_free(sqlQueryString);
    PQclear(result);
    return NULL;
  }
  if (PQresultStatus(result) == PGRES_FATAL_ERROR) {
    LOG_ERROR("%sOn: %s\n", PQresultErrorMessage(result), sqlQueryString);
    g_free(sqlQueryString);
    PQclear(result);
    return NULL;
  }
  g_free(sqlQueryString);

  return result;
}

char* fo_dbManager_StringEscape(fo_dbManager* dbManager, const char* string) {
  size_t length = strlen(string);
  char* dest = malloc(2*length + 1);

  int err;
  PQescapeStringConn(dbManager->dbConnection, dest, string, length, &err);
  if (err==0) {
    return dest;
  } else {
    free(dest);
    return NULL;
  }
}

PGresult* fo_dbManager_ExecPrepared(fo_dbManager_PreparedStatement* preparedStatement, ...) {
  if (!preparedStatement) {
    return NULL;
  }

  fo_dbManager* dbManager = preparedStatement->dbManager;
  PGconn* dbConnection = dbManager->dbConnection;

  va_list vars;
  va_start(vars, preparedStatement);
  char** parameters = buildStringArray(preparedStatement->paramc, preparedStatement->params, vars);
  va_end(vars);

#ifdef DEBUG
  char* printedStatement = fo_dbManager_printStatement(preparedStatement);
  LOG_DEBUG("Exec prepared '%s' with params '%s'\n",
            printedStatement,
            array_print(parameters, preparedStatement->paramc));
  free(printedStatement);
#endif
  PGresult* result = PQexecPrepared(dbConnection,
                        preparedStatement->name,
                        preparedStatement->paramc,
                        (const char * const *) parameters,
                        NULL,
                        NULL,
                        0);

  if (!result) {
    char* printedStatement = fo_dbManager_printStatement(preparedStatement);
    LOG_FATAL("%sExecuting prepared '%s' with params %s\n",
              PQerrorMessage(dbConnection),
              printedStatement,
              array_print(parameters, preparedStatement->paramc));
    free(printedStatement);
  } else if (PQresultStatus(result) == PGRES_FATAL_ERROR) {
    char* printedStatement = fo_dbManager_printStatement(preparedStatement);
    LOG_ERROR("%sExecuting prepared '%s' with params %s\n",
              PQresultErrorMessage(result),
              printedStatement,
              array_print(parameters, preparedStatement->paramc));
    free(printedStatement);

    PQclear(result);
    result = NULL;
  }

  array_free(parameters, preparedStatement->paramc);

  return result;
}

static inline int parseParamStr_equals(const char* a, const char* b, size_t bLength ){
  const char* ptrA = a;
  const char* ptrB = b;
  size_t lenB = 0;

  while (*ptrA && lenB < bLength) {
    if (isspace(*ptrA)) {
      if(!isspace(*ptrB))
        return 0;
      while (isspace(*ptrB) && lenB < bLength) {
        ++ptrB;
        ++lenB;
      }
      ++ptrA;
    }
    if ((lenB == bLength) || (*ptrB != *ptrA))
      return 0;
    ++ptrA;
    ++ptrB;
    ++lenB;
  }

  return (!(*ptrA) && (lenB == bLength));
}

static inline int parseParamStr_set(const char* type, size_t length, param* dest) {
  param* ptr = supported;
  while (ptr->fmt) {
    if (parseParamStr_equals(ptr->name, type, length)) {
      *dest = *ptr;
      return 1;
    }
    ptr++;
  }
  return 0;
}

int fo_dbManager_parseParamStr(const char* paramtypes, GArray** params) {
  *params = g_array_new(TRUE, FALSE, sizeof(param));
  GArray* paramsG = *params;

  const char* ptr = paramtypes;
  size_t currentLength = 0;
  const char* currentStart;
  const char* nextStart = ptr;
  int success = 1;
  while (*ptr) {
    // eat all starting whitespace
    while (*ptr && (isspace(*ptr)))
      ++ptr;
    currentStart = ptr;
    currentLength = 0;
    // go till the next comma
    while (*ptr && *ptr != ',') {
      ++currentLength;
      ++ptr;
    }
    nextStart = ptr;

    // if this token in empty we are done
    if (ptr == currentStart)
      break;

    --ptr;
    while (ptr != currentStart && isspace(*ptr)) {
      --currentLength;
      --ptr;
    }

    // we found a real token: add it
    {
      param next;
      if (parseParamStr_set(currentStart, currentLength, &next)) {
        g_array_append_val(paramsG, next);
      } else {
        success = 0;
        break;
      }
    }

    // now go to the next token, nextStart is at the comma (or end)
    ptr = nextStart;
    if (*ptr)
      ptr++;
  }

  // parsing terminated too early
  if (*nextStart)
    success = 0;

  if (!success)
    g_array_set_size(paramsG, 0);

  return success;
}

static inline int parseParamStr(fo_dbManager_PreparedStatement* statement, const char* paramtypes) {
  GArray* paramsG;
  int success = fo_dbManager_parseParamStr(paramtypes, &paramsG);

  statement->paramc = paramsG->len;
  statement->params = (param*) g_array_free(paramsG, FALSE);
  return success;
}

fo_dbManager_PreparedStatement* fo_dbManager_PrepareStamement_str(
  fo_dbManager* dbManager, const char* name, const char* query, const char* paramtypes
){
  GHashTable* cachedPrepared = dbManager->cachedPrepared;
  fo_dbManager_PreparedStatement* cached = g_hash_table_lookup(cachedPrepared, name);

  if (cached) {
    LOG_DEBUG("returning cached statement '%s'\n", cached->name);
    return cached;
  }

  fo_dbManager_PreparedStatement* result = malloc(sizeof(fo_dbManager_PreparedStatement));

  PGconn* dbConnection = dbManager->dbConnection;

  result->dbManager = dbManager;
  result->name = g_strdup(name);

  int failure = 0;
  if (parseParamStr(result, paramtypes)) {
    PGresult* prepareResult = PQprepare(dbConnection, result->name, query, 0, NULL);

    if (!prepareResult) {
      char* printedStatement = fo_dbManager_printStatement(result);
      LOG_FATAL("%sPreparing of '%s' AS '%s'\n",
                PQerrorMessage(dbConnection),
                printedStatement,
                query);
      free(printedStatement);
      failure = 1;
    } else {
      if (PQresultStatus(prepareResult) != PGRES_COMMAND_OK) {
        char* printedStatement = fo_dbManager_printStatement(result);
        LOG_ERROR("%sPreparing of '%s' AS '%s'\n",
                  PQresultErrorMessage(prepareResult),
                  printedStatement,
                  query);
        free(printedStatement);
        failure = 1;
      }
      PQclear(prepareResult);
    }
  } else {
    LOG_FATAL("dbManager could not comprehend parameter types '%s'\n"
              "Trying to prepare '%s' as '%s'\n", paramtypes, name, query);
    failure = 1;
  }

  if (failure) {
    cachedPrepared_free(result);
    result = NULL;
  } else {
    g_hash_table_insert(cachedPrepared, result->name, result);
  }

  return result;
}