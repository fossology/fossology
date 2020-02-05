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

/**
 * \class CliOptions
 * \brief Store the options sent through the CLI
 */
class CliOptions
{
private:
  int verbosity;                        /**< The verbosity level */
  unsigned int optType;                 /**< Scan type (2 => url, 4 => email, 8 => author, 16 => ecc) */
  bool json;                            /**< Whether to generate JSON output */
  bool ignoreFilesWithMimeType;         /**< Whether to ignore files with particular mimetype */
  std::list<unptr::shared_ptr<scanner>> cliScanners; /**< List of available scanners */

public:
  bool isVerbosityDebug() const;

  unsigned int getOptType() const;

  bool doJsonOutput() const;
  bool doignoreFilesWithMimeType() const;

  void addScanner(scanner* regexDesc);
  std::list<unptr::shared_ptr<scanner>> extractScanners();

  CliOptions(int verbosity, unsigned int type, bool json, bool ignoreFilesWithMimeType);
  CliOptions();
};

/**
 * \class CopyrightState
 * \brief Holds information about state of one agent
 */
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
  const CliOptions cliOptions;          /**< CliOptions passed */
  std::list<unptr::shared_ptr<scanner>> scanners; /**< List of available scanners */
};

#endif

