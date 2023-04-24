/*
 SPDX-FileCopyrightText: © 2021 Sarita Singh <saritasingh.0425@gmail.com>

 SPDX-License-Identifier: GPL-2.0-only
*/

#include "scancode_dbhandler.hpp"

/**
 * @brief Default constructor for DatabaseEntry
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
 * @brief constructor for DatabaseEntry
 * @param match   object of type Match class
 * @param agentId primary key of ScanCode agent
 * @param pfileId primary key of pfile
 */
DatabaseEntry::DatabaseEntry(Match match, unsigned long agentId,
                             unsigned long pfileId) :
    agent_fk(agentId), pfile_fk(pfileId), hash("")
{
  content = match.getMatchName();
  type = match.getType();
  copy_startbyte = match.getStartPosition();
  copy_endbyte = match.getStartPosition() + match.getLength();
};

/**
 * @brief  get string of parameters for a column for table creation  
 * @param in[]  input array of struct ColumnDef 
 * @param size  size of in[]
 * @return string of parameters
 */
std::string ScancodeDatabaseHandler::getColumnCreationString(const ScancodeDatabaseHandler::ColumnDef in[], size_t size) const
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
 * @brief Default constructor for ScanCode Database Handler class
 * @param dbManager   DBManager to be used
 */
ScancodeDatabaseHandler::ScancodeDatabaseHandler(DbManager dbManager) :
  fo::AgentDatabaseHandler(dbManager)
{
}

/**
 * @brief Instantiate a new object spawn for ScanCode Database handler
 * Used to create new objects for threads
 * @return DbManager object for threads
 */

ScancodeDatabaseHandler ScancodeDatabaseHandler::spawn() const
{
  DbManager spawnedDbMan(dbManager.spawn());
  return ScancodeDatabaseHandler(spawnedDbMan);
}

/**
 * @brief Function to get pfile ID for uploads
 * @param uploadId  Upload ID of uploads
 * @return Vector of pfile IDs
 */
vector<unsigned long> ScancodeDatabaseHandler::queryFileIdsForUpload(int uploadId, bool ignoreFilesWithMimeType)
{
  return queryFileIdsVectorForUpload(uploadId,ignoreFilesWithMimeType);
}

/**
 * @brief Insert null value of license for uploads having no licenses
 * @param agentId   agent_pk in agent database table
 * @param pFileId   pfile_pk in pfile dataabse table
 * @return True on successful insertion, false otherwise
 */
bool ScancodeDatabaseHandler::insertNoResultInDatabase(int agentId, long pFileId ,long licenseId)
{
  return dbManager.execPrepared(
    fo_dbManager_PrepareStamement(dbManager.getStruct_dbManager(),
      "scancodeInsertNoLicense",
      "INSERT INTO license_file"
      "(agent_fk, pfile_fk, rf_fk)"
      " VALUES($1,$2,$3)",
      int, long, long
    ),
    agentId, pFileId, licenseId);
}

/**
 * @brief save license match with license_ref table in license_file table 
 * Insert license if already not present in license_file table
 * @param agantId         agent pk in agent database table
 * @param pFileId         pfile pk in pfile dataabse table
 * @param licenseId       reference pk in license_ref table for matched or inserted license 
 * @param percentMatch    Score got for license from scancode agent 
 * @return  license_file  pk on success, -1 on failure to save match
 */
long ScancodeDatabaseHandler::saveLicenseMatch(
  int agentId, 
  long pFileId, 
  long licenseId, 
  int percentMatch)
{
  QueryResult result = dbManager.execPrepared(
    fo_dbManager_PrepareStamement(
      dbManager.getStruct_dbManager(),
      "saveLicenseMatch",
      "WITH "
          "selectExisting AS ("
          "SELECT fl_pk FROM ONLY license_file"
          " WHERE (agent_fk = $1 AND pfile_fk = $2 AND rf_fk = $3)"
          "),"
          "insertNew AS ("
          "INSERT INTO license_file"
          "(agent_fk, pfile_fk, rf_fk, rf_match_pct)"
          " SELECT $1, $2, $3, $4"
          " WHERE NOT EXISTS(SELECT * FROM license_file WHERE (agent_fk = $1 AND pfile_fk = $2 AND rf_fk = $3))"
          " RETURNING fl_pk"
          ") "
          "SELECT fl_pk FROM insertNew "
          "UNION "
          "SELECT fl_pk FROM selectExisting",
      int, long, long, unsigned
    ),
    agentId,
    pFileId,
    licenseId,
    percentMatch
  );

  long licenseFilePK= -1;
  if(!result.isFailed()){
    
    vector<unsigned long> res = result.getSimpleResults<unsigned long>(0,
    fo::stringToUnsignedLong);

    licenseFilePK = res.at(0);
  }
  return licenseFilePK;
}

