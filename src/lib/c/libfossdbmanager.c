/*
 Author: Daniele Fognini
 SPDX-FileCopyrightText: © 2014-2015 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

#include <glib.h>
#include <stdio.h>
#include <stdlib.h>
#include <stdarg.h>
#include <string.h>
#include <ctype.h>
#include <libfossdbmanager.h>
#include <libfossdb.h>

/** Parameter for queries */
typedef struct
{
  int type;   ///< Type of parameter, check buildStringArray() for more
  char* name; ///< Name of the parameter
  char* fmt;  ///< Printf format string for the parameter
} param;

/** Prepared statements */
struct fo_dbmanager_preparedstatement
{
  fo_dbManager* dbManager;  ///< DB manager
  param* params;  ///< Query parameters
  char* name;     ///< Name of the prepared statement
  int paramc;     ///< Number of paramenters
};

/** Database manager object */
struct fo_dbmanager
{
  PGconn* dbConnection; ///< Postgres database connection object
  GHashTable* cachedPrepared; ///< Hash table of prepared statements
  char* dbConf;         ///< DB conf file location
  FILE* logFile;        ///< FOSSology log file pointer
  int ignoreWarns;      ///< Set to ignore warnings from logging
};

/** Print the log in logfile or stdout */
#define LOG(level, str, ...) \
  do {\
    FILE* logFile = dbManager->logFile; \
    if (logFile) \
      fprintf(logFile, level ": " str, __VA_ARGS__); \
    else \
      printf(level ": " str, __VA_ARGS__); \
  } while(0)

/** Macro to log error */
#define LOG_ERROR(str, ...) LOG("ERROR", str, __VA_ARGS__)
/** Macro to log fatal error */
#define LOG_FATAL(str, ...) LOG("FATAL", str, __VA_ARGS__)

#ifdef DEBUG
/** Macro to log debug message */
#define LOG_DEBUG(str, ...) LOG("DEBUG", str, __VA_ARGS__)
#else
#define LOG_DEBUG(str, ...) \
  do {\
  } while(0)
#endif

/**
 * \brief Free prepared statement
 *
 * The function is used in initializing hash table.
 * \param ptr Pointer for fo_dbManager_PreparedStatement object
 */
static void cachedPrepared_free(gpointer ptr)
{
  fo_dbManager_PreparedStatement* stmt = ptr;
  free(stmt->name);
  free(stmt->params);
  free(stmt);
}

/**
 * \brief Receive notice/warning from Postgres and print in log
 * \param arg DB manager
 * \param res Result from Postgres
 */
static void noticeReceiver(void* arg, const PGresult* res) {
  fo_dbManager* dbManager = arg;
  char* message = PQresultErrorMessage(res);

  if (!dbManager->ignoreWarns)
    LOG("NOTICE", "%s", message);
};

/**
 * \brief Create new fo_dbManager object with conf file location
 * \param dbConnection  DB connection object
 * \param dbConf        Conf file location
 * \return The new DB manager object with conf file location
 * \sa fo_dbManager_new()
 */
fo_dbManager* fo_dbManager_new_withConf(PGconn* dbConnection, const char* dbConf)
{
  fo_dbManager* dbManager = fo_dbManager_new(dbConnection);
  dbManager->dbConf = g_strdup(dbConf);
  return dbManager;
}

/**
 * \brief Create and initialize new fo_dbManager object
 * \param dbConnection  DB connection object
 * \return New DB manager object
 */
fo_dbManager* fo_dbManager_new(PGconn* dbConnection)
{
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
  result->ignoreWarns = 0;

  PQsetNoticeReceiver(dbConnection, noticeReceiver, result);

  return result;
}

