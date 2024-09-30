/*
 SPDX-FileCopyrightText: © 2024 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/
/**
 * @file
 * State and CLI options for Compatibility agent
 */

#ifndef COMPATIBILITY_AGENT_STATE_HPP
#define COMPATIBILITY_AGENT_STATE_HPP

#include "CompatibilityAgent.hpp"
#include "libfossdbmanagerclass.hpp"

using namespace std;

/**
 * @class CompatibilityCliOptions
 * @brief Store the options sent through the CLI
 */
class CompatibilityCliOptions
{
private:
  int verbosity; /**< The verbosity level */
  bool json;     /**< Whether to generate JSON output */

public:
  bool isVerbosityDebug() const;
  bool doJsonOutput() const;

  CompatibilityCliOptions(int verbosity, bool json);
  CompatibilityCliOptions();
};

/**
 * @class CompatibilityState
 * @brief Store the state of the agent
 */
class CompatibilityState
{
public:
  CompatibilityState(const int agentId,
                     const CompatibilityCliOptions& cliOptions);

  void setAgentId(const int agentId);
  int getAgentId() const;
  const CompatibilityCliOptions& getCliOptions() const;
  const CompatibilityAgent& getCompatibilityAgent() const;

private:
  int agentId;                              /**< Agent id */
  const CompatibilityCliOptions cliOptions; /**< CLI options passed */
  CompatibilityAgent compatibilityAgent;    /**< Compatibility agent object */
};

#endif // COMPATIBILITY_AGENT_STATE_HPP