/**
 * @brief save highlight information in the highlight table
 * @param licenseFileId   license_file pk 
 * @param start           start byte of highlight_text
 * @param length          total no of bytes from start byte
 * @return  true on success to save highlight, false on failure
 */
bool ScancodeDatabaseHandler::saveHighlightInfo(
  long licenseFileId,
  unsigned start,
  unsigned length)
{
  return dbManager.execPrepared(
    fo_dbManager_PrepareStamement(
      dbManager.getStruct_dbManager(),
      "saveHighlightInfo",
          "INSERT INTO highlight"
          "(fl_fk, type, start, len)"
          " SELECT $1, 'L', $2, $3 "
          " WHERE NOT EXISTS(SELECT * FROM highlight WHERE (fl_fk = $1 AND start = $2 AND len = $3))",
      long, unsigned, unsigned
    ),
    licenseFileId,
    start,
    length
  );}

/**
 * @brief calling function for selectOrInsertLicenseIdForName
 * @param rfShortName   spdx license key for the license
 * @param rfFullName    full name of the license
 * @param rfTextUrl reference url for license text
 */
void ScancodeDatabaseHandler::insertOrCacheLicenseIdForName(string const& rfShortName, string const& rfFullName, string const& rfTextUrl)
{
  if (getCachedLicenseIdForName(rfShortName)==0)
  {
    unsigned long licenseId = selectOrInsertLicenseIdForName(rfShortName, rfFullName, rfTextUrl);

    if (licenseId > 0)
    {
      licenseRefCache.insert(std::make_pair(rfShortName, licenseId));
    }
  }
}

/**
 * @brief for given short name search license 
 * @param rfShortName   spdx license key for the license
 * @return license id if found in license_ref table, 0 otherwise
 */
unsigned long ScancodeDatabaseHandler::getCachedLicenseIdForName(string const& rfShortName) const
{
  auto findIterator = licenseRefCache.find(rfShortName);
  if (findIterator != licenseRefCache.end())
  {
    return findIterator->second;
  }
  else
  {
    return 0;
  }
}

/**
 * Helper function to check if a string ends with other string.
 * @param firstString   The string to be checked
 * @param ending        The ending string
 * @return True if first string has the ending string at end, false otherwise.
 */
bool hasEnding(string const &firstString, string const &ending)
{
  if (firstString.length() >= ending.length())
  {
    return (0
      == firstString.compare(firstString.length() - ending.length(),
        ending.length(), ending));
  }
  else
  {
    return false;
  }
}

/**
 * @brief insert license if not present in license_ref table and return rf_pk
 * @param rfShortName  spdx license key for the license
 * @param rfFullName   full name of the license
 * @param rfTextUrl    reference url for license text
 * @return licenseId on success, 0 on failure
 */
