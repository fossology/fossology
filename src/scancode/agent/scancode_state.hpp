/*
 SPDX-FileCopyrightText: © 2021 Sarita Singh <saritasingh.0425@gmail.com>

 SPDX-License-Identifier: GPL-2.0-only
*/

#ifndef SCANCODE_AGENT_STATE_HPP
#define SCANCODE_AGENT_STATE_HPP

#include "libfossdbmanagerclass.hpp"
#include "scancode_dbhandler.hpp"

using namespace std;

/**
 * class to get agent Id and CliOptions
 */
class State {
public:
  State(int agentId);
  void setCliOptions(string cliOptions);
  int getAgentId() const;
  string getCliOptions() const;

private:
  /**
   * agent Id is agent_pk in the agent table
   */
  int agentId;

  /**
   * cliOptions command line options for scancode toolkit
   */
  string cliOptions;
};

#endif // SCANCODE_AGENT_STATE_HPP