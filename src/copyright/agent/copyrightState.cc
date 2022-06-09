/*
 SPDX-FileCopyrightText: © 2014-2015 Siemens AG
 Author: Johannes Najjar, Daniele Fognini

 SPDX-License-Identifier: GPL-2.0-only
*/

#include "copyrightState.hpp"
#include "identity.hpp"

/**
 * \brief Constructor to initialize state
 * \param cliOptions Options sent by CLI
 */
CopyrightState::CopyrightState(CliOptions&& cliOptions) :
  cliOptions(cliOptions),
  scanners(cliOptions.extractScanners())
{
}

/**
 * \brief Add scanner to state
 * \param sc Scanner to be added
 */
void CopyrightState::addScanner(scanner* sc)
{
  if (sc)
    scanners.push_back(unptr::shared_ptr<scanner>(sc));
}

/**
 * \brief Get available scanner \a s
 * \return List of available scanners
 */
const std::list<unptr::shared_ptr<scanner>>& CopyrightState::getScanners() const
{
  return scanners;
}

/**
 * \brief Constructor for CliOptions
 * \param verbosity Verbosity set by CLI
 * \param type      Type set by CLI
 * \param json      True to get output in JSON format
 * \param ignoreFilesWithMimeType      True to ignore files with particular mimetype
 */
CliOptions::CliOptions(int verbosity, unsigned int type, bool json, bool ignoreFilesWithMimeType) :
  verbosity(verbosity),
  optType(type),
  json(json),
  ignoreFilesWithMimeType(ignoreFilesWithMimeType),
  cliScanners()
{
}

/**
 * \brief Default constructor for CliOptions
 */
CliOptions::CliOptions() :
  verbosity(0),
  optType(ALL_TYPES),
  ignoreFilesWithMimeType(false),
  cliScanners()
{
}

/**
 * \brief Get the opt type set by CliOptions
 * \return The opt type
 */
unsigned int CliOptions::getOptType() const
{
  return optType;
}

/**
 * \brief Get the CliOptions set by user
 * \return The CliOptions
 */
const CliOptions& CopyrightState::getCliOptions() const
{
  return cliOptions;
}

/**
 * \brief Get scanner s set by CliOptions
 * \return List of scanners
 */
std::list<unptr::shared_ptr<scanner>> CliOptions::extractScanners()
{
  return std::move(cliScanners);
}

/**
 * \brief Check if verbosity is set
 * \return True if set, else false
 */
bool CliOptions::isVerbosityDebug() const
{
  return verbosity >= 1;
}

/**
 * \brief Add scanner to CliOptions
 * \param sc Scanner to be added
 */
void CliOptions::addScanner(scanner* sc)
{
  cliScanners.push_back(unptr::shared_ptr<scanner>(sc));
}

/**
 * \brief Check if JSON output is required
 * \return True if required, else false
 */
bool CliOptions::doJsonOutput() const
{
  return json;
}

/**
 * \brief Check to ignore files with particular mimetype
 * \return True if required, else false
 */
bool CliOptions::doignoreFilesWithMimeType() const
{
  return ignoreFilesWithMimeType;
}
