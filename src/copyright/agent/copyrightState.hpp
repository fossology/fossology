/*
 SPDX-FileCopyrightText: © 2014 Siemens AG
 Author: Johannes Najjar, Daniele Fognini

 SPDX-License-Identifier: GPL-2.0-only
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