/**
 * \brief Creates a copy of the given dbManager with a new dedicated database
 * connection.
 *
 * Any statement prepared with the originating instance can be normally
 * executed with the previous connection, statements prepared with the new
 * instance will be completely independent (they are connection-local). The new
 * instance has the default logging attached and not share the log of the
 * originating instance, if needed set it with fo_dbManager_setLogFile().
 *
 * \b Example
 * \code
 * dbManager0 = fo_dbManager_new_withConf(dbConnection, "DB.conf");
 * statement1 = fo_dbManager_PrepareStamement(dbManager0, "a", "SELECT * FROM a");
 *
 * dbManager1 = fo_dbManager_fork(dbManager0);
 * statement2 = fo_dbManager_PrepareStamement(dbManager1, "a", "SELECT * FROM a");
 *
 * dbManager2 = fo_dbManager_fork(dbManager1);
 * \endcode
 * \param dbManager Existing DB manager
 * \return New DB manager forked from existing manager, else log a fatal error.
 * \sa fo_dbManager_new_withConf()
 */
fo_dbManager* fo_dbManager_fork(fo_dbManager* dbManager)
{
  fo_dbManager* result = NULL;
  char* error = NULL;
  PGconn* newDbConnection = fo_dbconnect(dbManager->dbConf, &error);
  if (newDbConnection)
  {
    result = fo_dbManager_new_withConf(newDbConnection, dbManager->dbConf);
  } else
  {
    LOG_FATAL("Can not open connection\n%s\nWhile forking dbManager using config: '%s'\n",
      error, dbManager->dbConf);
    free(error);
  }
  return result;
}

/**
 * \brief Set the log file pointer for a given DB manager
 * \param dbManager   DB manager to be updated
 * \param logFileName Log file location
 * \return 0 on success, 1 on failure
 */
int fo_dbManager_setLogFile(fo_dbManager* dbManager, const char* logFileName)
{
  if (dbManager->logFile)
    fclose(dbManager->logFile);

  if (logFileName)
  {
    dbManager->logFile = fopen(logFileName, "a");
    return dbManager->logFile != NULL;
  } else
  {
    dbManager->logFile = NULL;
    return 1;
  }
}

/**
 * \brief Set the ignoreWarns for a fo_dbManager
 * \param dbManager DB manager to be updated
 * \param ignoreWarns New value
 */
void fo_dbManager_ignoreWarnings(fo_dbManager* dbManager, int ignoreWarns)
{
  dbManager->ignoreWarns = ignoreWarns;
}

/**
 * \brief Get the wrapped Postgres connection object from fo_dbManager
 * \param dbManager DB manager with the connection object
 * \return The connection object wrapped inside the manager
 */
PGconn* fo_dbManager_getWrappedConnection(fo_dbManager* dbManager)
{
  return dbManager->dbConnection;
}

/**
 * \brief Un-allocate the memory from a DB manager
 *
 * The function applies following actions on the manager before calling free
 * -# Unref the cached table
 * -# Free the DB conf file location
 * -# Close the log file FP
 * \param dbManager The DB manager to be free-ed
 */
void fo_dbManager_free(fo_dbManager* dbManager)
{
  g_hash_table_unref(dbManager->cachedPrepared);
  if (dbManager->dbConf)
    free(dbManager->dbConf);
  if (dbManager->logFile)
    fclose(dbManager->logFile);
  free(dbManager);
}

/**
 * \brief Finish a connection on fo_dbManager
 *
 * -# Call PQfinish()
 * -# Free the DB manager using fo_dbManager_free()
 * \param dbManager DB manager
 */
void fo_dbManager_finish(fo_dbManager* dbManager)
{
  PQfinish(dbManager->dbConnection);
  fo_dbManager_free(dbManager);
}

/**
 * \brief Print an array as a JSON string
 *
 * The function prints the array in `{[index]='value', ...}` format
 * \param parameters Array of strings
 * \param count      Length of the array
 * \return JSON styled C string in the mentioned format
 */
