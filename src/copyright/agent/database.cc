/*
 * Copyright (C) 2014-2017, Siemens AG
 * Author: Daniele Fognini, Johannes Najjar
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License version 2
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 * See the GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software Foundation,
 * Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 */

#include "database.hpp"
#include "identity.hpp"

#include <iostream>
#include <libfossUtils.hpp>

using namespace fo;

#define RETURN_IF_FALSE(query) \
  do {\
    if (!(query)) {\
      return false;\
    }\
  } while(0)

DatabaseEntry::DatabaseEntry() :
        agent_fk(0),
        pfile_fk(0),
        content(""),
        hash(""),
        type(""),
        copy_startbyte(0),
        copy_endbyte(0)
{
};


CopyrightDatabaseHandler CopyrightDatabaseHandler::spawn() const
{
  DbManager spawnedDbMan(dbManager.spawn());
  return CopyrightDatabaseHandler(spawnedDbMan);
}


std::string CopyrightDatabaseHandler::getColumnListString(const CopyrightDatabaseHandler::ColumnDef in[], size_t size) const
{
  std::string result;
  for (size_t i = 0; i < size; ++i)
  {
    if (i != 0)
      result += ", ";
    result += in[i].name;
  }
  return result;
}

std::string CopyrightDatabaseHandler::getColumnCreationString(const CopyrightDatabaseHandler::ColumnDef in[], size_t size) const
{
  std::string result;
  for (size_t i = 0; i < size; ++i)
  {
    if (i != 0)
      result += ", ";
    result += in[i].name;
    result += " ";
    result += in[i].type;
    result += " ";
    result += in[i].creationFlags;
  }
  return result;
}

bool CopyrightDatabaseHandler::createTables() const
{
  int failedCounter = 0;
  bool tablesChecked = false;

  dbManager.ignoreWarnings(true);
  while (!tablesChecked && failedCounter < MAX_TABLE_CREATION_RETRIES)
  {
    dbManager.begin();

    tablesChecked = createTableAgentFindings() && createTableClearing();

    if (tablesChecked)
      dbManager.commit();
    else
    {
      dbManager.rollback();
      ++failedCounter;
      if (failedCounter < MAX_TABLE_CREATION_RETRIES)
        std::cout << "WARNING: table creation failed: trying again"
          " (" << failedCounter << "/" << MAX_TABLE_CREATION_RETRIES << ")"
          << std::endl;
    }
  }
  if (tablesChecked && (failedCounter > 0))
    std::cout << "NOTICE: table creation succeded on try "
      << failedCounter << "/" << MAX_TABLE_CREATION_RETRIES
      << std::endl;

  dbManager.ignoreWarnings(false);
  return tablesChecked;
}

const CopyrightDatabaseHandler::ColumnDef CopyrightDatabaseHandler::columns[] =
  {
#define SEQUENCE_NAME IDENTITY"_ct_pk_seq"
    {"ct_pk", "bigint", "PRIMARY KEY DEFAULT nextval('" SEQUENCE_NAME "'::regclass)"},
    {"agent_fk", "bigint", "NOT NULL"},
    {"pfile_fk", "bigint", "NOT NULL"},
    {"content", "text", ""},
    {"hash", "text", ""},
    {"type", "text", ""}, //TODO removed constrain: "CHECK (type in ('statement', 'email', 'url'))"},
    {"copy_startbyte", "integer", ""},
    {"copy_endbyte", "integer", ""},
    {"is_enabled", "boolean", "NOT NULL DEFAULT TRUE"},
  };

