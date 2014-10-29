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

#ifndef LIBFOSSDBMANAGER_H
#define LIBFOSSDBMANAGER_H

#include <libpq-fe.h>
#include <stdarg.h>
#include <glib.h>

typedef struct fo_dbmanager_preparedstatement fo_dbManager_PreparedStatement;
typedef struct fo_dbmanager fo_dbManager;

/*!
 * fo_dbManager_new()
 *
 * \brief creates a new instance of dbManager
 *
 * \param dbConnection           already opened connection to the database to be used in all queries mediated by the new instance
 *
 * \return new instance of dbManager
 *
 */
fo_dbManager* fo_dbManager_new(PGconn* dbConnection);

/*!
 * fo_dbManager_new_withConf()
 *
 * \brief creates a new instance of dbManager setting the database configuration file
 *
 * \param dbConnection    already opened connection to the database to be used in all queries mediated by the new instance
 * \param dbConf          filename of the configuration file
 *
 * \return new instance of dbManager
 *
 */
fo_dbManager* fo_dbManager_new_withConf(PGconn* dbConnection, const char* dbConf);

/*!
 * fo_dbManager_getWrappedConnection()
 *
 * \brief return the database connection wrapped inside this instance of dbManager
 *
 * \param dbManager    instance of dbManager
 *
 * \return database connection
 *
 */
PGconn* fo_dbManager_getWrappedConnection(fo_dbManager* dbManager);

/*!
 * fo_dbManager_fork()
 *
 * \brief creates a copy of the given dbManager with a new dedicated database connection.
 *        Any statement prepared with the originating instance can be normally executed with the previous connection,
 *        statements prepared with the new instance will be completely independent (they are connection-local).
 *        The new instance has the default logging attached and not share the log of the originating instance,
 *        if needed set it with fo_dbManager_setLogFile()
 *
 * \param dbManager  instance of dbManager to be copied, containing the configuration needed to open a new connection
 *
 * \return new instance of dbManager
 *
 * Example
 \code
   dbManager0 = fo_dbManager_new_withConf(dbConnection, "DB.conf");
   statement1 = fo_dbManager_PrepareStamement(dbManager0, "a", "SELECT * FROM a");

   dbManager1 = fo_dbManager_fork(dbManager0);
   statement2 = fo_dbManager_PrepareStamement(dbManager1, "a", "SELECT * FROM a");

   dbManager2 = fo_dbManager_fork(dbManager1);
 \endcode
 *
 */
fo_dbManager* fo_dbManager_fork(fo_dbManager* dbManager);

/*!
 * fo_dbManager_free()
 *
 * \brief frees memory used by the given dbManager.
 *        The connection to the database will be left open,
 *        but all the statements prepared though the dbManager instance will be freed
 *
 * \param dbManager    the instance to be freed
 *
 */
void fo_dbManager_free(fo_dbManager* dbManager);

/*!
 * fo_dbManager_finish()
 *
 * \brief closes the connection to the database and frees memory used by the given dbManager.
 *
 * \param dbManager    the instance to be freed
 *
 */
void fo_dbManager_finish(fo_dbManager* dbManager);

/*!
 * fo_dbManager_setLogFile()
 *
 * \brief sets the filename for logging. Messages after this call will be appended to #logFileName
 *
 * \param dbManager    the instance to which the log is attached
 * \param logFileName  the path to the log file to be used
 *
 */
int fo_dbManager_setLogFile(fo_dbManager* dbManager, const char* logFileName);
void fo_dbManager_ignoreWarnings(fo_dbManager* dbManager, int ignoreWarns);

/*!
 * fo_dbManager_StringEscape()
 *
 * \brief Escapes a string for use as a parameter in a query
 *
 * \param dbManager              an instance of DB manager to be used
 * \param sqlQueryStringFormat   string representing the query to be executed, with printf-style format specifiers
 * \param ...                    the parameters to be formatted
 *
 * \return query result or NULL on failure
 *
 * \sa for example see fo_dbManager_Exec_printf()
 *
 */
char* fo_dbManager_StringEscape(fo_dbManager* dbManager, const char* string);

/*!
 * fo_dbManager_begin()
 *
 * \brief begins a transaction
 *
 * \param dbManager  an instance of DB manager to be used
 *
 * \return boolean indicating success
 */
int fo_dbManager_begin(fo_dbManager* dbManager);

/*!
 * fo_dbManager_commit()
 *
 * \brief commits the transaction
 *
 * \param dbManager  an instance of DB manager to be used
 *
 * \return boolean indicating success
 */
int fo_dbManager_commit(fo_dbManager* dbManager);

/*!
 * fo_dbManager_rollback()
 *
 * \brief rolls back the transaction
 *
 * \param dbManager  an instance of DB manager to be used
 *
 * \return boolean indicating success
 */
int fo_dbManager_rollback(fo_dbManager* dbManager);

/*!
 * fo_dbManager_Exec_printf()
 *
 * \brief Executes a query
 *
 * \param dbManager              an instance of DB manager to be used
 * \param sqlQueryStringFormat   string representing the query to be executed, with printf-style format specifiers
 * \param ...                    the parameters to be formatted.
 *                               Strings should be escaped with fo_dbManager_StringEscape() before use
 *
 * \return query result or NULL on failure
 *
 * Example
 \code
   char* aString = fo_dbManager_StringEscape(dbManager, "a it's=");

   if (aString)
     PGresult* result = fo_dbManager_Exec_printf(
                          dbManager,
                          "SELECT * FROM %s WHERE a='%s%d'",
                          "table_one", aString, 3
                        );
 \endcode
 *
 */
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
/*!
 * \brief prepare a statement with types given as a string
 *
 * \sa don't use this directly: use the accompanying macro fo_dbManager_PrepareStamement()
 */
fo_dbManager_PreparedStatement* fo_dbManager_PrepareStamement_str(fo_dbManager* dbManager, const char* name, const char* query, const char* paramtypes);

/*!
 * fo_dbManager_ExecPrepared()
 *
 * \brief Executes a previously prepared statement
 *
 * \param preparedStatement    the statement to be executed (previously prepared by fo_dbManager_PrepareStamement())
 * \param ...                  the parameters to be binded for the query
 *
 * \return query result or NULL on failure
 */
PGresult* fo_dbManager_ExecPrepared(fo_dbManager_PreparedStatement* preparedStatement, ...);
PGresult* fo_dbManager_ExecPreparedv(fo_dbManager_PreparedStatement* preparedStatement, va_list args);

int fo_dbManager_tableExists(fo_dbManager* dbManager, const char* tableName);
int fo_dbManager_exists(fo_dbManager* dbManager, const char* type, const char* name);

// visible for testing
int fo_dbManager_parseParamStr(const char* paramtypes, GArray** params);
char* fo_dbManager_printStatement(fo_dbManager_PreparedStatement* preparedStatement);

#endif  /* LIBFOSSDBMANAGER_H */
