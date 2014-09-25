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
  {"ct_pk", "bigint", "PRIMARY KEY DEFAULT nextval('copyright_ct_pk_seq'::regclass)"}, //TODO abstract name
  {"agent_fk", "bigint", "NOT NULL"},
  {"pfile_fk", "bigint", "NOT NULL"},
  {"content", "text", ""},
  {"hash", "text", ""},
  {"type", "text", ""}, // TODO do we need this constrain?  "CHECK (type in ('statement', 'email', 'url'))"},
  {"copy_startbyte", "integer", ""},
  {"copy_endbyte", "integer", ""},
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

#define CHECK_OR_RETURN_FALSE(query) \
  do {\
    if (!(query)) {\
      return false;\
    }\
  } while(0)
  CHECK_OR_RETURN_FALSE(dbManager->queryPrintf("CREATE SEQUENCE copyright_ct_pk_seq"  //TODO abstract name
                                         " START WITH 1"
                                         " INCREMENT BY 1"
                                         " NO MAXVALUE"
                                         " NO MINVALUE"
                                         " CACHE 1"));

  CHECK_OR_RETURN_FALSE(dbManager->queryPrintf("CREATE table %s(%s)", tableName, getColumnCreationString()));

  CHECK_OR_RETURN_FALSE(dbManager->queryPrintf(
   "CREATE INDEX copyright_agent_fk_index"  //TODO abstract name
   " ON copyright"  //TODO abstract name
   " USING BTREE (agent_fk)"  //TODO abstract name
  ));

  CHECK_OR_RETURN_FALSE(dbManager->queryPrintf(
   "CREATE INDEX copyright_pfile_fk_index"  //TODO abstract name
   " ON copyright"  //TODO abstract name
   " USING BTREE (pfile_fk)"  //TODO abstract name
  ));

  CHECK_OR_RETURN_FALSE(dbManager->queryPrintf(
    "ALTER TABLE ONLY copyright"  //TODO abstract name
    " ADD CONSTRAINT agent_fk"  //TODO abstract name
    " FOREIGN KEY (agent_fk)"  //TODO abstract name
    " REFERENCES agent(agent_pk) ON DELETE CASCADE" //TODO abstract name
  ));

  CHECK_OR_RETURN_FALSE(dbManager->queryPrintf(
    "ALTER TABLE ONLY copyright" //TODO abstract name
    " ADD CONSTRAINT pfile_fk" //TODO abstract name
    " FOREIGN KEY (pfile_fk)" //TODO abstract name
    " REFERENCES pfile(pfile_pk) ON DELETE CASCADE" //TODO abstract name
  ));

  return true;
}

bool checkTables(DbManager* dbManager) {
  if (dbManager->tableExists(tableName)) {
    CHECK_OR_RETURN_FALSE(dbManager->queryPrintf("SELECT %s FROM %s", getColumnListString(), tableName));
  } else {
    return false;
  }

  return true;
}

std::vector<long> queryFileIdsForUpload(DbManager* dbManager, int agentId, int uploadId) {
  QueryResult queryResult = dbManager->queryPrintf(
    "SELECT pfile_pk"
    " FROM ("
    "  SELECT distinct(pfile_fk) AS PF"
    "  FROM uploadtree"
    "   WHERE upload_fk = %d and (ufile_mode&x'3C000000'::int)=0"
    " ) AS SS "
    "left outer join copyright on (PF = pfile_fk and agent_fk = %d) "
    "inner join pfile on (PF = pfile_pk) "
    "WHERE ct_pk IS null or agent_fk <> %d",
    uploadId,
    agentId,
    agentId
  );

  return queryResult.getSimpleResults<long>(0, atol);
}

bool insertNoResultInDatabase(DbManager* dbManager, long agentId, long pFileId) {
  return dbManager->execPrepared(
    fo_dbManager_PrepareStamement(
      dbManager->getStruct_dbManager(),
      "insertNoResultInDatabase",
      "INSERT INTO copyright(agent_fk, pfile_fk)"// TODO abstract
      " VALUES($1,$2)",
      long, long
    ),
    agentId, pFileId
  );
}

bool insertInDatabase(DbManager* dbManager, DatabaseEntry& entry) {
  return dbManager->execPrepared(
    fo_dbManager_PrepareStamement(
      dbManager->getStruct_dbManager(),
      "insertInDatabase",
      "INSERT INTO copyright(agent_fk, pfile_fk, content, hash, type, copy_startbyte, copy_endbyte)" // TODO abstract
      " VALUES($1,$2,$3,$4,$5,$6,$7)",
      long, long, char*, char*, char*, int, int
    ),
    entry.agent_fk, entry.pfile_fk,
    entry.content.c_str(),
    entry.hash.c_str(),
    entry.type.c_str(),
    entry.copy_startbyte, entry.copy_endbyte
  );
}