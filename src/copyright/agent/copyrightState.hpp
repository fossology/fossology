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

#ifndef copyrightState_h
#define copyrightState_h

#include "libfossdbmanagerclass.hpp"
#include "regexMatcher.hpp"
#include "database.hpp"
#include <vector>

class CopyrightState {
public:
  CopyrightState(DbManager dbManager, int _agentId, int _verbosity);
//  CopyrightState(const CopyrightState&) = delete;
//  CopyrightState(CopyrightState&&);

//  CopyrightState operator=(const CopyrightState&) = delete;
  ~CopyrightState();

  CopyrightState spawn() const;

  int getAgentId() const;
  int getVerbosity() const;
  PGconn * getConnection() const;
  void addMatcher(RegexMatcher regexMatcher);
  std::vector<RegexMatcher> getRegexMatchers() const;
  CopyrightDatabaseHandler copyrightDatabaseHandler;
  std::vector<long> queryFileIdsForUpload(long uploadId) const;
  DbManager getDbManager() const;
private:
  DbManager dbManager;
  int agentId;
  int verbosity;
  std::vector<RegexMatcher> regexMatchers;
};

#endif
