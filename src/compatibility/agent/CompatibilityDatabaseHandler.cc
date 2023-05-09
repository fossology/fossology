/*
 SPDX-FileCopyrightText: © 2024 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/
/**
 * @file
 * @brief Data base handler for COMPATIBILITY
 */

#include "CompatibilityDatabaseHandler.hpp"

#include "libfossagentref.h"

#include <iterator>
#include <sstream>
#include <utility>


using namespace fo;
using namespace std;

/**
 * Default constructor for CompatibilityDatabaseHandler
 * @param dbManager DBManager to be used
 */
CompatibilityDatabaseHandler::CompatibilityDatabaseHandler(
    DbManager dbManager) :
    fo::AgentDatabaseHandler(std::move(dbManager))
{
}

/**
 * Get a vector of all file id for a given upload id.
 * @param uploadId Upload ID to be queried
 * @return List of all pfiles for the given upload
 */
vector<unsigned long> CompatibilityDatabaseHandler::queryFileIdsForUpload(
    int uploadId)
{
  return queryFileIdsVectorForUpload(uploadId, false);
}

/**
 * Get a vector of all file id for a given upload id which are not scanned by
 * the given agentId.
 * @param uploadId Upload ID to be queried
 * @param agentId  ID of the agent
 * @return List of all pfiles for the given upload
 */
vector<unsigned long> CompatibilityDatabaseHandler::queryFileIdsForScan(
    int uploadId, int agentId)
{
  string uploadtreeTableName = queryUploadTreeTableName(uploadId);

  QueryResult queryResult = dbManager.execPrepared(
      fo_dbManager_PrepareStamement(
          dbManager.getStruct_dbManager(),
          ("pfileForUploadFilterAgent" + uploadtreeTableName).c_str(),
          ("SELECT distinct(ut.pfile_fk) FROM " + uploadtreeTableName +
           " AS ut "
           "INNER JOIN license_file AS lf ON ut.pfile_fk = lf.pfile_fk "
           "LEFT JOIN comp_result AS cr ON ut.pfile_fk = cr.pfile_fk "
           "AND cr.agent_fk = $2 WHERE cr.pfile_fk IS NULL "
           "AND ut.upload_fk = $1 AND (ut.ufile_mode&x'3C000000'::int)=0;")
          .c_str(), int, int),
      uploadId, agentId);

  return queryResult.getSimpleResults(0, fo::stringToUnsignedLong);
}

/**
 * @brief to get the license id from the file id
 * @param pFileId
 * @return List of licenses for the given file id
 */
std::vector<unsigned long> CompatibilityDatabaseHandler::queryLicIdsFromPfile(
    unsigned long pFileId,
    vector<unsigned long> agentIds) // to get licId from pfile_id
{
  std::stringstream ss;

  ss << "{";
  for (size_t i = 0; i < agentIds.size() - 1; i++)
  {
    ss << agentIds[i] << ",";
  }
  ss << agentIds[agentIds.size() - 1] << "}";

  QueryResult queryResult = dbManager.execPrepared(
      fo_dbManager_PrepareStamement(
          dbManager.getStruct_dbManager(), "getLicenses",
          ("SELECT DISTINCT rf_fk FROM license_file INNER JOIN license_ref "
           "ON rf_fk = rf_pk AND rf_shortname NOT IN"
           "('Dual-license', 'No_license_found', 'Void') WHERE pfile_fk = $1 "
           "AND agent_fk = ANY($2::int[])"),
          unsigned long, char*),
      pFileId, ss.str().c_str());

  return queryResult.getSimpleResults(0, fo::stringToUnsignedLong);
}

/**
 * @brief store the id and type of that respective license in vector of tuple
 * for all the licenses
 * @param licId
 * @return vector of tuple
 */
vector<tuple<unsigned long, string>> CompatibilityDatabaseHandler::
    queryLicDetails(const vector<unsigned long>& licId)
{
  vector<tuple<unsigned long, string>> vec;
  for (auto i : licId)
  {
    QueryResult queryResult = dbManager.execPrepared(
        fo_dbManager_PrepareStamement(
            dbManager.getStruct_dbManager(), "agentCompGetLicDetails",
            ("SELECT rf_licensetype FROM license_ref WHERE rf_pk = $1"),
            unsigned long),
        i);
    vector<string> vec2 = queryResult.getRow(0);
    vec.emplace_back(i, vec2[0]);
  }
  return vec;
}

