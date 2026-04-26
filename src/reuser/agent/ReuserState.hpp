/*
 SPDX-License-Identifier: GPL-2.0-only
 Author: Dietmar Helmut Leher <helmut.leher.ext@vaillant-group.com>
 SPDX-FileCopyrightText: © 2026 Vaillant GmbH
*/
#pragma once

/**
 * @class ReuserState
 * @brief Holds the runtime state of the reuser agent (agent id).
 *
 * Mirrors OjoState from the ojo agent.
 */
class ReuserState
{
public:
  explicit ReuserState(int agentId);

  void setAgentId(int agentId);
  int  getAgentId() const;

private:
  int agentId;
};
