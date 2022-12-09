/*
 SPDX-FileCopyrightText: © 2014-2015 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

#include "state.hpp"

State::State(int agentId) :
  agentId(agentId)
{
}

int State::getAgentId() const
{
  return agentId;
};