bool CopyrightDatabaseHandler::createTableAgentFindings() const
{
  if (!dbManager.sequenceExists(SEQUENCE_NAME))
  {
    RETURN_IF_FALSE(dbManager.queryPrintf("CREATE SEQUENCE "
      SEQUENCE_NAME
      " START WITH 1"
        " INCREMENT BY 1"
        " NO MAXVALUE"
        " NO MINVALUE"
        " CACHE 1"));
  }

  if (!dbManager.tableExists(IDENTITY))
  {
    size_t ncolumns = (sizeof(CopyrightDatabaseHandler::columns) / sizeof(CopyrightDatabaseHandler::ColumnDef));
    RETURN_IF_FALSE(dbManager.queryPrintf("CREATE table %s(%s)", IDENTITY,
      getColumnCreationString(CopyrightDatabaseHandler::columns, ncolumns).c_str()
    )
    );
    RETURN_IF_FALSE(dbManager.queryPrintf(
      "CREATE INDEX %s_agent_fk_index"
        " ON %s"
        " USING BTREE (agent_fk)",
      IDENTITY, IDENTITY
    ));

    RETURN_IF_FALSE(dbManager.queryPrintf(
      "CREATE INDEX %s_hash_index"
        " ON %s"
        " USING BTREE (hash)",
      IDENTITY, IDENTITY
    ));

    RETURN_IF_FALSE(dbManager.queryPrintf(
      "CREATE INDEX %s_pfile_fk_index"
        " ON %s"
        " USING BTREE (pfile_fk)",
      IDENTITY, IDENTITY
    ));

    RETURN_IF_FALSE(dbManager.queryPrintf(
      "ALTER TABLE ONLY %s"
        " ADD CONSTRAINT agent_fk"
        " FOREIGN KEY (agent_fk)"
        " REFERENCES agent(agent_pk) ON DELETE CASCADE",
      IDENTITY
    ));

    RETURN_IF_FALSE(dbManager.queryPrintf(
      "ALTER TABLE ONLY %s"
        " ADD CONSTRAINT pfile_fk"
        " FOREIGN KEY (pfile_fk)"
        " REFERENCES pfile(pfile_pk) ON DELETE CASCADE",
      IDENTITY
    ));
  }
  return true;
}


const CopyrightDatabaseHandler::ColumnDef CopyrightDatabaseHandler::columnsDecision[] = {
#define SEQUENCE_NAMEClearing IDENTITY"_decision_pk_seq"
  {IDENTITY"_decision_pk", "bigint", "PRIMARY KEY DEFAULT nextval('" SEQUENCE_NAMEClearing "'::regclass)"},
  {"user_fk", "bigint", "NOT NULL"},
  {"pfile_fk", "bigint", "NOT NULL"},
  {"clearing_decision_type_fk", "bigint", "NOT NULL"},
  {"description", "text", ""},
  {"textFinding", "text", ""},
  {"comment", "text", ""},
  {"is_enabled", "boolean", "NOT NULL DEFAULT TRUE"}
};

bool CopyrightDatabaseHandler::createTableClearing() const
{
  #define CLEARING_TABLE IDENTITY "_decision"

  if (!dbManager.sequenceExists(SEQUENCE_NAMEClearing))
  {
    RETURN_IF_FALSE(dbManager.queryPrintf("CREATE SEQUENCE "
      SEQUENCE_NAMEClearing
      " START WITH 1"
        " INCREMENT BY 1"
        " NO MAXVALUE"
        " NO MINVALUE"
        " CACHE 1"));
  }

  if (!dbManager.tableExists(CLEARING_TABLE))
  {
    size_t nDec = (sizeof(CopyrightDatabaseHandler::columnsDecision) / sizeof(CopyrightDatabaseHandler::ColumnDef));
    RETURN_IF_FALSE(dbManager.queryPrintf("CREATE table %s(%s)", CLEARING_TABLE,
      getColumnCreationString(CopyrightDatabaseHandler::columnsDecision, nDec).c_str()));

    RETURN_IF_FALSE(dbManager.queryPrintf(
      "CREATE INDEX %s_pfile_fk_index"
        " ON %s"
        " USING BTREE (pfile_fk)",
      CLEARING_TABLE, CLEARING_TABLE
    ));

    RETURN_IF_FALSE(dbManager.queryPrintf(
      "CREATE INDEX %s_user_fk_index"
        " ON %s"
        " USING BTREE (user_fk)",
      CLEARING_TABLE, CLEARING_TABLE
    ));

    RETURN_IF_FALSE(dbManager.queryPrintf(
      "CREATE INDEX %s_clearing_decision_type_fk_index"
        " ON %s"
        " USING BTREE (clearing_decision_type_fk)",
      CLEARING_TABLE, CLEARING_TABLE
    ));

    RETURN_IF_FALSE(dbManager.queryPrintf(
      "ALTER TABLE ONLY %s"
        " ADD CONSTRAINT user_fk"
        " FOREIGN KEY (user_fk)"
        " REFERENCES  users(user_pk) ON DELETE CASCADE",
      CLEARING_TABLE
    ));

    RETURN_IF_FALSE(dbManager.queryPrintf(
      "ALTER TABLE ONLY %s"
        " ADD CONSTRAINT pfile_fk"
        " FOREIGN KEY (pfile_fk)"
        " REFERENCES pfile(pfile_pk) ON DELETE CASCADE",
      CLEARING_TABLE
    ));
  }

  return true;
}

