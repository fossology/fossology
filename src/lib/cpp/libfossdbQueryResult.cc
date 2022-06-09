/*
 Author: Daniele Fognini
 SPDX-FileCopyrightText: © 2014-2015 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

#include "libfossdbQueryResult.hpp"

#include <string>

using namespace fo;

/**
 * \file
 * \brief Wrapper for DB result
 */

/**
 * Constructor for QueryResult
 * @param pgResult Postgres query result object
 */
QueryResult::QueryResult(PGresult* pgResult) : ptr(unptr::unique_ptr<PGresult, PGresultDeleter>(pgResult)) {
};

/**
 * \brief Check if the query failed
 *
 * Check if the query is failed by checking the Postgres object for NULL
 * \return True if failed, false on success.
 */
bool QueryResult::isFailed() const {
  return ptr.get() == NULL;
}

/**
 * Check the row count in the query result
 * \return Row count if result exists, -1 otherwise
 */
int QueryResult::getRowCount() const {
  if (ptr) {
    return PQntuples(ptr.get());
  }

  return -1;
}

/**
 * Get all columns from required row as a string vector
 * \param i The row to be fetched
 * \return String vector with each column as new element
 */
std::vector<std::string> QueryResult::getRow(int i) const {
  std::vector<std::string> result;

  if (ptr) {
    PGresult* r = ptr.get();

    if (i >= 0 && i < getRowCount()) {
      for (int j = 0; j < PQnfields(r); j++) {
        result.push_back(std::string(PQgetvalue(r, i, j)));
      }
    }
  }

  return result;
}
