/*
 * Copyright (C) 2019, Siemens AG
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * version 2 as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
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

  public:
    bool isVerbosityDebug() const;
    bool doJsonOutput() const;

    OjoCliOptions(int verbosity, bool json);
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
