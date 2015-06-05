/*
 * Copyright (C) 2014-2015, Siemens AG
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

#include "copyrightState.hpp"
#include "identity.hpp"

CopyrightState::CopyrightState(int _agentId, const CliOptions& cliOptions) :
  agentId(_agentId),
  cliOptions(cliOptions),
  scanners()
{
}

CopyrightState::~CopyrightState()
{
  // TODO: Scanners with pointers contained in scanners list have been allocated
  // with new and therefore have to be deleted here (but not those from extraRegexes)
  /*for (const scanner* p : scanners)
    delete p;*/
}

int CopyrightState::getAgentId() const
{
  return agentId;
};

void CopyrightState::addScanner(const scanner* sc)
{
  scanners.push_back(sc);
}

const std::list<const scanner*>& CopyrightState::getScanners() const
{
  return scanners;
}


CliOptions::CliOptions(int verbosity, unsigned int type) :
  verbosity(verbosity),
  optType(type),
  extraRegex()
{
}

CliOptions::CliOptions() :
  verbosity(0),
  optType(ALL_TYPES),
  extraRegex()
{
}

unsigned int CliOptions::getOptType() const
{
  return optType;
}

const CliOptions& CopyrightState::getCliOptions() const
{
  return cliOptions;
}

const std::list<regexScanner>& CliOptions::getExtraRegexes() const
{
  return extraRegex;
}

bool CliOptions::isVerbosityDebug() const
{
  return verbosity >= 1;
}

bool CliOptions::addExtraRegex(const std::string& regexDesc)
{
  #define RGX_FMT_SEPARATOR "@@"
  auto fmtRegex = rx::regex(
    "(?:([[:alpha:]]+)" RGX_FMT_SEPARATOR ")?(?:([[:digit:]]+)" RGX_FMT_SEPARATOR ")?(.*)",
    rx::regex_constants::icase
  );

  rx::match_results<std::string::const_iterator> match;
  if (rx::regex_match(regexDesc.begin(), regexDesc.end(), match, fmtRegex))
  {
    std::string type(match.length(1) > 0 ? match.str(1) : "cli");
    
    int regId = match.length(2) > 0 ? std::stoi(std::string(match.str(2))) : 0;

    if (match.length(3) == 0)
      return false;

    std::string regexPattern(match.str(3));
    
    char * ptype = new char[type.length() + 1];
    // TODO: delete this pointer somewhere?
    strcpy(ptype, type.c_str());

    extraRegex.push_back(regexScanner(regexPattern, ptype, regId));
    return true;
  }
  return false;
}

