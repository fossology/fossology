/*
 SPDX-FileCopyrightText: © 2019, 2021 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/
/**
 * @file
 * State and CLI options for OJO agent
 */

#ifndef OJOS_AGENT_STATE_HPP
#define OJOS_AGENT_STATE_HPP

#include "libfossdbmanagerclass.hpp"
#include "OjoAgent.hpp"

using namespace std;

/**
 * @class OjoCliOptions
 * @brief Store the options sent through the CLI
 */
class OjoCliOptions
{
  private:
    int verbosity;  /**< The verbosity level */
    bool json;      /**< Whether to generate JSON output */
    bool ignoreFilesWithMimeType; /**< Ignore files with particular mimetype */
    int userId;     /**< User running the agent */
    int groupId;    /**< Group running the agent */

  public:
    void setUserId(const int);
    void setGroupId(const int);

    bool isVerbosityDebug() const;
    bool doJsonOutput() const;
    bool doignoreFilesWithMimeType() const;
    int getUserId() const;
    int getGroupId() const;

    OjoCliOptions(int verbosity, bool json, bool ignoreFilesWithMimeType);
    OjoCliOptions();
};

/**
 * @class OjoState
 * @brief Store the state of the agent
 */
class OjoState
{
  public:
    OjoState(const int agentId, const OjoCliOptions &cliOptions);

    void setAgentId(const int agentId);
    int getAgentId() const;
    const OjoCliOptions& getCliOptions() const;
    const OjoAgent& getOjoAgent() const;

  private:
    int agentId;                      /**< Agent id */
    const OjoCliOptions cliOptions;   /**< CLI options passed */
    const OjoAgent ojoAgent;          /**< Ojo agent object */
};

#endif // OJOS_AGENT_STATE_HPP