/**
 * @brief This rule uses id of both the licenses to find the compatibility
 * @param lic1 holding license 2 information i.e. license id and license type
 * @param lic2 holding license 2 information i.e. license id and license type
 * @return Compatibility status
 */
CompatibilityStatus CompatibilityDatabaseHandler::queryRule1(
    tuple<unsigned long, string> lic1, tuple<unsigned long, string> lic2) const
{
  QueryResult queryResult = dbManager.execPrepared(
      fo_dbManager_PrepareStamement(
          dbManager.getStruct_dbManager(), "getCompatibilityOnLicenseId",
          ("SELECT compatibility FROM license_rules WHERE (first_rf_fk = $1 AND "
           "second_rf_fk = "
           "$2) OR (second_rf_fk = $1 AND first_rf_fk = $2)"),
          unsigned long, unsigned long),
      get<0>(lic1), get<0>(lic2));
  if (queryResult.getRowCount() == 0)
  {
    return UNKNOWN;
  }
  else
  {
    bool result = queryResult.getSimpleResults(0, fo::stringToBool)[0];
    if (result)
    {
      return COMPATIBLE;
    }
    else
    {
      return NOTCOMPATIBLE;
    }
  }
}

/**
 * @brief This rule uses license type of both the licenses to find the
 * compatibility
 * @param lic1 holding license 1 information
 * @param lic2 holding license 2 information
 * @return Compatibility status
 */
CompatibilityStatus CompatibilityDatabaseHandler::queryRule2(
    std::tuple<unsigned long, std::string> lic1,
    std::tuple<unsigned long, std::string> lic2) const
{
  QueryResult queryResult = dbManager.execPrepared(
      fo_dbManager_PrepareStamement(dbManager.getStruct_dbManager(),
                                    "getCompatibilityOnLicenseType",
                                    ("SELECT compatibility FROM license_rules "
                                     "WHERE (first_type = $1 AND second_type = $2) "
                                     "OR (second_type = $1 AND first_type = $2)"),
                                    char*, char*),
      get<1>(lic1).c_str(), get<1>(lic2).c_str());
  if (queryResult.getRowCount() == 0)
  {
    return UNKNOWN;
  }
  else
  {
    bool result = queryResult.getSimpleResults(0, fo::stringToBool)[0];
    if (result)
    {
      return COMPATIBLE;
    }
    else
    {
      return NOTCOMPATIBLE;
    }
  }
}

/**
 * @brief This rule uses license id and license type to find the compatibility
 * @param lic1 holding license 1 information
 * @param lic2 holding license 2 information
 * @return Compatibility status
 */
CompatibilityStatus CompatibilityDatabaseHandler::queryRule3(
    std::tuple<unsigned long, std::string> lic1,
    std::tuple<unsigned long, std::string> lic2) const
{
  unsigned long licenseId1, licenseId2;

  licenseId1 = get<0>(lic1);
  licenseId2 = get<0>(lic2);
  const char* licenseType1 = get<1>(lic1).c_str();
  const char* licenseType2 = get<1>(lic2).c_str();

  QueryResult queryResult = dbManager.execPrepared(
      fo_dbManager_PrepareStamement(
          dbManager.getStruct_dbManager(), "getCompatibilityOnLicenseIdAndType",
          ("SELECT compatibility FROM license_rules WHERE (first_rf_fk = $1 AND "
           "second_type = $2) OR (first_rf_fk = $3 AND second_type = $4)"),
          unsigned long, char*, unsigned long, char*),
      licenseId1, licenseType2, licenseId2, licenseType1);

  if (queryResult.getRowCount() == 0)
  {
    return UNKNOWN;
  }
  else
  {
    bool result = queryResult.getSimpleResults(0, fo::stringToBool)[0];
    if (result)
    {
      return COMPATIBLE;
    }
    else
    {
      return NOTCOMPATIBLE;
    }
  }
}

/**
 * @brief Checking whether the data is already present in database or not, to
 * prevent redundancy
 * @param id1 holding license 1 information
 * @param id2 holding license 2 information
 * @return boolean
 */
