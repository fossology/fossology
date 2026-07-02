/*
 SPDX-License-Identifier: GPL-2.0-only
 Author: Saksham Mishra <sakshammishra112@gmail.com>
 SPDX-FileCopyrightText: © 2026 Saksham Mishra
*/
#include "EnhancedReuserState.hpp"

EnhancedReuserState::EnhancedReuserState(int agentId)
  : agentId(agentId)
{
}

void EnhancedReuserState::setAgentId(int agentId)
{
  this->agentId = agentId;
}

int EnhancedReuserState::getAgentId() const
{
  return agentId;
}