static char* array_print(char** parameters, int count)
{
  GString* resultCreator = g_string_new("{");
  int i;
  for (i = 0; i < count; i++)
  {
    if (i > 0)
      g_string_append(resultCreator, ", ");
    g_string_append_printf(resultCreator, "[%d]='%s'", i, parameters[i]);
  }
  g_string_append(resultCreator, "}");
  return g_string_free(resultCreator, FALSE);
}

/**
 * \brief Free an array of strings
 * \param parameters Array of strings
 * \param count      Length of the array
 */
static void array_free(char** parameters, int count)
{
  int i;
  for (i = 0; i < count; i++)
    free(parameters[i]);
  free(parameters);
}

/**
 * NULL terminated array of param supported in query parameters
 * \note Remember to keep these synchronized in buildStringArray()
 */
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
#undef ADDSUPPORTED
  {0, NULL, NULL},
};

/**
 * \brief Create an array of strings from an array of param
 * \param paramCount  Length of params array
 * \param params      Array of param objects
 * \param vars        Corresponding value to objects in params array
 * \return Array of string with param value
 */
static inline char** buildStringArray(int paramCount, param* params, va_list vars)
{
  char** result = malloc(sizeof(char*) * paramCount);
  int i;
  for (i = 0; i < paramCount; i++)
  {
    param currentParamDesc = params[i];
    int type = currentParamDesc.type;
    char* format = currentParamDesc.fmt;
    switch (type)
    {
#define ADDCASE(n, type) \
      case n:\
        { \
          type t = va_arg(vars, type);\
          result[i] = g_strdup_printf(format, t);\
        }\
        break;
      ADDCASE(0, long)
      ADDCASE(1, int)
      ADDCASE(2, char*)
      ADDCASE(3, size_t)
      ADDCASE(4, unsigned)
      ADDCASE(5, unsigned int)
      ADDCASE(6, unsigned long)
#undef ADDCASE
      default:
        printf("internal error on typeid=%d\n", type);
        array_free(result, i - 1);
        return NULL;
    }
  }

  return result;
}

/**
 * \brief Print a prepared statement as a JSON string
 *
 * Prints the string in
 * \code
 * { name: '<statement_name>', parameterTypes: [
 *   [<index>]={<param_name>,<format>}],
 *   ...
 * ]}
 * \endcode
 * \param preparedStatement The prepared statement to be printed
 * \return JSON styled C string in the mentioned format
 */
char* fo_dbManager_printStatement(fo_dbManager_PreparedStatement* preparedStatement)
{
  GString* resultCreator = g_string_new("");
  g_string_append_printf(resultCreator,
    "{ name: '%s', parameterTypes: [",
    preparedStatement->name);
  int i;
  for (i = 0; i < preparedStatement->paramc; i++)
  {
    param current = preparedStatement->params[i];
    if (i > 0) g_string_append(resultCreator, ", ");
    g_string_append_printf(resultCreator,
      "[%d]={%s, %s}",
      i, current.name, current.fmt);
  }
  g_string_append_printf(resultCreator, "]}");
  return g_string_free(resultCreator, FALSE);
}

/**
 * \brief BEGIN a transaction block in Postgres
 * \param dbManager DB manager in use
 * \return 1 on success;\n
 * 0 on failure;
 */
int fo_dbManager_begin(fo_dbManager* dbManager)
{
  int result = 0;
  PGresult* queryResult = fo_dbManager_Exec_printf(dbManager, "BEGIN");
  if (queryResult)
  {
    result = 1;
    PQclear(queryResult);
  }
  return result;
}

/**
 * \brief COMMIT a transaction block in Postgres
 * \param dbManager DB manager in use
 * \return 1 on success;\n
 * 0 on failure;
 */
int fo_dbManager_commit(fo_dbManager* dbManager)
{
  int result = 0;
  PGresult* queryResult = fo_dbManager_Exec_printf(dbManager, "COMMIT");
  if (queryResult)
  {
    result = 1;
    PQclear(queryResult);
  }
  return result;
}