unsigned long ScancodeDatabaseHandler::selectOrInsertLicenseIdForName(string rfShortName, string rfFullname, string rfTexturl)
{
  bool success = false;
  unsigned long result = 0;

  icu::UnicodeString unicodeCleanShortname = fo::recodeToUnicode(rfShortName);

  // Clean shortname to get utf8 string
  rfShortName = "";
  unicodeCleanShortname.toUTF8String(rfShortName);

  fo_dbManager_PreparedStatement *searchWithOr = fo_dbManager_PrepareStamement(
      dbManager.getStruct_dbManager(),
      "selectLicenseIdWithOrScancode",
      " SELECT rf_pk FROM ONLY license_ref"
      " WHERE LOWER(rf_shortname) = LOWER($1)"
      " OR LOWER(rf_shortname) = LOWER($2);",
      char*, char*);

  if (hasEnding(rfShortName, "+") || hasEnding(rfShortName, "-or-later"))
  {
    string tempShortName(rfShortName);
    /* Convert shortname to lower-case to make it case-insensitive*/
    std::transform(tempShortName.begin(), tempShortName.end(), tempShortName.begin(),
      ::tolower);
    string plus("+");
    string orLater("-or-later");

    unsigned long int plusLast = tempShortName.rfind(plus);
    unsigned long int orLaterLast = tempShortName.rfind(orLater);

    /* Remove last occurrence of + and -or-later (if found) */
    if (plusLast != string::npos)
    {
      tempShortName.erase(plusLast, string::npos);
    }
    if (orLaterLast != string::npos)
    {
      tempShortName.erase(orLaterLast, string::npos);
    }

    QueryResult queryResult = dbManager.execPrepared(searchWithOr,
        (tempShortName + plus).c_str(), (tempShortName + orLater).c_str());

    success = queryResult && queryResult.getRowCount() > 0;
    if (success)
    {
      result = queryResult.getSimpleResults<unsigned long>(0, fo::stringToUnsignedLong)[0];
    }
  }
  else
  {
    string tempShortName(rfShortName);
    /* Convert shortname to lower-case */
    std::transform(tempShortName.begin(), tempShortName.end(), tempShortName.begin(),
      ::tolower);
    string only("-only");

    unsigned long int onlyLast = tempShortName.rfind(only);

    /* Remove last occurrence of -only (if found) */
    if (onlyLast != string::npos)
    {
      tempShortName.erase(onlyLast, string::npos);
    }

    QueryResult queryResult = dbManager.execPrepared(searchWithOr,
        tempShortName.c_str(), (tempShortName + only).c_str());

    success = queryResult && queryResult.getRowCount() > 0;
    if (success)
    {
      result = queryResult.getSimpleResults<unsigned long>(0, fo::stringToUnsignedLong)[0];
    }
  }

  if (result > 0)
  {
    return result;
  }


  unsigned count = 0;
  while ((!success) && count++<3)
  {
    if (!dbManager.begin())
      continue;

    dbManager.queryPrintf("LOCK TABLE license_ref");
    QueryResult queryResult = dbManager.execPrepared(
      fo_dbManager_PrepareStamement(
        dbManager.getStruct_dbManager(),
        "selectOrInsertLicenseIdForName",
        "WITH "
          "selectExisting AS ("
            "SELECT rf_pk FROM ONLY license_ref"
            " WHERE rf_shortname = $1"
          "),"
          "insertNew AS ("
            "INSERT INTO license_ref(rf_shortname, rf_text, rf_detector_type, rf_fullname, rf_url)"
            " SELECT $1, $2, $3, $4, $5"
            " WHERE NOT EXISTS(SELECT * FROM selectExisting)"
            " RETURNING rf_pk"
          ") "

        "SELECT rf_pk FROM insertNew "
        "UNION "
        "SELECT rf_pk FROM selectExisting",
        char*, char*, int, char* , char* 
      ),
      rfShortName.c_str(),
      "License by Scancode.",
      4,
      rfFullname.c_str(),
      rfTexturl.c_str()
      
    );

    success = queryResult && queryResult.getRowCount() > 0;

    if (success) {
      success &= dbManager.commit();

      if (success) {
        result = queryResult.getSimpleResults(0, fo::stringToUnsignedLong)[0];
      }
    } else {
      dbManager.rollback();
    }
  }

  return result;
}

/**
 * @brief insert copyright/author in scancode_copyright/scancode_author table 
 * @param entry   object of DatabaseEntry class
 * @return  true on success, false otherwise
 */
