/*
Author: Daniele Fognini, Andreas Wuerl
Copyright (C) 2013-2014, Siemens AG

This program is free software; you can redistribute it and/or modify it under the terms of the GNU General Public License version 2 as published by the Free Software Foundation.

This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.

You should have received a copy of the GNU General Public License along with this program; if not, write to the Free Software Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
*/

#include <stdio.h>
#include <stdlib.h>
#include <glib.h>
#include "monk.h"
#include "extended.h"
#include "database.h"
#include "getopt.h"

int parseArguments(MonkState* state, int argc, char** argv, int* fileOptInd, long* bulkOptId) {
  int c;
  state->verbosity = 0;
  while ((c = getopt(argc, argv, "VvhB:")) != -1) {
    switch (c) {
      case 'v':
        state->verbosity++;
        break;
      case 'B':
        *bulkOptId = atol(optarg);
        break;
       case 'V':
#ifdef SVN_REV_S
        printf(AGENT_NAME " version " VERSION_S " r(" SVN_REV_S ")\n");
#else
        printf(AGENT_NAME " (no version available)\n");
#endif
        return 0;
      case 'h':
      default:
        printf("Usage: %s [options] -- [file [file [...]]\n", argv[0]);
        printf("  -h   :: help (print this message), then exit.\n"
               "  -c   :: specify the directory for the system configuration.\n"
               "  -v   :: verbose output.\n"
               "  file :: scan file and print licenses detected within it.\n"
               "  no file :: process data from the scheduler.\n"
               "  -V   :: print the version info, then exit.\n");
        return 0;
    }
  }
  *fileOptInd = optind;
  return 1;
}

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