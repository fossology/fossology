/*
 SPDX-FileCopyrightText: © 2019 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/
/**
 * @file
 * @brief Database handler for OJO
 */

#ifndef OJOS_AGENT_DATABASE_HANDLER_HPP
#define OJOS_AGENT_DATABASE_HANDLER_HPP

#include <unordered_map>
#include <algorithm>
#include <string>
#include <iostream>

#include "libfossUtils.hpp"
#include "libfossAgentDatabaseHandler.hpp"
#include "libfossdbmanagerclass.hpp"
#include "ojomatch.hpp"

extern "C" {
#include "libfossology.h"
}

/**
 * @struct OjoDatabaseEntry
 * Structure to hold entries to be inserted in DB
 */
struct OjoDatabaseEntry
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
   * Constructor for OjoDatabaseEntry structure
   * @param l License ID
   * @param a Agent ID
   * @param p Pfile ID
   */
  OjoDatabaseEntry(const unsigned long int l, const unsigned long int a,
    const unsigned long int p) :
    license_fk(l), agent_fk(a), pfile_fk(p)
  {
  }
};

/**
 * @class OjosDatabaseHandler
 * Database handler for OJO agent
 */
class OjosDatabaseHandler: public fo::AgentDatabaseHandler
{
  public:
    OjosDatabaseHandler(fo::DbManager dbManager);
    OjosDatabaseHandler(OjosDatabaseHandler &&other) :
      fo::AgentDatabaseHandler(std::move(other))
    {
    }
    ;
    OjosDatabaseHandler spawn() const;

    std::vector<unsigned long> queryFileIdsForUpload(int uploadId, int agentId,
                                                     bool ignoreFilesWithMimeType);
    unsigned long saveLicenseToDatabase(OjoDatabaseEntry &entry) const;
    bool insertNoResultInDatabase(OjoDatabaseEntry &entry) const;
    unsigned long saveLicenseExpressionToDatabase(const ojomatch &match) const;
    bool saveHighlightToDatabase(const ojomatch &match,
      const unsigned long fl_fk) const;

    unsigned long getLicenseIdForName(std::string const &rfShortName,
                                      const int groupId,
                                      const int userId);

  private:
    unsigned long getCachedLicenseIdForName (
      std::string const &rfShortName) const;
    unsigned long selectOrInsertLicenseIdForName (std::string rfShortname,
                                                  const int groupId,
                                                  const int userId);
    /**
     * Cached license pairs
     */
    std::unordered_map<std::string, long> licenseRefCache;
};

#endif // OJOS_AGENT_DATABASE_HANDLER_HPP