bool ScancodeDatabaseHandler::insertInDatabase(DatabaseEntry& entry) const
{
  std::string tableName = "scancode_author";

  if("scancode_statement" == entry.type ){
    tableName = "scancode_copyright";
  }

  return dbManager.execPrepared(
    fo_dbManager_PrepareStamement(
      dbManager.getStruct_dbManager(),
      ("insertInDatabaseFor " + tableName).c_str(),
      ("INSERT INTO "+ tableName +
      "(agent_fk, pfile_fk, content, hash, type, copy_startbyte, copy_endbyte)" +
        " SELECT $1, $2, $3, md5($3), $4, $5, $6 "
        " WHERE NOT EXISTS(SELECT * FROM " + tableName +
        " WHERE (agent_fk= $1 AND pfile_fk = $2 AND hash = md5($3)))").c_str(),
        long, long, char*, char*, int, int
    ),
    entry.agent_fk, entry.pfile_fk,
    entry.content.c_str(),
    entry.type.c_str(),
    entry.copy_startbyte, entry.copy_endbyte
  );
}

/**
 * @brief create tables to save copyright and author informations
 * @return  true on successful creation, false otherwise
 */
bool ScancodeDatabaseHandler::createTables() const
{
  int failedCounter = 0;
  bool tablesChecked = false;

  dbManager.ignoreWarnings(true);
  while (!tablesChecked && failedCounter < MAX_TABLE_CREATION_RETRIES)
  {
    dbManager.begin();
    tablesChecked = createTableAgentFindings("scancode_copyright") && createTableAgentFindings("scancode_author")&& createTableAgentEvents("scancode_copyright_event") && createTableAgentEvents("scancode_author_event");


    if (tablesChecked)
      dbManager.commit();
    else
    {
      dbManager.rollback();
      ++failedCounter;
      if (failedCounter < MAX_TABLE_CREATION_RETRIES)
      LOG_WARNING("table creation failed: trying again (%d/%d) \n", failedCounter, MAX_TABLE_CREATION_RETRIES);
    }
  }
  if (tablesChecked && (failedCounter > 0))
    LOG_NOTICE("table creation succeeded on try %d/%d \n", failedCounter, MAX_TABLE_CREATION_RETRIES);
  dbManager.ignoreWarnings(false);
  return tablesChecked;
}

/**
 * @brief Columns required to store copyright information by scancode agent
 */
const ScancodeDatabaseHandler::ColumnDef
    ScancodeDatabaseHandler::columns_copyright[] = {
#define CSEQUENCE_NAME "scancode_copyright_pk_seq"
#define CCOLUMN_NAME_PK "scancode_copyright_pk"
        {CCOLUMN_NAME_PK, "bigint",
         "PRIMARY KEY DEFAULT nextval('" CSEQUENCE_NAME "'::regclass)"},
        {"agent_fk", "bigint", "NOT NULL"},
        {"pfile_fk", "bigint", "NOT NULL"},
        {"content", "text", ""},
        {"hash", "text", ""},
        {"type", "text", ""},
        {"copy_startbyte", "integer", ""},
        {"copy_endbyte", "integer", ""},
        {"is_enabled", "boolean", "NOT NULL DEFAULT TRUE"},
};

/**
 * @brief Columns required to store author information by scancode agent
 */
const ScancodeDatabaseHandler::ColumnDef
    ScancodeDatabaseHandler::columns_author[] = {
#define ASEQUENCE_NAME "scancode_author_pk_seq"
#define ACOLUMN_NAME_PK "scancode_author_pk"
        {ACOLUMN_NAME_PK, "bigint",
         "PRIMARY KEY DEFAULT nextval('" ASEQUENCE_NAME "'::regclass)"},
        {"agent_fk", "bigint", "NOT NULL"},
        {"pfile_fk", "bigint", "NOT NULL"},
        {"content", "text", ""},
        {"hash", "text", ""},
        {"type", "text", ""},
        {"copy_startbyte", "integer", ""},
        {"copy_endbyte", "integer", ""},
        {"is_enabled", "boolean", "NOT NULL DEFAULT TRUE"},
};

/**
 * @brief create table to store agent findings
 * @param tableName   name of the table to be created
 * @return  true on successful creation, false otherwise
 */
