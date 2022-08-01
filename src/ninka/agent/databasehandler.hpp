/*
 SPDX-FileCopyrightText: © 2014-2015 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

#ifndef NINKA_AGENT_DATABASE_HANDLER_HPP
#define NINKA_AGENT_DATABASE_HANDLER_HPP

#include <string>
#include <vector>
#include <unordered_map>
#include "libfossAgentDatabaseHandler.hpp"
#include "libfossdbmanagerclass.hpp"

class NinkaDatabaseHandler : public fo::AgentDatabaseHandler
{
public:
  NinkaDatabaseHandler(fo::DbManager dbManager);
  NinkaDatabaseHandler(NinkaDatabaseHandler&& other) : fo::AgentDatabaseHandler(std::move(other)) {};
  NinkaDatabaseHandler spawn() const;

  std::vector<unsigned long> queryFileIdsForUpload(int uploadId);
  bool saveLicenseMatch(int agentId, long pFileId, long licenseId, unsigned percentMatch);

  void insertOrCacheLicenseIdForName(std::string const& rfShortName);
  unsigned long getCachedLicenseIdForName(std::string const& rfShortName) const;

private:
  unsigned long selectOrInsertLicenseIdForName(std::string rfShortname);

  std::unordered_map<std::string,long> licenseRefCache;
};

#endif // NINKA_AGENT_DATABASE_HANDLER_HPP
