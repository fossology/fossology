/*
 * Copyright (C) 2014, Siemens AG
 *
 * This program is free software; you can redistribute it and/or modify it under the terms of the GNU General Public License version 2 as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along with this program; if not, write to the Free Software Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 */

#include "databasehandler.hpp"
#include "libfossUtils.hpp"

NinkaDatabaseHandler::NinkaDatabaseHandler(DbManager dbManager) :
  fo::AgentDatabaseHandler(dbManager)
{
}

vector<unsigned long> NinkaDatabaseHandler::queryFileIdsForUpload(int uploadId)
{
  return dbManager.queryFileIdsVectorForUpload(uploadId);
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
  bool committed = false;

  unsigned count = 0;
  while ((!committed) && count++<3)
  {
    dbManager.begin();
    dbManager.queryPrintf("LOCK TABLE license_ref");

    QueryResult queryResult = dbManager.execPrepared(
      fo_dbManager_PrepareStamement(
        dbManager.getStruct_dbManager(),
        "selectOrInsertLicenseIdForName",
        "WITH "
          "selectExisting AS ("
            "SELECT rf_pk FROM license_ref"
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

    committed = dbManager.commit();

    if (committed && queryResult && queryResult.getRowCount() > 0) {
      return queryResult.getSimpleResults(0, fo::stringToUnsignedLong)[0];
    }
  }

  return 0;
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