bool ScancodeDatabaseHandler::createTableAgentFindings( string tableName) const
{
  const char *tablename = "";
  const char *sequencename = "";
  if (tableName == "scancode_copyright") {
    tablename = "scancode_copyright";
    sequencename = "scancode_copyright_pk_seq";
  } else if (tableName == "scancode_author") {
    tablename = "scancode_author";
    sequencename = "scancode_author_pk_seq";
  }
  if (!dbManager.sequenceExists(sequencename)) {
    RETURN_IF_FALSE(dbManager.queryPrintf("CREATE SEQUENCE %s"
      " START WITH 1"
        " INCREMENT BY 1"
        " NO MAXVALUE"
        " NO MINVALUE"
        " CACHE 1",sequencename));
  }

  if (!dbManager.tableExists(tablename))
  {
    if (tableName == "scancode_copyright") {
    size_t ncolumns = (sizeof(ScancodeDatabaseHandler::columns_copyright) / sizeof(ScancodeDatabaseHandler::ColumnDef));
    RETURN_IF_FALSE(dbManager.queryPrintf("CREATE table %s(%s)", tablename,
      getColumnCreationString(ScancodeDatabaseHandler::columns_copyright, ncolumns).c_str()
    )
    );
  } else if (tableName == "scancode_author") {
    size_t ncolumns = (sizeof(ScancodeDatabaseHandler::columns_author) / sizeof(ScancodeDatabaseHandler::ColumnDef));
    RETURN_IF_FALSE(dbManager.queryPrintf("CREATE table %s(%s)", tablename,
      getColumnCreationString(ScancodeDatabaseHandler::columns_author, ncolumns).c_str()
    )
    );
  }
    
    RETURN_IF_FALSE(dbManager.queryPrintf(
      "CREATE INDEX %s_agent_fk_index"
        " ON %s"
        " USING BTREE (agent_fk)",
      tablename, tablename
    ));

    RETURN_IF_FALSE(dbManager.queryPrintf(
      "CREATE INDEX %s_hash_index"
        " ON %s"
        " USING BTREE (hash)",
      tablename, tablename
    ));

    RETURN_IF_FALSE(dbManager.queryPrintf(
      "CREATE INDEX %s_pfile_fk_index"
        " ON %s"
        " USING BTREE (pfile_fk)",
      tablename, tablename
    ));

    RETURN_IF_FALSE(dbManager.queryPrintf(
      "ALTER TABLE ONLY %s"
        " ADD CONSTRAINT agent_fk"
        " FOREIGN KEY (agent_fk)"
        " REFERENCES agent(agent_pk) ON DELETE CASCADE",
      tablename
    ));

    RETURN_IF_FALSE(dbManager.queryPrintf(
      "ALTER TABLE ONLY %s"
        " ADD CONSTRAINT pfile_fk"
        " FOREIGN KEY (pfile_fk)"
        " REFERENCES pfile(pfile_pk) ON DELETE CASCADE",
      tablename
    ));
  }
  return true;
}
 
/**
 * @brief Columns required to store copyright deactivated statement
 */
const ScancodeDatabaseHandler::ColumnDef
    ScancodeDatabaseHandler::columns_copyright_event[] = {
#define CESEQUENCE_NAME "scancode_copyright_event_pk_seq"
#define CECOLUMN_NAME_PK "scancode_copyright_event_pk"
        {CECOLUMN_NAME_PK, "bigint",
         "PRIMARY KEY DEFAULT nextval('" CESEQUENCE_NAME "'::regclass)"},
        {"upload_fk", "bigint", "NOT NULL"},
        {"uploadtree_fk", "bigint", "NOT NULL"},
        {"scancode_copyright_fk", "bigint", "NOT NULL"},
        {"content", "text", ""},
        {"hash", "text", ""},
        {"is_enabled", "boolean", "NOT NULL DEFAULT FALSE"},
        {"scope", "int4", "NOT NULL"},
};

/**
 * @brief Columns required to store author deactivated statement
 */
