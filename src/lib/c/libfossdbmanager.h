/*
 Author: Daniele Fognini
 SPDX-FileCopyrightText: © 2014-2015 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

#ifndef LIBFOSSDBMANAGER_H
#define LIBFOSSDBMANAGER_H

#include <libpq-fe.h>
#include <stdarg.h>
#include <glib.h>

#ifdef __cplusplus
extern "C" {
#endif

typedef struct fo_dbmanager_preparedstatement fo_dbManager_PreparedStatement;
typedef struct fo_dbmanager fo_dbManager;

fo_dbManager* fo_dbManager_new(PGconn* dbConnection);
fo_dbManager* fo_dbManager_new_withConf(PGconn* dbConnection, const char* dbConf);
PGconn* fo_dbManager_getWrappedConnection(fo_dbManager* dbManager);
fo_dbManager* fo_dbManager_fork(fo_dbManager* dbManager);
void fo_dbManager_free(fo_dbManager* dbManager);
void fo_dbManager_finish(fo_dbManager* dbManager);
int fo_dbManager_setLogFile(fo_dbManager* dbManager, const char* logFileName);
void fo_dbManager_ignoreWarnings(fo_dbManager* dbManager, int ignoreWarns);
char* fo_dbManager_StringEscape(fo_dbManager* dbManager, const char* string);
int fo_dbManager_begin(fo_dbManager* dbManager);
int fo_dbManager_commit(fo_dbManager* dbManager);
int fo_dbManager_rollback(fo_dbManager* dbManager);
PGresult* fo_dbManager_Exec_printf(fo_dbManager* dbManager, const char* sqlQueryStringFormat, ...);

/*!
 * \fn fo_dbManager_PreparedStatement* fo_dbManager_PrepareStatement (fo_dbManager* dbManager, const char* name, const char* query, ...)
 *
 * \brief prepare a statement
 *
 * \param dbManager    an instance of DB manager to be used
 * \param name         name of the statement, it will uniquely identify the prepared statement
 *                     It will be used to avoid preparing the same statement twice
 * \param query        The SQL string for the query, with $1...$n as parameters
 * \param ...          The types of the C arguments that will be given as parameters
 *                     when the caller will execute the statement
 *
 * \return pointer to a prepared statement to be used in fo_dbManager_ExecPrepared() or NULL on failure.
 *         The returned pointer can be used multiple times and the same pointer will be returned
 *         by successive calls with the same #name
 *         This pointer MUST NOT be freed. Used memory will be given back on destruction of dbManager: see fo_dbManager_free()
 *
 * Example
 \code
   fo_dbManager_PreparedStatement* prepared = fo_dbManager_PrepareStamement(
                                                dbManager,
                                                "example",
                                                "SELECT * FROM table WHERE a = $1",
                                                int
                                              );

   PGresult* result;
   int i=1;
   for (i=0; i<10; i++) {
     result = fo_dbManager_ExecPrepared(prepared, i);
   }
 \endcode
 *
 */
#define fo_dbManager_PrepareStamement(dbManager, name, query, ...) \
fo_dbManager_PrepareStamement_str(dbManager, \
  name, \
  query, \
  #__VA_ARGS__\
)

fo_dbManager_PreparedStatement* fo_dbManager_PrepareStamement_str(fo_dbManager* dbManager, const char* name, const char* query, const char* paramtypes);

PGresult* fo_dbManager_ExecPrepared(fo_dbManager_PreparedStatement* preparedStatement, ...);
PGresult* fo_dbManager_ExecPreparedv(fo_dbManager_PreparedStatement* preparedStatement, va_list args);

int fo_dbManager_tableExists(fo_dbManager* dbManager, const char* tableName);
int fo_dbManager_exists(fo_dbManager* dbManager, const char* type, const char* name);

// visible for testing
int fo_dbManager_parseParamStr(const char* paramtypes, GArray** params);
char* fo_dbManager_printStatement(fo_dbManager_PreparedStatement* preparedStatement);

#ifdef __cplusplus
}
#endif

#endif  /* LIBFOSSDBMANAGER_H */
