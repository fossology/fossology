/*
 * Copyright (C) 2014, Siemens AG
 * Author: Johannes Najjar, Daniele Fognini
 *
 * This program is free software; you can redistribute it and/or modify it under the terms of the GNU General Public License version 2 as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along with this program; if not, write to the Free Software Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 */

#include "copyrightState.hpp"

CopyrightState::CopyrightState(DbManager* _dbManager, int _agentId, int _verbosity):
                              copyrightDatabaseHandler(),
                              dbManager(_dbManager),
                              agentId(_agentId),
                              verbosity(_verbosity),
                              regexMatchers() {}

CopyrightState::~CopyrightState() {}

int CopyrightState::getAgentId() {
  return agentId;
};

int CopyrightState::getVerbosity() {
  return verbosity;
};

DbManager* CopyrightState::getDbManager() {
  return dbManager;
};

PGconn* CopyrightState::getConnection() {
  return dbManager->getConnection();
};

void CopyrightState::addMatcher(RegexMatcher regexMatcher) {
  regexMatchers.push_back(regexMatcher);
}

std::vector<RegexMatcher> CopyrightState::getRegexMatchers() {
  return regexMatchers;
}

std::vector<long> CopyrightState::queryFileIdsForUpload(long uploadId) {
  return copyrightDatabaseHandler.queryFileIdsForUpload(dbManager, agentId, uploadId);
}