const ScancodeDatabaseHandler::ColumnDef
    ScancodeDatabaseHandler::columns_author_event[] = {
#define AESEQUENCE_NAME "scancode_author_event_pk_seq"
#define AECOLUMN_NAME_PK "scancode_author_event_pk"
        {AECOLUMN_NAME_PK, "bigint",
         "PRIMARY KEY DEFAULT nextval('" AESEQUENCE_NAME "'::regclass)"},
        {"upload_fk", "bigint", "NOT NULL"},
        {"uploadtree_fk", "bigint", "NOT NULL"},
        {"scancode_author_fk", "bigint", "NOT NULL"},
        {"content", "text", ""},
        {"hash", "text", ""},
        {"is_enabled", "boolean", "NOT NULL DEFAULT FALSE"},
        {"scope", "int4", "NOT NULL"},
};

/**
 * @brief create table to store agent events
 * @param tableName   name of the table to be created
 * @return  true on successful creation, false otherwise
 */
bool ScancodeDatabaseHandler::createTableAgentEvents( string tableName) const
{
  const char *tablename = "";
  const char *etablename = "";
  const char *esequencename = "";
  if (tableName == "scancode_copyright_event") {
    etablename = "scancode_copyright_event";
    esequencename = "scancode_copyright_event_pk_seq";
    tablename = "scancode_copyright";
  } else if (tableName == "scancode_author_event") {
    etablename = "scancode_author_event";
    esequencename = "scancode_author_event_pk_seq";
    tablename = "scancode_author";
  }
  if (!dbManager.sequenceExists(esequencename)) {
    RETURN_IF_FALSE(dbManager.queryPrintf("CREATE SEQUENCE %s"
      " START WITH 1"
        " INCREMENT BY 1"
        " NO MAXVALUE"
        " NO MINVALUE"
        " CACHE 1",esequencename));
  }

  if (!dbManager.tableExists(etablename))
  {
    if (tableName == "scancode_copyright_event") {
    size_t ncolumns = (sizeof(ScancodeDatabaseHandler::columns_copyright_event) / sizeof(ScancodeDatabaseHandler::ColumnDef));
    RETURN_IF_FALSE(dbManager.queryPrintf("CREATE table %s(%s)", etablename,
      getColumnCreationString(ScancodeDatabaseHandler::columns_copyright_event, ncolumns).c_str()
    )
    );
  } else if (tableName == "scancode_author_event") {
    size_t ncolumns = (sizeof(ScancodeDatabaseHandler::columns_author_event) / sizeof(ScancodeDatabaseHandler::ColumnDef));
    RETURN_IF_FALSE(dbManager.queryPrintf("CREATE table %s(%s)", etablename,
      getColumnCreationString(ScancodeDatabaseHandler::columns_author_event, ncolumns).c_str()
    )
    );
  }
    RETURN_IF_FALSE(dbManager.queryPrintf(
      "CREATE INDEX %s_upload_fk_index"
        " ON %s"
        " USING BTREE (upload_fk)",
      etablename, etablename
    ));
    RETURN_IF_FALSE(dbManager.queryPrintf(
      "CREATE INDEX %s_uploadtree_fk_index"
        " ON %s"
        " USING BTREE (uploadtree_fk)",
      etablename, etablename
    ));
    RETURN_IF_FALSE(dbManager.queryPrintf(
      "CREATE INDEX %s_scancode_fk_index"
        " ON %s"
        " USING BTREE (%s_fk)",
      etablename, etablename, tablename
    ));
    
    RETURN_IF_FALSE(dbManager.queryPrintf(
      "ALTER TABLE ONLY %s"
        " ADD CONSTRAINT upload_fk"
        " FOREIGN KEY (upload_fk)"
        " REFERENCES upload(upload_pk) ON DELETE CASCADE",
      etablename
    ));


    RETURN_IF_FALSE(dbManager.queryPrintf(
      "ALTER TABLE ONLY %s"
        " ADD CONSTRAINT %s_fk"
        " FOREIGN KEY (%s_fk)"
        " REFERENCES %s(%s_pk) ON DELETE CASCADE",
      etablename, tablename, tablename, tablename, tablename
    ));
    RETURN_IF_FALSE(dbManager.queryPrintf(
      "ALTER TABLE %s"
      " ALTER COLUMN scope"
      " SET DEFAULT 1",
      etablename
    ));
  }
  return true;
}
