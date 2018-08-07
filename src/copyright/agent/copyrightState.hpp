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
#include "uniquePtr.hpp"

class CliOptions
{
private:
  int verbosity;
  unsigned int optType;
  bool json;
  std::list<unptr::shared_ptr<scanner>> cliScanners;

public:
  bool isVerbosityDebug() const;

  unsigned int getOptType() const;

  bool doJsonOutput() const;

  void addScanner(scanner* regexDesc);
  std::list<unptr::shared_ptr<scanner>> extractScanners();

  CliOptions(int verbosity, unsigned int type, bool json);
  CliOptions();
};

class CopyrightState
{
public:
  CopyrightState(CliOptions&& cliOptions);

  int getAgentId() const;

  /* give ownership of the scanner pointer to this CopyrightState */
  void addScanner(scanner* scanner);

  const std::list<unptr::shared_ptr<scanner>>& getScanners() const;

  const CliOptions& getCliOptions() const;

private:
  const CliOptions cliOptions;
  std::list<unptr::shared_ptr<scanner>> scanners;
};

#endif

