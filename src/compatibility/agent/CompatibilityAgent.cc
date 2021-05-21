/*
 SPDX-FileCopyrightText: © 2024 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

#include "CompatibilityAgent.hpp"

#include "CompatibilityStatus.hpp"

using namespace std;

/**
 * Print the debug status if needed, then insert the records to DB.
 * @param status          Compatibility status
 * @param verbosityDebug  Verbosity is on?
 * @param databaseHandler Database handler
 * @param pFileId         pfile id
 * @param agentId         agent id
 * @param lid1            First license ID
 * @param lid2            Second license ID
 * @param ruleName        Name of the rule for debug
 * @return True if insertion is successful, false otherwise.
 */
bool insertResultInDb(CompatibilityStatus status, bool verbosityDebug,
                      CompatibilityDatabaseHandler& databaseHandler,
                      unsigned long& pFileId, int agentId, unsigned long lid1,
                      unsigned long lid2, const string& ruleName)
{
  string compatibility = (status == COMPATIBLE) ? "t" : "f";
  if (verbosityDebug)
  {
    cout << ruleName << ((status == COMPATIBLE) ? "" : " not")
         << " compatible, " << pFileId << endl;
  }
  return databaseHandler.queryInsertResult(pFileId, agentId, lid1, lid2,
                                           compatibility);
}

/**
 * Default constructor for CompatibilityAgent.
 */
CompatibilityAgent::CompatibilityAgent(int agentId, bool verbosityDebug) :
    agentId(agentId), verbosityDebug(verbosityDebug)
{
}

/**
 * @brief find the compatibility between the licenses using scheduler mode
 * @param licId license id
 * @param pFileId file id
 * @param databaseHandler Database handler to be used
 * @return boolean
 */
bool CompatibilityAgent::checkCompatibilityForPfile(
    vector<unsigned long>& licId, unsigned long& pFileId,
    CompatibilityDatabaseHandler& databaseHandler) const
{
  vector<tuple<unsigned long, string>> licenseTypes;
  string def = "f";
  bool res, resultExists;
  licenseTypes = databaseHandler.queryLicDetails(licId);
  size_t length = licenseTypes.size();
  for (size_t main = 0; main < (length - 1); ++main)
  {
    for (size_t sub = (main + 1); sub < length; ++sub)
    {
      CompatibilityStatus status;
      unsigned long licenseId1, licenseId2;
      licenseId1 = get<0>(licenseTypes[main]);
      licenseId2 = get<0>(licenseTypes[sub]);
      resultExists = databaseHandler.check(licenseId1, licenseId2, pFileId);
      if (resultExists)
      {
        continue;
      }

      status =
          databaseHandler.queryRule1(licenseTypes[main], licenseTypes[sub]);
      if (status != UNKNOWN)
      {
        res = insertResultInDb(status, verbosityDebug, databaseHandler, pFileId,
                               agentId, licenseId1, licenseId2, "rule1");
        if (!res)
        {
          return res;
        }
        continue;
      }

      status =
          databaseHandler.queryRule2(licenseTypes[main], licenseTypes[sub]);
      if (status != UNKNOWN)
      {
        res = insertResultInDb(status, verbosityDebug, databaseHandler, pFileId,
                               agentId, licenseId1, licenseId2, "rule2");
        if (!res)
        {
          return res;
        }
        continue;
      }

      status =
          databaseHandler.queryRule3(licenseTypes[main], licenseTypes[sub]);
      if (status != UNKNOWN)
      {
        res = insertResultInDb(status, verbosityDebug, databaseHandler, pFileId,
                               agentId, licenseId1, licenseId2, "rule3");
        if (!res)
        {
          return res;
        }
        continue;
      }

      res = databaseHandler.queryInsertResult(pFileId, agentId, licenseId1,
                                              licenseId2, def);
      if (verbosityDebug)
      {
        cout << "default rule " << pFileId << endl;
      }
    }
  }
  return true;
}
