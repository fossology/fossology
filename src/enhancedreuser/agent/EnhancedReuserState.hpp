/*
 SPDX-License-Identifier: GPL-2.0-only
 Author: Saksham Mishra <sakshammishra112@gmail.com>
 SPDX-FileCopyrightText: © 2026 Saksham Mishra
*/
#pragma once

class EnhancedReuserState
{
public:
  explicit EnhancedReuserState(int agentId);

  void setAgentId(int agentId);
  int  getAgentId() const;

private:
  int agentId;
};
