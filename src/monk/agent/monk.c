/*
Author: Daniele Fognini, Andreas Wuerl
Copyright (C) 2013-2014, Siemens AG

This program is free software; you can redistribute it and/or modify it under the terms of the GNU General Public License version 2 as published by the Free Software Foundation.

This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.

You should have received a copy of the GNU General Public License along with this program; if not, write to the Free Software Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
*/

#include "monk.h"

#include "license.h"
#include "scheduler.h"
#include "cli.h"
#include "extended.h"

MatchCallbacks schedulerCallbacks = {NULL, sched_onNoMatch, sched_onFullMatch, sched_onDiffMatch};

int processUploadId(MonkState* state, int uploadId, Licenses* licenses) {
  PGresult* fileIdResult = queryFileIdsForUpload(state->dbManager, uploadId);

  if (!fileIdResult)
    return 0;

  if (PQntuples(fileIdResult) == 0) {
    PQclear(fileIdResult);
    fo_scheduler_heart(0);
    return 1;
  }

  int threadError = 0;
#ifdef MONK_MULTI_THREAD
  #pragma omp parallel
#endif
  {
    MonkState threadLocalStateStore = *state;
    MonkState* threadLocalState = &threadLocalStateStore;

    threadLocalState->dbManager = fo_dbManager_fork(state->dbManager);
    if (threadLocalState->dbManager) {
      int count = PQntuples(fileIdResult);
#ifdef MONK_MULTI_THREAD
      #pragma omp for schedule(dynamic)
#endif
      for (int i = 0; i < count; i++) {
        if (threadError)
          continue;

        long pFileId = atol(PQgetvalue(fileIdResult, i, 0));

        if ((pFileId <= 0) || hasAlreadyResultsFor(threadLocalState->dbManager, threadLocalState->agentId, pFileId))
        {
          fo_scheduler_heart(0);
          continue;
        }

        if (matchPFileWithLicenses(threadLocalState, pFileId, licenses, &schedulerCallbacks)) {
          fo_scheduler_heart(1);
        } else {
          fo_scheduler_heart(0);
          threadError = 1;
        }
      }
      fo_dbManager_finish(threadLocalState->dbManager);
    } else {
      threadError = 1;
    }
  }
  PQclear(fileIdResult);

  return !threadError;
}

int main(int argc, char** argv) {
  MonkState stateStore;
  MonkState* state = &stateStore;

  fo_scheduler_connect_dbMan(&argc, argv, &(state->dbManager));

  if (argc > 1) {
    if (!handleCliMode(state, argc, argv))
      bail(state, 3);
  } else {
    /* scheduler mode */
    state->scanMode = MODE_SCHEDULER;
    queryAgentId(state, AGENT_NAME, AGENT_DESC);

    PGresult* licensesResult = queryAllLicenses(state->dbManager);
    Licenses* licenses = extractLicenses(state->dbManager, licensesResult, MIN_ADJACENT_MATCHES, MAX_LEADING_DIFF);

    while (fo_scheduler_next() != NULL) {
      int uploadId = atoi(fo_scheduler_current());

      if (uploadId == 0) continue;

      int arsId = fo_WriteARS(fo_dbManager_getWrappedConnection(state->dbManager),
                              0, uploadId, state->agentId, AGENT_ARS, NULL, 0);

      if (arsId<=0)
        bail(state, 1);

      if (!processUploadId(state, uploadId, licenses))
        bail(state, 2);

      fo_WriteARS(fo_dbManager_getWrappedConnection(state->dbManager),
                  arsId, uploadId, state->agentId, AGENT_ARS, NULL, 1);
    }
    fo_scheduler_heart(0);

    licenses_free(licenses);
    PQclear(licensesResult);
  }

  scheduler_disconnect(state, 0);
  return 0;
}
