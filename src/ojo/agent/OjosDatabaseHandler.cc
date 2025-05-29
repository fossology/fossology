/*
 SPDX-FileCopyrightText: © 2019 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/
/**
 * @file
 * @brief Data base handler for OJO
 */

#include "OjosDatabaseHandler.hpp"

using namespace fo;
using namespace std;

/**
 * Default constructor for OjosDatabaseHandler
 * @param dbManager DBManager to be used
 */
OjosDatabaseHandler::OjosDatabaseHandler(DbManager dbManager) :
    fo::AgentDatabaseHandler(dbManager)
{
}

/**
 * Get a vector of all file id for a given upload id.
 * @param uploadId Upload ID to be queried
 * @param agentId  Current agent ID
 * @param ignoreFilesWithMimeType To ignore files with particular mimetype
 * @return List of all pfiles for the given upload
 */
vector<unsigned long> OjosDatabaseHandler::queryFileIdsForUpload (
    int uploadId, int agentId, bool ignoreFilesWithMimeType)
{
  return queryFileIdsVectorForUpload(uploadId, agentId, ignoreFilesWithMimeType);
}

/**
 * Spawn a new DbManager object.
 *
 * Used to create new objects for threads.
 * @return DbManager object for threads.
 */
OjosDatabaseHandler OjosDatabaseHandler::spawn() const
{
  DbManager spawnedDbMan(dbManager.spawn());
  return OjosDatabaseHandler(spawnedDbMan);
}

/**
 * @brief Save findings to the database if agent was called by scheduler
 * @param entry The entry to be made
 * @return fl_pk on success, -1 on failure
 */
unsigned long OjosDatabaseHandler::saveLicenseToDatabase(
    OjoDatabaseEntry &entry) const
{
  QueryResult queryResult = dbManager.execPrepared(
    fo_dbManager_PrepareStamement(dbManager.getStruct_dbManager(),
      "ojoInsertLicense",
      "INSERT INTO license_file"
      "(rf_fk, agent_fk, pfile_fk)"
      " VALUES($1,$2,$3) RETURNING fl_pk",
      long, long, long),
    entry.license_fk, entry.agent_fk, entry.pfile_fk);
  vector<unsigned long> res = queryResult.getSimpleResults<unsigned long>(0,
    fo::stringToUnsignedLong);
  if (res.size() > 0)
  {
    return res.at(0);
  }
  else
  {
    return -1;
  }
}
/**
 * @brief Save License Expressions findings to the database if agent was called by scheduler
 * @param match Match found by the agent
 * @return rf_pk on success, -1 on failure
 */
unsigned long OjosDatabaseHandler::saveLicenseExpressionToDatabase(const ojomatch &match) const
{
  QueryResult queryResult = dbManager.execPrepared(
    fo_dbManager_PrepareStamement(dbManager.getStruct_dbManager(),
      "ojoFindLicenseExpression",
      "SELECT rf_pk FROM license_expression WHERE rf_expression = $1",
      char*),
    match.content.c_str());
  vector<unsigned long> res = queryResult.getSimpleResults<unsigned long>(0, fo::stringToUnsignedLong);
  if (res.size() > 0)
  {
    return res.at(0);
  }
  QueryResult insertResult = dbManager.execPrepared(
    fo_dbManager_PrepareStamement(dbManager.getStruct_dbManager(),
      "ojoInsertLicenseExpression",
      "INSERT INTO license_expression"
      "(rf_expression)"
      " VALUES($1) RETURNING rf_pk",
      char* ),
    match.content.c_str());
    res = insertResult.getSimpleResults<unsigned long>(0,
    fo::stringToUnsignedLong);
  if (res.size() > 0)
  {
    return res.at(0);
  }
  else
  {
    return -1;
  }
}

/**
 * Save findings highlights to DB
 * @param match Match to be saved
 * @param fl_fk fl_pk from license_file table
 * @return True on success, false otherwise
 */
bool OjosDatabaseHandler::saveHighlightToDatabase(const ojomatch &match,
    const unsigned long fl_fk) const
{
  if (fl_fk < 1)
  {
    return false;
  }
  return dbManager.execPrepared(
    fo_dbManager_PrepareStamement(dbManager.getStruct_dbManager(),
      "ojoInsertHighlight",
      "INSERT INTO highlight"
      "(fl_fk, start, len, type)"
      " VALUES($1,$2,$3,'L')",
      long, long, long
    ),
      fl_fk, match.start,
    match.len);
}

/**
 * @brief Save no result to the database
 * @param entry Entry containing the agent id and file id
 * @return True of successful insertion, false otherwise
 */
bool OjosDatabaseHandler::insertNoResultInDatabase(
    OjoDatabaseEntry &entry) const
{
  return dbManager.execPrepared(
    fo_dbManager_PrepareStamement(dbManager.getStruct_dbManager(),
      "ojoInsertNoLicense",
      "INSERT INTO license_file"
      "(agent_fk, pfile_fk)"
      " VALUES($1,$2)",
      long, long
    ),
    entry.agent_fk, entry.pfile_fk);
}

