/*
 SPDX-FileCopyrightText: © 2024 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

#include "CompatibilityState.hpp"

/**
 * Constructor for State
 * @param agentId    Agent ID
 * @param cliOptions CLI options passed
 */
CompatibilityState::CompatibilityState(
    const int agentId, const CompatibilityCliOptions& cliOptions) :
    agentId(agentId),
    cliOptions(cliOptions),
    compatibilityAgent(
        CompatibilityAgent(agentId, cliOptions.isVerbosityDebug()))
{
}

/**
 * Get the agent id
 * @return Agent id
 */
void CompatibilityState::setAgentId(const int agentId)
{
  this->agentId = agentId;
  this->compatibilityAgent.setAgentId(agentId);
}

/**
 * Get the agent id
 * @return Agent id
 */
int CompatibilityState::getAgentId() const
{
  return agentId;
}

/**
 * Get the CompatibilityAgent reference
 * @return CompatibilityAgent reference
 */
const CompatibilityAgent& CompatibilityState::getCompatibilityAgent() const
{
  return compatibilityAgent;
}

/**
 * @brief Constructor for CompatibilityCliOptions
 * @param verbosity Verbosity set by CLI
 * @param json      True to get output in JSON format
 * @param autoConclude True to auto conclude fully compatible files
 */
CompatibilityCliOptions::CompatibilityCliOptions(int verbosity, bool json, bool autoConclude) :
    verbosity(verbosity), json(json), autoConclude(autoConclude)
{
}

/**
 * @brief Default constructor for CompatibilityCliOptions
 */
CompatibilityCliOptions::CompatibilityCliOptions() : verbosity(0), json(false), autoConclude(false)
{
}

/**
 * @brief Get the CompatibilityCliOptions set by user
 * @return The CompatibilityCliOptions
 */
const CompatibilityCliOptions& CompatibilityState::getCliOptions() const
{
  return cliOptions;
}

/**
 * @brief Check if verbosity is set
 * @return True if set, else false
 */
bool CompatibilityCliOptions::isVerbosityDebug() const
{
  return verbosity >= 1;
}

/**
 * @brief Check if JSON output is required
 * @return True if required, else false
 */
bool CompatibilityCliOptions::doJsonOutput() const
{
  return json;
}

/**
 * @brief Check if auto-conclude is required
 * @return True if required, else false
 */
bool CompatibilityCliOptions::doAutoConclude() const
{
  return autoConclude;
}
