/*
 SPDX-License-Identifier: GPL-2.0-only
 Author: Dietmar Helmut Leher <helmut.leher.ext@vaillant-group.com>
 SPDX-FileCopyrightText: © 2026 Vaillant GmbH
*/
#include "ReuserState.hpp"

ReuserState::ReuserState(int agentId)
  : agentId(agentId)
{
}

void ReuserState::setAgentId(int agentId)
{
  this->agentId = agentId;
}

int ReuserState::getAgentId() const
{
  return agentId;
}
