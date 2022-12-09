/*
 SPDX-FileCopyrightText: © 2019, 2021 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

#include "OjoState.hpp"

/**
 * Constructor for State
 * @param agentId    Agent ID
 * @param cliOptions CLI options passed
 */
OjoState::OjoState(const int agentId, const OjoCliOptions &cliOptions) :
  agentId(agentId), cliOptions(cliOptions)
{
}

/**
 * Get the agent id
 * @return Agent id
 */
void OjoState::setAgentId(const int agentId)
{
  this->agentId = agentId;
}

/**
 * Get the agent id
 * @return Agent id
 */
int OjoState::getAgentId() const
{
  return agentId;
}

/**
 * Get the OjoAgent reference
 * @return OjoAgent reference
 */
const OjoAgent& OjoState::getOjoAgent() const
{
  return ojoAgent;
}

/**
 * @brief Constructor for OjoCliOptions
 * @param verbosity Verbosity set by CLI
 * @param json      True to get output in JSON format
 * @param ignoreFilesWithMimeType To ignore files with particular mimetype
 */
OjoCliOptions::OjoCliOptions(int verbosity, bool json, bool ignoreFilesWithMimeType) :
    verbosity(verbosity), json(json), ignoreFilesWithMimeType(ignoreFilesWithMimeType),
    userId(-1), groupId(-1)
{
}

/**
 * @brief Default constructor for OjoCliOptions
 */
OjoCliOptions::OjoCliOptions() :
    verbosity(0), json(false), ignoreFilesWithMimeType(false), userId(-1),
    groupId(-1)
{
}

/**
 * @brief Get the OjoCliOptions set by user
 * @return The OjoCliOptions
 */
const OjoCliOptions& OjoState::getCliOptions() const
{
  return cliOptions;
}

/**
 * @brief Check if verbosity is set
 * @return True if set, else false
 */
bool OjoCliOptions::isVerbosityDebug() const
{
  return verbosity >= 1;
}

/**
 * @brief Check if JSON output is required
 * @return True if required, else false
 */
bool OjoCliOptions::doJsonOutput() const
{
  return json;
}

/**
 * @brief Check ignore files with particular mimetype is required
 * @return True if required, else false
 */
bool OjoCliOptions::doignoreFilesWithMimeType() const
{
  return ignoreFilesWithMimeType;
}

/**
 * @brief Set the user id
 * @param userId User id
 */
void OjoCliOptions::setUserId(const int userId)
{
  this->userId = userId;
}

/**
 * @brief Set the group id
 * @param groupId Group id
 */
void OjoCliOptions::setGroupId(const int groupId)
{
  this->groupId = groupId;
}

/**
 * @brief Get the user running the agent
 * @return User id if available, -1 otherwise
 */
int OjoCliOptions::getUserId() const
{
  return userId;
}

/**
 * @brief Get the group running the agent
 * @return Group id if available, -1 otherwise
 */
int OjoCliOptions::getGroupId() const
{
  return groupId;
}
