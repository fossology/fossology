/*
 SPDX-License-Identifier: GPL-2.0-only
 Author: Dietmar Helmut Leher <helmut.leher.ext@vaillant-group.com>
 SPDX-FileCopyrightText: © 2026 Vaillant GmbH
*/
#pragma once

/**
 * @file
 * Agent constants and free utility functions for the reuser agent.
 * Mirrors OjoUtils.hpp from the ojo agent.
 */

#define AGENT_NAME "reuser"
#define AGENT_DESC "reuser agent"
#define AGENT_ARS  "reuser_ars"

#include "ReuserDatabaseHandler.hpp"
#include "ReuserState.hpp"
#include "ReuserTypes.hpp"
#include "libfossologyCPP.hpp"

extern "C" {
#include "libfossagent.h"
#include "libfossscheduler.h"
}

/** @brief Create a ReuserState from the database (registers agent key). */
ReuserState getState(fo::DbManager& dbManager);

/** @brief Query and register the agent id. Bails on failure. */
int queryAgentId(fo::DbManager& dbManager);

/** @brief Write (insert/update) an ARS record. */
int writeARS(const ReuserState& state, int arsId, int uploadId, int success,
  fo::DbManager& dbManager);

/** @brief Disconnect scheduler and exit. */
void bail(int exitval);

/** @brief Process one upload through all active reuse relationships. */
bool processUploadId(const ReuserState& /*state*/, int uploadId,
  ReuserDatabaseHandler& databaseHandler);
