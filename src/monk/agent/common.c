/*
Author: Daniele Fognini, Andreas Wuerl
Copyright (C) 2013-2014, Siemens AG

This program is free software; you can redistribute it and/or modify it under the terms of the GNU General Public License version 2 as published by the Free Software Foundation.

This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.

You should have received a copy of the GNU General Public License along with this program; if not, write to the Free Software Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
*/

#include <glib.h>
#include "monk.h"
#include "common.h"
#include <libfossology.h>

void scheduler_disconnect(MonkState* state, int exitval) {
  fo_dbManager_finish(state->dbManager);
  fo_scheduler_disconnect(exitval);
}

void bail(MonkState* state, int exitval) {
  scheduler_disconnect(state, exitval);
  exit(exitval);
}

void queryAgentId(MonkState* state, const char* agentName, const char* agentDesc) {
  char* svnRev = fo_sysconfig(agentName, "SVN_REV");
  char* version = fo_sysconfig(agentName, "VERSION");
  gchar* agentRevision = g_strdup_printf("%s.%s", version, svnRev);

  int agentId = fo_GetAgentKey(fo_dbManager_getWrappedConnection(state->dbManager),
                               agentName, 0, agentRevision, agentDesc);
  g_free(agentRevision);

  if (agentId > 0)
    state->agentId = agentId;
  else
    bail(state, 1);
}