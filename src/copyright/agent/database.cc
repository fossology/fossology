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

const CopyrightDatabaseHandler::ColumnDef CopyrightDatabaseHandler::columns[] =
{
    {"ct_pk", "bigint", "PRIMARY KEY DEFAULT nextval('copyright_ct_pk_seq'::regclass)"},
    {"agent_fk", "bigint", "NOT NULL"},
    {"pfile_fk", "bigint", "NOT NULL"},
    {"content", "text", ""},
    {"hash", "text", ""},
    {"type", "text", ""}, //TODO removed constrain: "CHECK (type in ('statement', 'email', 'url'))"},
    {"copy_startbyte", "integer", ""},
    {"copy_endbyte", "integer", ""},
  };

CopyrightDatabaseHandler::CopyrightDatabaseHandler(const char* aname)
{
  name = g_strdup(aname);

  insertInDatabaseQuery = g_strdup_printf(
    "INSERT INTO %s(agent_fk, pfile_fk, content, hash, type, copy_startbyte, copy_endbyte)"
    " VALUES($1,$2,$3,$4,$5,$6,$7)",
    name
  );

  insertNoResultInDatabaseQuery = g_strdup_printf(
    "INSERT INTO %s(agent_fk, pfile_fk) VALUES($1,$2)",
    name
  );
}

CopyrightDatabaseHandler::~CopyrightDatabaseHandler() {
  g_free(name);
  g_free(insertInDatabaseQuery);
  g_free(insertNoResultInDatabaseQuery);
}


const char* CopyrightDatabaseHandler::getColumnListString() {
  std::string result;
  for (size_t i=0; i<(sizeof(columns)/sizeof(ColumnDef)); ++i) {
    if (i!=0)
      result += ", ";
    result += columns[i].name;
  }
  return result.c_str();
}

const char* CopyrightDatabaseHandler::getColumnCreationString() {
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

bool CopyrightDatabaseHandler::createTables(DbManager* dbManager) {

#define RETURN_IF_FALSE(query) \
  do {\
    if (!(query)) {\
      return false;\
    }\
  } while(0)
  RETURN_IF_FALSE(dbManager->queryPrintf("CREATE SEQUENCE copyright_ct_pk_seq" // keep only one sequence
                                         " START WITH 1"
                                         " INCREMENT BY 1"
                                         " NO MAXVALUE"
                                         " NO MINVALUE"
                                         " CACHE 1", name));

  RETURN_IF_FALSE(dbManager->queryPrintf("CREATE table %s(%s)", name, getColumnCreationString()));

  RETURN_IF_FALSE(dbManager->queryPrintf(
   "CREATE INDEX %s_agent_fk_index"
   " ON %s"
   " USING BTREE (agent_fk)",
   name, name
  ));

  RETURN_IF_FALSE(dbManager->queryPrintf(
   "CREATE INDEX %s_pfile_fk_index"
   " ON %s"
   " USING BTREE (pfile_fk)",
   name, name
  ));

  RETURN_IF_FALSE(dbManager->queryPrintf(
    "ALTER TABLE ONLY %s"
    " ADD CONSTRAINT agent_fk"
    " FOREIGN KEY (agent_fk)"
    " REFERENCES agent(agent_pk) ON DELETE CASCADE",
    name
  ));

  RETURN_IF_FALSE(dbManager->queryPrintf(
    "ALTER TABLE ONLY %s"
    " ADD CONSTRAINT pfile_fk"
    " FOREIGN KEY (pfile_fk)"
    " REFERENCES pfile(pfile_pk) ON DELETE CASCADE",
    name
  ));

  return true;
}

bool CopyrightDatabaseHandler::checkTables(DbManager* dbManager) {
  if (dbManager->tableExists(name)) {
    RETURN_IF_FALSE(dbManager->queryPrintf("SELECT %s FROM %s", getColumnListString(), name));
  } else {
    return false;
  }

  return true;
}

std::vector<long> CopyrightDatabaseHandler::queryFileIdsForUpload(DbManager* dbManager, int agentId, int uploadId) {
  QueryResult queryResult = dbManager->queryPrintf(
    "SELECT pfile_pk"
    " FROM ("
    "  SELECT distinct(pfile_fk) AS PF"
    "  FROM uploadtree"
    "   WHERE upload_fk = %d and (ufile_mode&x'3C000000'::int)=0"
    " ) AS SS "
    "left outer join %s on (PF = pfile_fk and agent_fk = %d) "
    "inner join pfile on (PF = pfile_pk) "
    "WHERE ct_pk IS null or agent_fk <> %d",
    uploadId,
    name,
    agentId,
    agentId
  );

  return queryResult.getSimpleResults<long>(0, atol);
}

bool CopyrightDatabaseHandler::insertNoResultInDatabase(DbManager* dbManager, long agentId, long pFileId) {
  return dbManager->execPrepared(
    fo_dbManager_PrepareStamement(
      dbManager->getStruct_dbManager(),
      "insertNoResultInDatabase",
      insertNoResultInDatabaseQuery,
      long, long
    ),
    agentId, pFileId
  );
}

bool CopyrightDatabaseHandler::insertInDatabase(DbManager* dbManager, DatabaseEntry& entry) {
  return dbManager->execPrepared(
    fo_dbManager_PrepareStamement(
      dbManager->getStruct_dbManager(),
      "insertInDatabase",
      insertInDatabaseQuery,
      long, long, char*, char*, char*, int, int
    ),
    entry.agent_fk, entry.pfile_fk,
    entry.content.c_str(),
    entry.hash.c_str(),
    entry.type.c_str(),
    entry.copy_startbyte, entry.copy_endbyte
  );
}