/*
 SPDX-FileCopyrightText: © 2014-2017,2022, Siemens AG
 Author: Daniele Fognini, Johannes Najjar

 SPDX-License-Identifier: GPL-2.0-only
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

/**
 * \brief Default constructor for DatabaseEntry
 */
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

/**
 * \brief Spawn/fork a new database handler and return it
 * \return CopyrightDatabaseHandler object with spawned DbManager
 */
CopyrightDatabaseHandler CopyrightDatabaseHandler::spawn() const
{
  DbManager spawnedDbMan(dbManager.spawn());
  return CopyrightDatabaseHandler(spawnedDbMan);
}

/**
 * \brief Given a list of ColumnDef, return a comma separated list of column names
 * \param in   List to parse
 * \param size Number of elements in the list
 * \return Comma separated list of column names
 * \see CopyrightDatabaseHandler::ColumnDef
 */
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

/**
 * \brief Return a comma delimited string with column elements separated by space.
 * The string is used for database creation
 * \param in   List of column to be parsed
 * \param size Number of elements in the list
 * \return Comma delimited string
 * \see CopyrightDatabaseHandler::createTableAgentFindings()
 */
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

/**
 * \brief Create tables required by agent
 *
 * Calls createTableAgentFindings() and createTableClearing()
 * to create the tables required by the agent to work.
 *
 * The function tries to create table in maximum of MAX_TABLE_CREATION_RETRIES
 * attempts.
 * \return True if success, false otherwise
 */
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

/**
 * \brief Columns required by agent in database
 * \todo Removed constrain: "CHECK (type in ('statement', 'email', 'url'))"}
 */
const CopyrightDatabaseHandler::ColumnDef CopyrightDatabaseHandler::columns[] =
  {
#define SEQUENCE_NAME IDENTITY"_pk_seq"
#define COLUMN_NAME_PK IDENTITY"_pk"
    { COLUMN_NAME_PK, "bigint", "PRIMARY KEY DEFAULT nextval('" SEQUENCE_NAME "'::regclass)"},
    {"agent_fk", "bigint", "NOT NULL"},
    {"pfile_fk", "bigint", "NOT NULL"},
    {"content", "text", ""},
    {"hash", "text", ""},
    {"type", "text", ""}, //TODO removed constrain: "CHECK (type in ('statement', 'email', 'url'))"},
    {"copy_startbyte", "integer", ""},
    {"copy_endbyte", "integer", ""},
    {"is_enabled", "boolean", "NOT NULL DEFAULT TRUE"},
  };

/**
 * \brief Create table to store agent find data
 * \return True on success, false otherwise
 * \see CopyrightDatabaseHandler::columns
 */
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

/**
 * \brief Columns required to store user decisions in database.
 */
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

/**
 * \brief Create table to store user decisions
 * \return True on success, false otherwise
 * \see CopyrightDatabaseHandler::columnsDecision
 */
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

/**
 * \brief Get the list of pfile ids on which the given agent has no findings for a given upload
 * \param agentId  Agent id to be removed from result
 * \param uploadId Upload id to scan for files
 * \param ignoreFilesWithMimeType to exclude filetypes with particular mimetype
 * \return List of pfiles on which the given agent has no findings
 */
std::vector<unsigned long> CopyrightDatabaseHandler::queryFileIdsForUpload(int agentId, int uploadId, bool ignoreFilesWithMimeType)
{
  std::string uploadTreeTableName = queryUploadTreeTableName(uploadId);
  fo_dbManager_PreparedStatement* preparedStatement;
  std::string sql = "SELECT pfile_pk"
    " FROM ("
    "  SELECT distinct(pfile_fk) AS PF"
    "  FROM " + uploadTreeTableName +
    "  WHERE upload_fk = $1 and (ufile_mode&x'3C000000'::int)=0"
    " ) AS SS "
    "LEFT OUTER JOIN " IDENTITY " ON (PF = pfile_fk AND agent_fk = $2) "
#ifdef IDENTITY_COPYRIGHT
    "LEFT OUTER JOIN author AS au ON (PF = au.pfile_fk AND au.agent_fk = $2) "
#endif
    "INNER JOIN pfile ON (PF = pfile_pk) "
#ifdef IDENTITY_COPYRIGHT
    "WHERE copyright.copyright_pk IS NULL AND au.author_pk IS NULL"
#else
    "WHERE (" IDENTITY "_pk IS NULL OR agent_fk <> $2)"
#endif
    ;
  std::string statementName = "queryFileIdsForUpload:" IDENTITY "Agent" + uploadTreeTableName;
  if (ignoreFilesWithMimeType)
  {
    sql = sql + " AND (pfile_mimetypefk NOT IN ( "
      "SELECT mimetype_pk FROM mimetype WHERE mimetype_name=ANY(string_to_array(( "
      "SELECT conf_value FROM sysconfig WHERE variablename='SkipFiles'),','))));";
    statementName = statementName + "withMimetype";
  }
  preparedStatement =
    fo_dbManager_PrepareStamement(dbManager.getStruct_dbManager(),
      statementName.c_str(),
      sql.c_str(),
      int, int);
  QueryResult queryResult = dbManager.execPrepared(preparedStatement,
      uploadId, agentId);

  return queryResult.getSimpleResults<unsigned long>(0, fo::stringToUnsignedLong);

}

/**
 * \brief Insert a finding in database
 * \param entry Entry to be inserted in the database
 * \return True on success, false otherwise
 * \see DatabaseEntry
 */
bool CopyrightDatabaseHandler::insertInDatabase(DatabaseEntry& entry) const
{
  std::string tableName = IDENTITY;
  std::string content;
  entry.content.toUTF8String(content);

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
    content.c_str(),
    entry.type.c_str(),
    entry.copy_startbyte, entry.copy_endbyte
  );
}

/**
 * \brief Constructor to initialize database handler
 */
CopyrightDatabaseHandler::CopyrightDatabaseHandler(DbManager manager) :
  AgentDatabaseHandler(manager)
{

}
