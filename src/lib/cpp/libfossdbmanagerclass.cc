/*
Author: Johannes Najjar, Cedric Bodet, Andreas Wuerl, Daniele Fognini
Copyright (C) 2014, Siemens AG

This program is free software; you can redistribute it and/or modify it under the terms of the GNU General Public License version 2 as published by the Free Software Foundation.

This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.

You should have received a copy of the GNU General Public License along with this program; if not, write to the Free Software Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
*/

#include "libfossdbmanagerclass.hpp"

extern "C" {
#include "libfossscheduler.h"
#include "libfossagent.h"
}

using namespace fo;

DbManager::DbManager(int* argc, char** argv)
{
  fo_dbManager* _dbManager;
  fo_scheduler_connect_dbMan(argc, argv, &_dbManager);

  dbManager = unptr::shared_ptr<fo_dbManager>(_dbManager, DbManagerStructDeleter());
}

DbManager::DbManager(fo_dbManager* dbManager)
  : dbManager(unptr::shared_ptr<fo_dbManager>(dbManager, DbManagerStructDeleter()))
{
}

DbManager::DbManager(DbManager&& other) : dbManager(other.dbManager)
{
}

DbManager::DbManager(const DbManager& other) : dbManager(other.dbManager)
{
}

PGconn* DbManager::getConnection() const
{
  return fo_dbManager_getWrappedConnection(getStruct_dbManager());
}

DbManager::~DbManager()
{
}

DbManager DbManager::spawn() const
{
  return DbManager(fo_dbManager_fork(getStruct_dbManager()));
}

fo_dbManager* DbManager::getStruct_dbManager() const
{
  return dbManager.get();
}

bool DbManager::tableExists(const char* tableName) const
{
  return fo_dbManager_tableExists(getStruct_dbManager(), tableName) != 0;
}

bool DbManager::sequenceExists(const char* name) const
{
  return fo_dbManager_exists(getStruct_dbManager(), "sequence", name) != 0;
}

bool DbManager::begin() const
{
  return fo_dbManager_begin(getStruct_dbManager()) != 0;
}

bool DbManager::commit() const
{
  return fo_dbManager_commit(getStruct_dbManager()) != 0;
}

bool DbManager::rollback() const
{
  return fo_dbManager_rollback(getStruct_dbManager()) != 0;
}

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

QueryResult DbManager::execPrepared(fo_dbManager_PreparedStatement* stmt, ...) const
{
  va_list args;
  va_start(args, stmt);
  PGresult* pgResult = fo_dbManager_ExecPreparedv(stmt, args);
  va_end(args);

  return QueryResult(pgResult);
}

void DbManager::ignoreWarnings(bool b) const
{
  fo_dbManager_ignoreWarnings(getStruct_dbManager(), b);
}

std::string DbManager::queryUploadTreeTableName(int uploadId)
{
  return std::string(getUploadTreeTableName(getStruct_dbManager(), uploadId));
}