/**
 * \brief ROLLBACK a transaction block in Postgres
 * \param dbManager DB manager in use
 * \return 1 on success;\n
 * 0 on failure;
 */
int fo_dbManager_rollback(fo_dbManager* dbManager)
{
  int result = 0;
  PGresult* queryResult = fo_dbManager_Exec_printf(dbManager, "ROLLBACK");
  if (queryResult)
  {
    result = 1;
    PQclear(queryResult);
  }
  return result;
}

/**
 * \brief Check if a table exists in Database
 * \param dbManager DB manager to use
 * \param tableName Table in question
 * \return 1 on success;
 * 0 on failure;
 * \sa fo_dbManager_exists()
 */
int fo_dbManager_tableExists(fo_dbManager* dbManager, const char* tableName)
{
  return fo_dbManager_exists(dbManager, "table", tableName);
}

/**
 * \brief Check if a given type with the given name exists in Database
 * \param dbManager DB manager to use
 * \param type      Type to be checked (table|column|view|etc...)
 * \param tableName Table in question
 * \return 1 on success;
 * 0 on failure;
 * \sa fo_dbManager_exists()
 */
int fo_dbManager_exists(fo_dbManager* dbManager, const char* type, const char* name)
{
  int result = 0;

  char* escapedName = fo_dbManager_StringEscape(dbManager, name);

  if (escapedName)
  {
    PGresult* queryResult = fo_dbManager_Exec_printf(
      dbManager,
      "select count(*) from information_schema.%ss where %s_catalog='%s' and %s_name='%s'",
      type, type,
      PQdb(dbManager->dbConnection),
      type,
      escapedName
    );

    if (queryResult)
    {
      if (PQntuples(queryResult) == 1)
      {
        if (atol(PQgetvalue(queryResult, 0, 0)) == 1)
        {
          result = 1;
        }
      }
      PQclear(queryResult);
    }
    free(escapedName);
  }

  return result;
}

/**
 * \brief Execute a SQL query in a printf format
 *
 * \b Example
 * \code
 * char* aString = fo_dbManager_StringEscape(dbManager, "a it's=");
 *
 * if (aString)
 *     PGresult* result = fo_dbManager_Exec_printf(
 *                            dbManager,
 *                            "SELECT * FROM %s WHERE a='%s%d'",
 *                            "table_one", aString, 3
 *                        );
 * \endcode
 * \param dbManager             DB manager to use
 * \param sqlQueryStringFormat  Query format like printf
 * \return PGreult object on success;\n
 * NULL on error (also writes to log);
 */
