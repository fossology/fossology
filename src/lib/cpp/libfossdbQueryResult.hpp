/*
Copyright (C) 2014-2015, Siemens AG
Author: Daniele Fognini

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License version 2
as published by the Free Software Foundation.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
See the GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software Foundation,
Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
*/

#ifndef LIBFOSSDBQUERYRESULT_HPP_
#define LIBFOSSDBQUERYRESULT_HPP_

#include "libfossdbmanager.h"

#include "uniquePtr.hpp"

#include <vector>

/**
 * \file
 * \brief Wrapper for DB result
 */

namespace fo {
  /**
   * \class PGresultDeleter
   * \brief PGresult deleter (for shared pointer)
   */
  class PGresultDeleter {
  public:
    /**
     * Called by shared pointer destructor
     * @param d PGresult object to be deleted
     */
    void operator()(PGresult* p) {
      PQclear(p);
    }
  };

  /**
   * \class QueryResult
   * \brief Wrapper for DB result
   */
  class QueryResult {
    friend class DbManager;

    friend class AgentDatabaseHandler;

  private:
    QueryResult(PGresult* ptr);

  public:
    QueryResult(QueryResult&& o): ptr(std::move(o.ptr)) {};

    bool isFailed() const;

    /**
     * Check if the query result failed
     * \return True if failed, false on success.
     * \sa fo::QueryResult::isFailed()
     */
    operator bool() const {
      return !isFailed();
    };

    int getRowCount() const;

    std::vector<std::string> getRow(int i) const;

    template<typename T>
    std::vector<T> getSimpleResults(int columnN, T (functionP)(const char*)) const;

  private:
    unptr::unique_ptr <PGresult, PGresultDeleter> ptr;   ///< Unique pointer to the actual PGresult
  };

  /**
   * \brief Get vector of a single column from query result
   *
   * Get a single column's values as a vector of type T (defined by `functionP`
   * param). The values will be translated using the `functionP`.
   * \param columnN   Position of the required column
   * \param functionP Function to translate the string result in desired format
   * \return Vector with translated values of a single column
   */
  template<typename T>
  std::vector<T> QueryResult::getSimpleResults(int columnN, T (functionP)(const char*)) const {
    std::vector<T> result;

    if (ptr) {
      PGresult* r = ptr.get();

      if (columnN < PQnfields(r)) {
        for (int i = 0; i < getRowCount(); i++) {
          result.push_back(functionP(PQgetvalue(r, i, columnN)));
        }
      }
    }

    return result;
  }
}
#endif
