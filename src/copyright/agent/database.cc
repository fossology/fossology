/*
 * Copyright (C) 2014, Siemens AG
 * Author: Daniele Fognini
 *
 * This program is free software; you can redistribute it and/or modify it under the terms of the GNU General Public License version 2 as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along with this program; if not, write to the Free Software Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 */

#include "database.hpp"
#include <iostream> // TODO removeme

const static char* tableName = "copyright";

typedef struct {
  const char* name;
  const char* type;
  const char* creationFlags;
} ColumnDef;

ColumnDef columns[] = {
  {"a", "integer", ""},
  {"b", "bigint", ""}
};

const char* getColumnListString() {
  std::string result;
  for (size_t i=0; i<(sizeof(columns)/sizeof(ColumnDef)); ++i) {
    if (i!=0)
      result += ", ";
    result += columns[i].name;
  }
  return result.c_str();
}

const char* getColumnCreationString() {
  std::string result;
  for (size_t i=0; i< (sizeof(columns)/sizeof(ColumnDef)); ++i) {
    if (i!=0)
      result += ", ";
    result += columns[i].name;
    result += " ";
    result += columns[i].type;
    result += " ";
    result += columns[i].creationFlags;
  }
  return result.c_str();
}

bool createTables(DbManager* dbManager) {

#define CHECK_OR_RETURN(query) \
  do {\
    PGresult* queryResult = (query); \
    if ((queryResult)) {\
      PQclear((queryResult));\
    } else {\
      return false;\
    }\
  } while(0)

  CHECK_OR_RETURN(dbManager->queryPrintf("CREATE table %s(%s)", tableName, getColumnCreationString()));

  CHECK_OR_RETURN(dbManager->queryPrintf("CREATE SEQUENCE copyright_ct_pk_seq"
                                         " START WITH 1"
                                         " INCREMENT BY 1"
                                         " NO MAXVALUE"
                                         " NO MINVALUE"
                                         " CACHE 1"));

  return true;
}


bool checkTables(DbManager* dbManager) {
  if (dbManager->tableExists(tableName)) {
    CHECK_OR_RETURN(dbManager->queryPrintf("SELECT %s FROM %s", getColumnListString(), tableName));
  }

  return true;
}
