/*
 SPDX-License-Identifier: GPL-2.0-only
 Author: Saksham Mishra <sakshammishra112@gmail.com>
 SPDX-FileCopyrightText: © 2026 Saksham Mishra
*/
#pragma once

#define AGENT_NAME "enhancedreuser"
#define AGENT_DESC "enhancedreuser agent"
#define AGENT_ARS  "enhancedreuser_ars"

#include "EnhancedReuserDatabaseHandler.hpp"
#include "EnhancedReuserState.hpp"
#include "EnhancedReuserTypes.hpp"
#include "libfossologyCPP.hpp"

extern "C" {
#include "libfossagent.h"
#include "libfossscheduler.h"
}

EnhancedReuserState getState(fo::DbManager& dbManager);
int queryAgentId(fo::DbManager& dbManager);
int writeARS(const EnhancedReuserState& state, int arsId, int uploadId, int success,
  fo::DbManager& dbManager, const char* arsStatus = nullptr);
void bail(int exitval);
bool processUploadId(const EnhancedReuserState& state, int uploadId,
  EnhancedReuserDatabaseHandler& databaseHandler);
