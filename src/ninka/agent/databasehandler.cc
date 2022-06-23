/*
 SPDX-FileCopyrightText: © 2014-2015 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

#include "databasehandler.hpp"
#include "libfossUtils.hpp"

#include <iostream>

using namespace fo;
using namespace std;

NinkaDatabaseHandler::NinkaDatabaseHandler(DbManager dbManager) :
  fo::AgentDatabaseHandler(dbManager)
{
}

vector<unsigned long> NinkaDatabaseHandler::queryFileIdsForUpload(int uploadId)
{
  return queryFileIdsVectorForUpload(uploadId);
}

// TODO: see function saveToDb() from src/monk/agent/database.c
bool NinkaDatabaseHandler::saveLicenseMatch(int agentId, long pFileId, long licenseId, unsigned percentMatch)
{
  return dbManager.execPrepared(
    fo_dbManager_PrepareStamement(
      dbManager.getStruct_dbManager(),
      "saveLicenseMatch",
      "INSERT INTO license_file (agent_fk, pfile_fk, rf_fk, rf_match_pct) VALUES ($1, $2, $3, $4)",
      int, long, long, unsigned
    ),
    agentId,
    pFileId,
    licenseId,
    percentMatch
  );
}

unsigned long NinkaDatabaseHandler::selectOrInsertLicenseIdForName(string rfShortName)
{
  bool success = false;
  unsigned long result = 0;

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
            "INSERT INTO license_ref(rf_shortname, rf_text, rf_detector_type)"
            " SELECT $1, $2, $3"
            " WHERE NOT EXISTS(SELECT * FROM selectExisting)"
            " RETURNING rf_pk"
          ") "

        "SELECT rf_pk FROM insertNew "
        "UNION "
        "SELECT rf_pk FROM selectExisting",
        char*, char*, int
      ),
      rfShortName.c_str(),
      "License by Ninka.",
      3
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

NinkaDatabaseHandler NinkaDatabaseHandler::spawn() const
{
  DbManager spawnedDbMan(dbManager.spawn());
  return NinkaDatabaseHandler(spawnedDbMan);
}

void NinkaDatabaseHandler::insertOrCacheLicenseIdForName(string const& rfShortName)
{
  if (getCachedLicenseIdForName(rfShortName)==0)
  {
    unsigned long licenseId = selectOrInsertLicenseIdForName(rfShortName);

    if (licenseId > 0)
    {
      licenseRefCache.insert(std::make_pair(rfShortName, licenseId));
    }
  }
}

unsigned long NinkaDatabaseHandler::getCachedLicenseIdForName(string const& rfShortName) const
{
  std::unordered_map<string,long>::const_iterator findIterator = licenseRefCache.find(rfShortName);
  if (findIterator != licenseRefCache.end())
  {
    return findIterator->second;
  }
  else
  {
    return 0;
  }
}
