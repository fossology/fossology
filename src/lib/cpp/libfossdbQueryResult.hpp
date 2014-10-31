/*
Copyright (C) 2014, Siemens AG
Author: Daniele Fognini

This program is free software; you can redistribute it and/or modify it under the terms of the GNU General Public License version 2 as published by the Free Software Foundation.

This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.

You should have received a copy of the GNU General Public License along with this program; if not, write to the Free Software Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
*/

#ifndef LIBFOSSDBQUERYRESULT_HPP_
#define LIBFOSSDBQUERYRESULT_HPP_

extern "C" {
#include "libfossdbmanager.h"
}

#include "uniquePtr.hpp"

#include <vector>

namespace fo
{
  class PGresultDeleter
  {
  public:
    void operator ()(PGresult* p)
    {
      PQclear(p);
    }
  };

  class QueryResult
  {
    friend class DbManager;
    friend class AgentDatabaseHandler;

  private:
    QueryResult(PGresult* ptr);

  public:
    bool isFailed() const;
    int getRowCount() const;
    std::vector<std::string> getRow(int i) const;
    template <typename T>
    std::vector<T> getSimpleResults(int columnN, T (functionP)(const char*));

    QueryResult(QueryResult&& queryResult);
    operator bool() const;

  private:
    unptr::unique_ptr <PGresult, PGresultDeleter> ptr;
  };

  template <typename T>
  std::vector<T> QueryResult::getSimpleResults(int columnN, T (functionP)(const char*))
  {
    std::vector<T> result;
    PGresult* r = ptr.get();

    if (columnN < PQnfields(r))
    {
      for (int i = 0; i < getRowCount(); i++)
      {
        result.push_back(functionP(PQgetvalue(r, i, columnN)));
      }
    }

    return result;
  }
}
#endif