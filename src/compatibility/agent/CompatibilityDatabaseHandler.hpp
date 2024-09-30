/*
 SPDX-FileCopyrightText: © 2024 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/
/**
 * @file
 * @brief Database handler for COMPATIBILITY
 */

#ifndef COMPATIBILITY_AGENT_DATABASE_HANDLER_HPP
#define COMPATIBILITY_AGENT_DATABASE_HANDLER_HPP

#include "CompatibilityStatus.hpp"
#include "libfossAgentDatabaseHandler.hpp"
#include "libfossdbmanagerclass.hpp"
#include "libfossUtils.hpp"

#include <algorithm>
#include <iostream>
#include <string>
#include <tuple>
#include <unordered_map>
#include <vector>

extern "C"
{
#include "libfossology.h"
}
using namespace std;
/**
 * @struct CompatibilityDatabaseEntry
 * Structure to hold entries to be inserted in DB
 */
struct CompatibilityDatabaseEntry
{
  /**
   * @var long int license_fk
   * License ID
   * @var long int agent_fk
   * Agent ID
   * @var long int pfile_fk
   * Pfile ID
   */
  const unsigned long int license_fk, agent_fk, pfile_fk;
  /**
   * Constructor for CompatibilityDatabaseEntry structure
   * @param l License ID
   * @param a Agent ID
   * @param p Pfile ID
   */
  CompatibilityDatabaseEntry(const unsigned long int l,
                             const unsigned long int a,
                             const unsigned long int p) :
      license_fk(l), agent_fk(a), pfile_fk(p)
  {
  }
};

/**
 * @class CompatibilityDatabaseHandler
 * Database handler for COMPATIBILITY agent
 */
class CompatibilityDatabaseHandler : public fo::AgentDatabaseHandler
{
public:
  CompatibilityDatabaseHandler(fo::DbManager dbManager);
  CompatibilityDatabaseHandler(CompatibilityDatabaseHandler&& other) :
      fo::AgentDatabaseHandler(std::move(other)) {};
  CompatibilityDatabaseHandler spawn() const;

  std::vector<unsigned long> queryFileIdsForUpload(int uploadId);
  std::vector<unsigned long> queryFileIdsForScan(int uploadId, int agentId);
  std::vector<unsigned long> queryScannerIdsForUpload(int uploadId);

  std::vector<unsigned long> queryLicIdsFromPfile(
      unsigned long pFileId, vector<unsigned long> agentIds);
  std::vector<unsigned long> queryMainLicenseForUpload(int uploadId,
                                                       int groupId);
  std::vector<std::tuple<unsigned long, std::string>> queryLicDetails(
      const std::vector<unsigned long>& licId);
  CompatibilityStatus queryRule1(tuple<unsigned long, string> lic1,
                                 tuple<unsigned long, string> lic2) const;
  CompatibilityStatus queryRule2(
      std::tuple<unsigned long, std::string> lic1,
      std::tuple<unsigned long, std::string> lic2) const;
  CompatibilityStatus queryRule3(
      std::tuple<unsigned long, std::string> lic1,
      std::tuple<unsigned long, std::string> lic2) const;
  bool queryInsertResult(unsigned long pFileId, int a_id, unsigned long id1,
                         unsigned long id2, const string& comp);
  bool check(unsigned long id1, unsigned long id2, unsigned long pFileId);
  CompatibilityStatus getDefaultRule() const;
};

#endif // COMPATIBILITY_AGENT_DATABASE_HANDLER_HPP