/**
 * Helper function to check if a string ends with other string.
 * @param firstString The string to be checked
 * @param ending      The ending string
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
 * Get the license id for a given short name or create a new entry.
 *
 * @note
 * The following rules are also applied on name matching:
 * -# All matches are case in-sensitive.
 * -# `GPL-2.0 and GPL-2.0-only` are treated as same
 * -# `GPL-2.0+ and GPL-2.0-or-later` are treated as same
 *
 * @note
 * If the agent could not find license in main license, it will create a new one
 * as candidate license.
 *
 * @param rfShortName Short name to be searched.
 * @param groupId     Group id for candidate license
 * @param userId      User who is running the agent
 * @returns License id, 0 on failure
 */
unsigned long OjosDatabaseHandler::selectOrInsertLicenseIdForName(
    string rfShortName, const int groupId, const int userId)
{
  bool success = false;
  unsigned long result = 0;

  icu::UnicodeString unicodeCleanShortname = fo::recodeToUnicode(rfShortName);

  // Clean shortname to get utf8 string
  rfShortName = "";
  unicodeCleanShortname.toUTF8String(rfShortName);

  fo_dbManager_PreparedStatement *searchWithOr = fo_dbManager_PrepareStamement(
      dbManager.getStruct_dbManager(),
      "selectLicenseIdWithOrOJO",
      "WITH mainLicense AS ("
      " SELECT rf_pk, 1 AS prior FROM ONLY license_ref"
      " WHERE LOWER(rf_shortname) = LOWER($1)"
      " OR LOWER(rf_shortname) = LOWER($2)"
      "), candidateLicense AS ("
      " SELECT rf_pk, 2 AS prior FROM license_candidate"
      " WHERE (LOWER(rf_shortname) = LOWER($1)"
      " OR LOWER(rf_shortname) = LOWER($2))"
      " AND group_fk = $3"
      ")"
      "SELECT rf_pk, prior FROM mainLicense "
      "UNION "
      "SELECT rf_pk, prior FROM candidateLicense "
      "ORDER BY prior;",
      char*, char*, int);

  /* First check similar matches */
  /* Check if the name ends with +, -or-later, -only */
  if (hasEnding(rfShortName, "+") || hasEnding(rfShortName, "-or-later"))
  {
    string tempShortName(rfShortName);
    /* Convert shortname to lower-case */
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
        (tempShortName + plus).c_str(), (tempShortName + orLater).c_str(),
        groupId);

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
        tempShortName.c_str(), (tempShortName + only).c_str(), groupId);

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

  if (groupId < 1)
  {
    return 0;
  }

  if (userId < 1)
  {
    return 0;
  }

  unsigned count = 0;
  while ((!success) && count++ < 3)
  {
    if (!dbManager.begin())
      continue;

    dbManager.queryPrintf("LOCK TABLE license_ref");

    QueryResult queryResult =
      dbManager.execPrepared(
        fo_dbManager_PrepareStamement(dbManager.getStruct_dbManager(),
          "selectOrInsertLicenseIdForNameOjo",
          "WITH "
          "selectExisting AS ("
          "SELECT rf_pk FROM ONLY license_ref"
          " WHERE LOWER(rf_shortname) = LOWER($1)"
          "),"
          "insertNew AS ("
          "INSERT INTO license_candidate"
          "(rf_shortname, rf_text, rf_detector_type, group_fk, "
          "rf_user_fk_created, rf_user_fk_modified)"
          " SELECT $1, $2, $3, $4, $5, $5"
          " WHERE NOT EXISTS(SELECT * FROM selectExisting)"
          " RETURNING rf_pk"
          ") "

          "SELECT rf_pk FROM insertNew "
          "UNION "
          "SELECT rf_pk FROM selectExisting",
          char*, char*, int, int, int
        ),
        rfShortName.c_str(), "License by OJO.", 3, groupId, userId);

    success = queryResult && queryResult.getRowCount() > 0;

    if (success)
    {
      success &= dbManager.commit();

      if (success)
      {
        result = queryResult.getSimpleResults(0, fo::stringToUnsignedLong)[0];
      }
    }
    else
    {
      dbManager.rollback();
    }
  }

  return result;
}

/**
 * @brief Get the license id for a given short name.
 *
 * The function first checks if the license exists in the cache list. If the
 * license is not cached, it checks in DB and store in the cache.
 * @param rfShortName Short name to be searched
 * @param groupId     Group running the agent
 * @param userId      UserRunning the agent
 * @returns License ID if found, 0 otherwise
 * @sa OjosDatabaseHandler::getCachedLicenseIdForName()
 */
unsigned long OjosDatabaseHandler::getLicenseIdForName(
    string const &rfShortName, const int groupId, const int userId)
{
  unsigned long licenseId = getCachedLicenseIdForName(rfShortName);
  if (licenseId == 0)
  {
    licenseId = selectOrInsertLicenseIdForName(rfShortName, groupId, userId);
    licenseRefCache.insert(std::make_pair(rfShortName, licenseId));
  }
  return licenseId;
}

/**
 * Get the license id from the cached license list.
 * @param rfShortName Name of the license
 * @returns License id if found, 0 otherwise
 */
unsigned long OjosDatabaseHandler::getCachedLicenseIdForName(
    string const &rfShortName) const
{
  std::unordered_map<string, long>::const_iterator findIterator =
    licenseRefCache.find(rfShortName);
  if (findIterator != licenseRefCache.end())
  {
    return findIterator->second;
  }
  else
  {
    return 0;
  }
}