bool CompatibilityDatabaseHandler::check(unsigned long id1, unsigned long id2,
                                         unsigned long pFileId)
{
  QueryResult queryResult = dbManager.execPrepared(
      fo_dbManager_PrepareStamement(
          dbManager.getStruct_dbManager(), "comp_resultExists",
          ("SELECT exists(SELECT 1 FROM comp_result WHERE ((first_rf_fk= $1 "
           "AND "
           "second_rf_fk= $2) OR (second_rf_fk= $1 AND first_rf_fk= $2)) AND "
           "pfile_fk = $3)"),
          unsigned long, unsigned long, unsigned long),
      id1, id2, pFileId);
  return queryResult.getSimpleResults(0, fo::stringToBool)[0];
}

/**
 * @brief insert the compatibility result in the comp_result table
 * @param pFileId file id
 * @param a_id agent id
 * @param id1 first license id
 * @param id2 second license id
 * @param comp storing the compatibility result
 * @return boolean
 */
bool CompatibilityDatabaseHandler::queryInsertResult(unsigned long pFileId,
                                                     int a_id,
                                                     unsigned long id1,
                                                     unsigned long id2,
                                                     const string& comp)
{
  QueryResult queryResult = dbManager.execPrepared(
      fo_dbManager_PrepareStamement(
          dbManager.getStruct_dbManager(), "CompInsertResult",
          "INSERT INTO comp_result"
          "(pfile_fk, agent_fk, first_rf_fk, second_rf_fk, result)"
          "VALUES($1, $2, $3, $4, $5)",
          unsigned long, int, unsigned long, unsigned long, char*),
      pFileId, a_id, id1, id2, comp.c_str());
  return !queryResult.isFailed();
}
/**
 * Spawn a new DbManager object.
 *
 * Used to create new objects for threads.
 * @return DbManager object for threads.
 */
CompatibilityDatabaseHandler CompatibilityDatabaseHandler::spawn() const
{
  DbManager spawnedDbMan(dbManager.spawn());
  return CompatibilityDatabaseHandler(spawnedDbMan);
}

/**
 * \brief Get the default rule from DB as CompatibilityStatus
 * \return COMPATIBLE if default rule not found, otherwise the value from DB
 */
CompatibilityStatus CompatibilityDatabaseHandler::getDefaultRule() const
{
  QueryResult queryResult = dbManager.execPrepared(
      fo_dbManager_PrepareStamement(
          dbManager.getStruct_dbManager(), "getDefaultRule",
          "SELECT compatibility FROM license_rules "
          "WHERE first_rf_fk IS NULL AND second_rf_fk IS NULL AND "
          "first_type IS NULL AND second_type IS NULL;"));
  if (queryResult.getRowCount() == 0)
  {
    return COMPATIBLE;
  }
  bool result = queryResult.getSimpleResults(0, fo::stringToBool)[0];
  if (result)
  {
    return COMPATIBLE;
  }
  return NOTCOMPATIBLE;
}

/**
 * \brief Get the agent id of latest scanners run on the upload
 * \param uploadId Upload ID
 * \return Vectory of latest agent ids of scanners run on the upload
 */
std::vector<unsigned long> CompatibilityDatabaseHandler::
    queryScannerIdsForUpload(int uploadId)
{
  QueryResult agentIdResult = dbManager.execPrepared(
    fo_dbManager_PrepareStamement(
      dbManager.getStruct_dbManager(), "latestScanner",
      ("SELECT DISTINCT ON (agent_name) agent_pk FROM agent AS ag "
       "INNER JOIN ars_master AS am "
       " ON ag.agent_pk = am.agent_fk AND am.upload_fk = $1"
       "  AND am.ars_success = true "
       "WHERE agent_name IN (" FOSSOLOGY_SCANNER_AGENT_NAME_ARRAY ") "
       "ORDER BY agent_name, agent_ts DESC;"),
      unsigned long),
    uploadId);
  return agentIdResult.getSimpleResults(0, fo::stringToUnsignedLong);
}

/**
 * \brief Get the main licenses for the upload.
 * \param uploadId Upload ID
 * \return Vector of main licenses
 */
std::vector<unsigned long> CompatibilityDatabaseHandler::
    queryMainLicenseForUpload(int uploadId, int groupId)
{
  QueryResult agentIdResult = dbManager.execPrepared(
      fo_dbManager_PrepareStamement(
          dbManager.getStruct_dbManager(), "mainLicense",
          ("SELECT DISTINCT rf_fk FROM "
           "upload_clearing_license "
           "WHERE upload_fk = $1 AND group_fk = $2;"),
          unsigned long, unsigned long),
      uploadId, groupId);
  return agentIdResult.getSimpleResults(0, fo::stringToUnsignedLong);
}
