/*
 Author: Johannes Najjar, Cedric Bodet, Andreas Wuerl, Daniele Fognini
 SPDX-FileCopyrightText: © 2014 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

#include "libfossdbmanagerclass.hpp"

extern "C" {
#include "libfossscheduler.h"
#include "libfossagent.h"
}

/**
 * \file
 * \brief DB wrapper for agents
 */

using namespace fo;

/**
 * \brief Get the C wrapper for DB manager
 * \param argc
 * \param argv
 * \return C wrapper for DbManager
 */
static fo_dbManager* doConnect(int* argc, char** argv) {
  fo_dbManager* _dbManager;
  fo_scheduler_connect_dbMan(argc, argv, &_dbManager);
  return _dbManager;
}

/**
 * \brief Get a shared pointer for DB manager
 * \param p
 * \return Shared DB manager pointer
 */
static inline unptr::shared_ptr<fo_dbManager> makeShared(fo_dbManager * p)
{
  return unptr::shared_ptr<fo_dbManager>(p, DbManagerStructDeleter());
}

/**
 * \brief Constructor for DbManager
 *
 * Store a shared pointer for DB Manager
 */
DbManager::DbManager(int* argc, char** argv) :
  dbManager(makeShared(doConnect(argc, argv)))
{
}

/**
 * \overload fo::DbManager::DbManager(fo_dbManager* _dbManager)
 */
DbManager::DbManager(fo_dbManager* _dbManager) :
  dbManager(makeShared(_dbManager))
{
}

/**
 * Get bare DB connection object
 * \return Get the native connection object
 */
PGconn* DbManager::getConnection() const
{
  return fo_dbManager_getWrappedConnection(getStruct_dbManager());
}

/**
 * Fork a new DB connection
 * \return New DbManager object with new DB connection
 * \sa fo_dbManager_fork()
 */
DbManager DbManager::spawn() const
{
  return DbManager(fo_dbManager_fork(getStruct_dbManager()));
}

/**
 * Get the C wrapper for DB manager
 * \return C wrapper for DB manager
 */
fo_dbManager* DbManager::getStruct_dbManager() const
{
  return dbManager.get();
}

/**
 * Check if a table exists in Database
 * \param tableName Table to check
 * \return True if table exists, false otherwise
 * \sa fo_dbManager_tableExists()
 */
bool DbManager::tableExists(const char* tableName) const
{
  return fo_dbManager_tableExists(getStruct_dbManager(), tableName) != 0;
}

/**
 * Check if a sequence exists in Database
 * \param name Sequence to check
 * \return True if sequence exists, false otherwise
 * \sa fo_dbManager_exists()
 */
bool DbManager::sequenceExists(const char* name) const
{
  return fo_dbManager_exists(getStruct_dbManager(), "sequence", name) != 0;
}

/**
 * BEGIN a transaction block
 * \return True on success, false otherwise
 * \sa fo_dbManager_begin()
 */
bool DbManager::begin() const
{
  return fo_dbManager_begin(getStruct_dbManager()) != 0;
}

/**
 * COMMIT a transaction block
 * \return True on success, false otherwise
 * \sa fo_dbManager_commit()
 */
bool DbManager::commit() const
{
  return fo_dbManager_commit(getStruct_dbManager()) != 0;
}

/**
 * ROLLBACK a transaction block
 * \return True on success, false otherwise
 * \sa fo_dbManager_rollback()
 */
bool DbManager::rollback() const
{
  return fo_dbManager_rollback(getStruct_dbManager()) != 0;
}

/**
 * \brief Execute a query in printf format
 *
 * This function can execute a query using the printf format (`%s`, `%d`, etc.
 * placeholder in queryFormat).
 * \param queryFormat Printf styled string format
 * \return QueryResult
 * \sa fo_dbManager_Exec_printf()
 */
QueryResult DbManager::queryPrintf(const char* queryFormat, ...) const
{
  va_list args;
  va_start(args, queryFormat);
  char* queryString = g_strdup_vprintf(queryFormat, args);
  va_end(args);

  QueryResult result(fo_dbManager_Exec_printf(getStruct_dbManager(), queryString));

  g_free(queryString);
  return result;
}

/**
 * \brief Execute a prepared statement with new parameters.
 * \param stmt Pointer to the prepared statement
 * \return QueryResult
 * \sa fo_dbManager_ExecPreparedv()
 */
QueryResult DbManager::execPrepared(fo_dbManager_PreparedStatement* stmt, ...) const
{
  va_list args;
  va_start(args, stmt);
  PGresult* pgResult = fo_dbManager_ExecPreparedv(stmt, args);
  va_end(args);

  return QueryResult(pgResult);
}

/**
 * Set the ignore warning flag for connection
 * \param b True to ignore waring
 * \sa fo_dbManager_ignoreWarnings()
 */
void DbManager::ignoreWarnings(bool b) const
{
  fo_dbManager_ignoreWarnings(getStruct_dbManager(), b);
}


