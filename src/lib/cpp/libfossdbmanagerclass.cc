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
}

DbManager::DbManager(int* argc, char** argv){
  fo_scheduler_connect_dbMan(argc,argv,&_dbManager);
};

DbManager::DbManager(fo_dbManager* __dbManager): _dbManager(__dbManager){

};

DbManager::~DbManager(){
  fo_dbManager_finish(_dbManager);
};

PGconn* DbManager::getConnection() const {
  return fo_dbManager_getWrappedConnection(_dbManager);
}


DbManager* DbManager::spawn() const {
  return new DbManager(fo_dbManager_fork(_dbManager));
}

fo_dbManager* DbManager::getStruct_dbManager() const {
  return _dbManager;
}

bool DbManager::tableExists(const char* tableName) const {
  return fo_dbManager_tableExists(_dbManager, tableName);
}

bool DbManager::begin() const {
  // TODO merge with new features and use fo_dbManager_begin()
  PGresult* queryResult = fo_dbManager_Exec_printf(getStruct_dbManager(), "BEGIN");

  if (queryResult) {
    PQclear(queryResult);
    return true;
  }

  return false;
}

bool DbManager::commit() const {
  // TODO merge with new features and use fo_dbManager_commit()
  PGresult* queryResult = fo_dbManager_Exec_printf(getStruct_dbManager(), "COMMIT");

  if (queryResult) {
    PQclear(queryResult);
    return true;
  }

  return false;
}

bool DbManager::rollback() const {
  // TODO merge with new features and create a fo_dbManager_rollback()
  PGresult* queryResult = fo_dbManager_Exec_printf(getStruct_dbManager(), "ROLLBACK");

  if (queryResult) {
    PQclear(queryResult);
    return true;
  }

  return false;
}

QueryResult DbManager::queryPrintf(const char* queryFormat, ...) const {
  va_list args;
  va_start(args, queryFormat);
  char* queryString = g_strdup_vprintf(queryFormat, args);
  va_end(args);

  return QueryResult(fo_dbManager_Exec_printf(_dbManager, queryString));
}

QueryResult DbManager::execPrepared(fo_dbManager_PreparedStatement* stmt, ...) const {
  va_list args;
  va_start(args, stmt);
  PGresult* pgResult = fo_dbManager_ExecPreparedv(stmt, args);
  va_end(args);

  return QueryResult(pgResult);
}

QueryResult::QueryResult(PGresult* pgResult): ptr(unptr::unique_ptr<PGresult, PGresultDeleter>(pgResult)) {};

QueryResult::QueryResult(QueryResult&& other) : ptr(std::move(other.ptr)) {};

bool QueryResult::isFailed() const {
  return ptr.get() == NULL;
}

QueryResult::operator bool() const {
  return !isFailed();
}

int QueryResult::getRowCount() const {
  if (ptr) {
    return PQntuples(ptr.get());
  }

  return -1;
}

std::vector< std::string > QueryResult::getRow(int i) const{
  std::vector< std::string > result;
  PGresult* r = ptr.get();

  if (i>=0 && i<getRowCount()) {
    for (int j=0; j<PQnfields(r); j++) {
      result.push_back(std::string(PQgetvalue(r, i, j)));
    }
  }

  return result;
}
