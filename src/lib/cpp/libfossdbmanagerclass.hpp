/*
Author: Johannes Najjar, Cedric Bodet, Andreas Wuerl, Daniele Fognini
Copyright (C) 2014, Siemens AG

This program is free software; you can redistribute it and/or modify it under the terms of the GNU General Public License version 2 as published by the Free Software Foundation.

This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.

You should have received a copy of the GNU General Public License along with this program; if not, write to the Free Software Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
*/

#ifndef LIBFOSSDBMANAGERCLASS_HPP_
#define LIBFOSSDBMANAGERCLASS_HPP_

extern "C" {
#include "libfossdbmanager.h"
}

#include <cstdarg>
#include <vector>
#include <string>

#include "uniquePtr.hpp"

class QueryResult;

class DbManager {
public :
  DbManager(int* argc, char** argv);
  DbManager(fo_dbManager* dbManager);
  DbManager(DbManager& dbManager) = delete;
  DbManager operator=(const DbManager&) = delete;
  ~DbManager();

  PGconn* getConnection() const;
  DbManager* spawn() const;

  fo_dbManager* getStruct_dbManager() const;
  bool tableExists(const char* tableName) const;
  bool sequenceExists(const char* name) const;
  bool begin() const;
  bool commit() const;
  bool rollback() const;

  QueryResult queryPrintf(const char* queryFormat, ...) const;
  QueryResult execPrepared(fo_dbManager_PreparedStatement* stmt, ...) const;

private:
  fo_dbManager* _dbManager;
};

class PGresultDeleter {
public:
  void operator()(PGresult* p) {
    PQclear(p);
  }
};

class QueryResult {
  friend class DbManager;

public:
  bool isFailed() const;
  int getRowCount() const;
  std::vector<std::string> getRow(int i) const;
  template<typename T> std::vector<T> getSimpleResults(int columnN, T (functionP)(const char*));

  QueryResult(QueryResult && queryResult);
  operator bool() const;

private:
  QueryResult(PGresult* ptr);
  unptr::unique_ptr<PGresult, PGresultDeleter> ptr;
};

template<typename T> std::vector<T> QueryResult::getSimpleResults(int columnN, T (functionP)(const char*)) {
  std::vector<T> result;
  PGresult* r = ptr.get();

  if (columnN<PQnfields(r)) {
    for (int i=0; i<getRowCount(); i++) {
      result.push_back(functionP(PQgetvalue(r, i, columnN)));
    }
  }

  return result;
}

#endif /* LIBFOSSDBMANAGERCLASS_HPP_ */
