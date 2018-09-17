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

CopyrightState::CopyrightState(CliOptions&& cliOptions) :
  cliOptions(cliOptions),
  scanners(cliOptions.extractScanners())
{
}

void CopyrightState::addScanner(scanner* sc)
{
  if (sc)
    scanners.push_back(unptr::shared_ptr<scanner>(sc));
}

const std::list<unptr::shared_ptr<scanner>>& CopyrightState::getScanners() const
{
  return scanners;
}

CliOptions::CliOptions(int verbosity, unsigned int type, bool json) :
  verbosity(verbosity),
  optType(type),
  json(json),
  cliScanners()
{
}

CliOptions::CliOptions() :
  verbosity(0),
  optType(ALL_TYPES),
  cliScanners()
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

std::list<unptr::shared_ptr<scanner>> CliOptions::extractScanners()
{
  return std::move(cliScanners);
}

bool CliOptions::isVerbosityDebug() const
{
  return verbosity >= 1;
}

void CliOptions::addScanner(scanner* sc)
{
  cliScanners.push_back(unptr::shared_ptr<scanner>(sc));
}

bool CliOptions::doJsonOutput() const
{
  return json;
}

