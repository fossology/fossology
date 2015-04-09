/*
Copyright (C) 2014, Siemens AG
Author: Daniele Fognini

This program is free software; you can redistribute it and/or modify it under the terms of the GNU General Public License version 2 as published by the Free Software Foundation.

This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.

You should have received a copy of the GNU General Public License along with this program; if not, write to the Free Software Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
*/

#include "libfossdbQueryResult.hpp"

#include <string>

using namespace fo;

QueryResult::QueryResult(PGresult* pgResult) : ptr(unptr::unique_ptr<PGresult, PGresultDeleter>(pgResult))
{
};

QueryResult::QueryResult(QueryResult&& other) : ptr(std::move(other.ptr))
{
};

bool QueryResult::isFailed() const
{
  return ptr.get() == NULL;
}

QueryResult::operator bool() const
{
  return !isFailed();
}

int QueryResult::getRowCount() const
{
  if (ptr)
  {
    return PQntuples(ptr.get());
  }

  return -1;
}

std::vector<std::string> QueryResult::getRow(int i) const
{
  std::vector<std::string> result;
  PGresult* r = ptr.get();

  if (i >= 0 && i < getRowCount())
  {
    for (int j = 0; j < PQnfields(r); j++)
    {
      result.push_back(std::string(PQgetvalue(r, i, j)));
    }
  }

  return result;
}