std::vector<unsigned long> CopyrightDatabaseHandler::queryFileIdsForUpload(int agentId, int uploadId)
{
  std::string uploadTreeTableName = queryUploadTreeTableName(uploadId);

  QueryResult queryResult = dbManager.queryPrintf(
      "SELECT pfile_pk"
            " FROM ("
            "  SELECT distinct(pfile_fk) AS PF"
            "  FROM %s"
            "  WHERE upload_fk = %d and (ufile_mode&x'3C000000'::int)=0"
            " ) AS SS "
            "LEFT OUTER JOIN %s on (PF = pfile_fk AND agent_fk = %d) "
#ifdef IDENTITY_COPYRIGHT
            "LEFT OUTER JOIN author AS au ON (PF = au.pfile_fk AND au.agent_fk = %d) "
#endif
            "INNER JOIN pfile on (PF = pfile_pk) "
#ifdef IDENTITY_COPYRIGHT
            "WHERE copyright.ct_pk IS NULL AND au.ct_pk IS NULL",
#else
            "WHERE ct_pk IS NULL OR agent_fk <> %d",
#endif
    uploadTreeTableName.c_str(),
    uploadId,
    IDENTITY,
    agentId
#ifdef IDENTITY_COPYRIGHT
    , agentId, agentId
#endif
    , agentId
  );

  return queryResult.getSimpleResults<unsigned long>(0, fo::stringToUnsignedLong);
}

bool CopyrightDatabaseHandler::insertNoResultInDatabase(long int agentId, long int pFileId) const
{
  return dbManager.execPrepared(
    fo_dbManager_PrepareStamement(
      dbManager.getStruct_dbManager(),
      "insertNoResultInDatabase",
      "INSERT INTO "
      IDENTITY
      "(agent_fk, pfile_fk) VALUES($1,$2)",
      long, long
    ),
    agentId, pFileId
  );
}

bool CopyrightDatabaseHandler::insertInDatabase(DatabaseEntry& entry) const
{
  std::string tableName = IDENTITY;

  if("author" == entry.type ||
     "email" == entry.type ||
     "url" == entry.type){
    tableName = "author";
   }

  return dbManager.execPrepared(
    fo_dbManager_PrepareStamement(
      dbManager.getStruct_dbManager(),
      ("insertInDatabaseFor" + tableName).c_str(),
      ("INSERT INTO "+ tableName +
      "(agent_fk, pfile_fk, content, hash, type, copy_startbyte, copy_endbyte)" +
        " VALUES($1,$2,$3,md5($3),$4,$5,$6)").c_str(),
        long, long, char*, char*, int, int
    ),
    entry.agent_fk, entry.pfile_fk,
    entry.content.c_str(),
    entry.type.c_str(),
    entry.copy_startbyte, entry.copy_endbyte
  );
}

CopyrightDatabaseHandler::CopyrightDatabaseHandler(DbManager manager) :
  AgentDatabaseHandler(manager)
{

}