PGresult* fo_dbManager_Exec_printf(fo_dbManager* dbManager, const char* sqlQueryStringFormat, ...)
{
  char* sqlQueryString;
  PGconn* dbConnection = dbManager->dbConnection;

  va_list argptr;
  va_start(argptr, sqlQueryStringFormat);
  sqlQueryString = g_strdup_vprintf(sqlQueryStringFormat, argptr);
  va_end(argptr);
  if (sqlQueryString == NULL)
  {
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
  if (PQresultStatus(result) == PGRES_FATAL_ERROR)
  {
    LOG_ERROR("%sOn: %s\n", PQresultErrorMessage(result), sqlQueryString);
    g_free(sqlQueryString);
    PQclear(result);
    return NULL;
  }
  g_free(sqlQueryString);

  return result;
}

/**
 * \brief Escape strings to prevent injections
 *
 * Escapes using PQescapeStringConn()
 * \param dbManager DB manager to use
 * \param string    String to be escaped
 * \return Escaped string on success; NULL otherwise
 */
char* fo_dbManager_StringEscape(fo_dbManager* dbManager, const char* string)
{
  size_t length = strlen(string);
  char* dest = malloc(2 * length + 1);

  int err;
  PQescapeStringConn(dbManager->dbConnection, dest, string, length, &err);
  if (err == 0)
  {
    return dest;
  } else
  {
    free(dest);
    return NULL;
  }
}

/**
 * \brief Execute a prepared statement
 * \param preparedStatement Prepared statement
 * \return Result on success; NULL otherwise
 * \sa fo_dbManager_ExecPreparedv()
 */
PGresult* fo_dbManager_ExecPrepared(fo_dbManager_PreparedStatement* preparedStatement, ...)
{
  if (!preparedStatement)
  {
    return NULL;
  }
  va_list vars;
  va_start(vars, preparedStatement);
  PGresult* result = fo_dbManager_ExecPreparedv(preparedStatement, vars);
  va_end(vars);

  return result;
}

/**
 * \brief Execute a prepared statement
 * \param preparedStatement Prepared statement
 * \param args              Values for the parameter placeholders
 * \return Result on success; NULL otherwise
 * \sa fo_dbManager_ExecPrepared()
 */
PGresult* fo_dbManager_ExecPreparedv(fo_dbManager_PreparedStatement* preparedStatement, va_list args)
{
  if (!preparedStatement)
  {
    return NULL;
  }

  char** parameters = buildStringArray(preparedStatement->paramc, preparedStatement->params, args);

  fo_dbManager* dbManager = preparedStatement->dbManager;
  PGconn* dbConnection = dbManager->dbConnection;

#ifdef DEBUG
  char* printedStatement = fo_dbManager_printStatement(preparedStatement);
  char* params = array_print(parameters, preparedStatement->paramc);
  LOG_DEBUG("Exec prepared '%s' with params '%s'\n",
            printedStatement,
            params);
  g_free(printedStatement);
  g_free(params);
#endif
  PGresult* result = PQexecPrepared(dbConnection,
    preparedStatement->name,
    preparedStatement->paramc,
    (const char* const*) parameters,
    NULL,
    NULL,
    0);

  if (!result)
  {
    char* printedStatement = fo_dbManager_printStatement(preparedStatement);
    char* params = array_print(parameters, preparedStatement->paramc);
    LOG_FATAL("%sExecuting prepared '%s' with params %s\n",
      PQerrorMessage(dbConnection),
      printedStatement,
      params);
    g_free(printedStatement);
    g_free(params);
  } else if (PQresultStatus(result) == PGRES_FATAL_ERROR)
  {
    char* printedStatement = fo_dbManager_printStatement(preparedStatement);
    char* params = array_print(parameters, preparedStatement->paramc);
    LOG_ERROR("%sExecuting prepared '%s' with params %s\n",
      PQresultErrorMessage(result),
      printedStatement,
      params);
    g_free(printedStatement);
    g_free(params);

    PQclear(result);
    result = NULL;
  }

  array_free(parameters, preparedStatement->paramc);

  return result;
}

/**
 * \brief Compare two strings ignoring consecutive spaces in b
 * \param a       First string
 * \param b       Second string
 * \param bLength Length of second string
 * \return 0 if strings don't match
 */
static inline int parseParamStr_equals(const char* a, const char* b, size_t bLength)
{
  const char* ptrA = a;
  const char* ptrB = b;
  size_t lenB = 0;

  while (*ptrA && lenB < bLength)
  {
    if (isspace(*ptrA))
    {
      if (!isspace(*ptrB))
        return 0;
      while (isspace(*ptrB) && lenB < bLength)
      {
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

/**
 * \brief Get the required param object in dest based on type
 * \param [in]type    Type of parameter
 * \param [in]length  Length of type string
 * \param [out]dest   The required param object
 * \return 1 on success;\n
 * 0 otherwise
 */
static inline int parseParamStr_set(const char* type, size_t length, param* dest)
{
  param* ptr = supported;
  while (ptr->fmt)
  {
    if (parseParamStr_equals(ptr->name, type, length))
    {
      *dest = *ptr;
      return 1;
    }
    ptr++;
  }
  return 0;
}

/**
 * \brief Get a list of params from a required CSV
 * \param paramtypes  CSV of required params
 * \param [out]params Array of required params
 * \return 0 on error, 1 on success
 * \sa parseParamStr_set()
 */
int fo_dbManager_parseParamStr(const char* paramtypes, GArray** params)
{
  *params = g_array_new(TRUE, FALSE, sizeof(param));
  GArray* paramsG = *params;

  const char* ptr = paramtypes;
  size_t currentLength = 0;
  const char* currentStart;
  const char* nextStart = ptr;
  int success = 1;
  while (*ptr)
  {
    // eat all starting whitespace
    while (*ptr && (isspace(*ptr)))
      ++ptr;
    currentStart = ptr;
    currentLength = 0;
    // go till the next comma
    while (*ptr && *ptr != ',')
    {
      ++currentLength;
      ++ptr;
    }
    nextStart = ptr;

    // if this token in empty we are done
    if (ptr == currentStart)
      break;

    --ptr;
    while (ptr != currentStart && isspace(*ptr))
    {
      --currentLength;
      --ptr;
    }

    // we found a real token: add it
    {
      param next;
      if (parseParamStr_set(currentStart, currentLength, &next))
      {
        g_array_append_val(paramsG, next);
      } else
      {
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

/**
 * \brief Initialize prepared statement from a CSV of parameters type required
 * \param statement   Statement to the initialized
 * \param paramtypes  CSV of parameters type required
 * \return 0 on error, 1 on success
 * \sa fo_dbManager_parseParamStr()
 */
static inline int parseParamStr(fo_dbManager_PreparedStatement* statement, const char* paramtypes)
{
  GArray* paramsG;
  int success = fo_dbManager_parseParamStr(paramtypes, &paramsG);

  statement->paramc = paramsG->len;
  statement->params = (param*) g_array_free(paramsG, FALSE);
  return success;
}

/**
 * \brief Create a prepared statement
 * \param dbManager  DB manager to use
 * \param name       Name of the prepared statement
 * \param query      Query to be perpared
 * \param paramtypes CSV list of parameter types
 * \return Prepared statement on success;\n
 * NULL otherwise;
 * \sa parseParamStr()
 */
fo_dbManager_PreparedStatement* fo_dbManager_PrepareStamement_str(
  fo_dbManager* dbManager, const char* name, const char* query, const char* paramtypes
)
{
  GHashTable* cachedPrepared = dbManager->cachedPrepared;
  fo_dbManager_PreparedStatement* cached = g_hash_table_lookup(cachedPrepared, name);

  if (cached)
  {
    LOG_DEBUG("returning cached statement '%s'\n", cached->name);
    return cached;
  }

  fo_dbManager_PreparedStatement* result = malloc(sizeof(fo_dbManager_PreparedStatement));

  PGconn* dbConnection = dbManager->dbConnection;

  result->dbManager = dbManager;
  result->name = g_strdup(name);

  int failure = 0;
  if (parseParamStr(result, paramtypes))
  {
    PGresult* prepareResult = PQprepare(dbConnection, result->name, query, 0, NULL);

    if (!prepareResult)
    {
      char* printedStatement = fo_dbManager_printStatement(result);
      LOG_FATAL("%sPreparing of '%s' AS '%s'\n",
        PQerrorMessage(dbConnection),
        printedStatement,
        query);
      free(printedStatement);
      failure = 1;
    } else
    {
      if (PQresultStatus(prepareResult) != PGRES_COMMAND_OK)
      {
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
  } else
  {
    LOG_FATAL("dbManager could not comprehend parameter types '%s'\n"
      "Trying to prepare '%s' as '%s'\n", paramtypes, name, query);
    failure = 1;
  }

  if (failure)
  {
    cachedPrepared_free(result);
    result = NULL;
  } else
  {
    g_hash_table_insert(cachedPrepared, result->name, result);
  }

  return result;
}
