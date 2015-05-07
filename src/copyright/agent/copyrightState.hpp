/*
 * Copyright (C) 2014, Siemens AG
 * Author: Johannes Najjar, Daniele Fognini
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License version 2
 * as published by the Free Software Foundation.
 * 
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 * See the GNU General Public License for more details.
 * 
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software Foundation,
 * Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 */

#ifndef copyrightState_h
#define copyrightState_h

#include "regscan.hpp"
#include "copyscan.hpp"

#include "libfossdbmanagerclass.hpp"
#include "database.hpp"
#include <list>

class CliOptions
{
private:
  int verbosity;
  unsigned int optType;
  std::list<regexScanner> extraRegex;

public:
  const std::list<regexScanner>& getExtraRegexes() const;
  bool isVerbosityDebug() const;

  unsigned int getOptType() const;

  bool addExtraRegex(const std::string& regexDesc);

  CliOptions(int verbosity, unsigned int type);
  CliOptions();
};

class CopyrightState
{
public:
  CopyrightState(int agentId, const CliOptions& cliOptions);
  ~CopyrightState();

  int getAgentId() const;

  void addScanner(const scanner* sc);
  //void addScanners(const std::list<scanner>& sc);

  const std::list<const scanner*>& getScanners() const;

  const CliOptions& getCliOptions() const;

private:
  int agentId;
  const CliOptions& cliOptions;
  std::list<const scanner*> scanners;
};

#endif

