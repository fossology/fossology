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
#include "identity.hpp"

CopyrightState::CopyrightState(int _agentId, const CliOptions& cliOptions) :
  agentId(_agentId),
  cliOptions(cliOptions),
  regexMatchers()
{
}

CopyrightState::~CopyrightState()
{
}

int CopyrightState::getAgentId() const
{
  return agentId;
};

void CopyrightState::addMatcher(RegexMatcher regexMatcher)
{
  regexMatchers.push_back(regexMatcher);
}

const std::vector<RegexMatcher>& CopyrightState::getRegexMatchers() const
{
  return regexMatchers;
}


CliOptions::CliOptions(int verbosity, unsigned int type) :
  verbosity(verbosity),
  optType(type),
  extraRegex(false),
  extraRegexId(0),
  extraRegexStr("")
{
}

CliOptions::CliOptions(int verbosity, unsigned int optType, std::string& extraRegexStr, unsigned extraRegexId) :
  verbosity(verbosity),
  optType(optType),
  extraRegex(true),
  extraRegexId(extraRegexId),
  extraRegexStr(extraRegexStr)
{
}

CliOptions::CliOptions():
  verbosity(0),
  optType(ALL_TYPES),
  extraRegex(false),
  extraRegexId(0),
  extraRegexStr("")
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

const std::string& CliOptions::getExtraRegex() const
{
  return extraRegexStr;
}

bool CliOptions::hasExtraRegex() const
{
  return extraRegex;
}

unsigned int CliOptions::getExtraRegexId() const
{
  return extraRegexId;
}

bool CliOptions::isVerbosityDebug() const
{
  return verbosity >= 1;
}
