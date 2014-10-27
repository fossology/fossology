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

// TODO: see function queryFileIdsForUpload() from src/lib/c/libfossagent.c
vector<unsigned long> NinkaDatabaseHandler::queryFileIdsForUpload(int uploadId)
{
  QueryResult queryResult = dbManager.execPrepared(
    fo_dbManager_PrepareStamement(
      dbManager.getStruct_dbManager(),
      "queryFileIdsForUpload",
      "SELECT DISTINCT(pfile_fk) FROM uploadtree WHERE upload_fk = $1 AND (ufile_mode&x'3C000000'::int) = 0",
      int
    ),
    uploadId
  );

  return queryResult.getSimpleResults<unsigned long>(0, fo::stringToUnsignedLong);
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

unsigned long NinkaDatabaseHandler::queryLicenseIdForLicense(string rfShortName)
{
  QueryResult queryResult = dbManager.execPrepared(
    fo_dbManager_PrepareStamement(
      dbManager.getStruct_dbManager(),
      "queryLicenseIdForLicense",
      "SELECT rf_pk FROM license_ref WHERE rf_shortname = $1",
      char*
    ),
    rfShortName.c_str()
  );

  return (queryResult.getRowCount() == 1) ? queryResult.getSimpleResults<unsigned long>(0, fo::stringToUnsignedLong)[0] : 0;
}

unsigned long NinkaDatabaseHandler::saveLicense(string rfShortName)
{
  QueryResult queryResult = dbManager.execPrepared(
    fo_dbManager_PrepareStamement(
      dbManager.getStruct_dbManager(),
      "saveLicense",
      "INSERT INTO license_ref(rf_shortname, rf_text, rf_detector_type) VALUES ($1, $2, $3) RETURNING rf_pk",
      char*,
      char*,
      int
    ),
    rfShortName.c_str(),
    "License by Ninka.",
    3
  );

  return (queryResult.getRowCount() == 1) ? queryResult.getSimpleResults<unsigned long>(0, fo::stringToUnsignedLong)[0] : 0;
}
