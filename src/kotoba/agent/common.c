/*
 Author: Harshit Gandhi
 SPDX-FileCopyrightText: © 2025 Harshit Gandhi <gandhiharshit716@gmail.com>

 SPDX-License-Identifier: GPL-2.0-only
*/

#include <glib.h>
#include "kotoba.h"
#include "common.h"
#include <libfossology.h>

void scheduler_disconnect(KotobaState* state, int exitval) {
  fo_dbManager_finish(state->dbManager);
  fo_scheduler_disconnect(exitval);
}

void bail(KotobaState* state, int exitval) {
  if(state->dbManager != NULL) {
    scheduler_disconnect(state, exitval);
  }
  exit(exitval);
}

void queryAgentId(KotobaState* state, const char* agentName, const char* agentDesc) {
  char* commitHash = fo_sysconfig(agentName, "COMMIT_HASH");
  char* version = fo_sysconfig(agentName, "VERSION");
  gchar* agentRevision = g_strdup_printf("%s.%s", version, commitHash);

  int agentId = fo_GetAgentKey(fo_dbManager_getWrappedConnection(state->dbManager),
                               agentName, 0, agentRevision, agentDesc);
  g_free(agentRevision);

  if (agentId > 0)
    state->agentId = agentId;
  else
    bail(state, 1);
}
