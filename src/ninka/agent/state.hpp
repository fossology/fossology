/*
 SPDX-FileCopyrightText: © 2014-2015 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

#ifndef NINKA_AGENT_STATE_HPP
#define NINKA_AGENT_STATE_HPP

#include "databasehandler.hpp"
#include "libfossdbmanagerclass.hpp"

using namespace std;

class State
{
public:
  State(int agentId);

  int getAgentId() const;

private:
  int agentId;
};

#endif // NINKA_AGENT_STATE_HPP
