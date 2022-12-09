/*
 Author: Daniele Fognini
 SPDX-FileCopyrightText: © 2013-2014 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

#include "scheduler.h"

#include "common.h"
#include "database.h"

MatchCallbacks schedulerCallbacks =
  { .onNo = sched_onNoMatch,
    .onFull = sched_onFullMatch,
    .onDiff = sched_onDiffMatch,
    .onBeginOutput = sched_noop,
    .onBetweenIndividualOutputs = sched_noop,
    .onEndOutput = sched_noop,
    .ignore = sched_ignore
  };

int processUploadId(MonkState* state, int uploadId, const Licenses* licenses) {
  PGresult* fileIdResult = queryFileIdsForUpload(state->dbManager, uploadId, state->ignoreFilesWithMimeType);

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

        if (matchPFileWithLicenses(threadLocalState, pFileId, licenses, &schedulerCallbacks, DELIMITERS)) {
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

int handleSchedulerMode(MonkState* state, const Licenses* licenses) {
  /* scheduler mode */
  state->scanMode = MODE_SCHEDULER;
  queryAgentId(state, AGENT_NAME, AGENT_DESC);

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

  return 1;
}

int sched_onNoMatch(MonkState* state, const File* file) {
  return saveNoResultToDb(state->dbManager, state->agentId, file->id);
}

int sched_onFullMatch(MonkState* state, const File* file, const License* license, const DiffMatchInfo* matchInfo) {
  fo_dbManager* dbManager = state->dbManager;
  const int agentId = state->agentId;
  const long fileId = file->id;

#ifdef DEBUG
    printf("found full match between (pFile=%ld) and \"%s\" (rf_pk=%ld)\n", file->id, license->shortname, license->refId);
#endif //DEBUG

  fo_dbManager_begin(dbManager);

  int success = 0;
  long licenseFileId = saveToDb(dbManager, agentId, license->refId, fileId, 100);
  if (licenseFileId > 0) {
    success = saveDiffHighlightToDb(dbManager, matchInfo, licenseFileId);
  }

  if (success) {
    fo_dbManager_commit(dbManager);
  } else {
    fo_dbManager_rollback(dbManager);
  }
  return success;
}

int sched_onDiffMatch(MonkState* state, const File* file, const License* license, const DiffResult* diffResult) {
  fo_dbManager* dbManager = state->dbManager;
  const int agentId = state->agentId;
  const long fileId = file->id;

  unsigned short matchPercent = diffResult->percentual;

#ifdef DEBUG
    printf("found diff match between (pFile=%ld) and \"%s\" (rf_pk=%ld); ", file->id, license->shortname, license->refId);
    printf("%u%%; ", diffResult->percentual);

    char * formattedMatchArray = formatMatchArray(diffResult->matchedInfo);
    printf("diffs: {%s}\n", formattedMatchArray);
    free(formattedMatchArray);
#endif //DEBUG

  fo_dbManager_begin(dbManager);

  int success = 0;
  long licenseFileId = saveToDb(dbManager, agentId, license->refId, fileId, matchPercent);
  if (licenseFileId > 0) {
    success = saveDiffHighlightsToDb(dbManager, diffResult->matchedInfo, licenseFileId);
  }

  if (success) {
    fo_dbManager_commit(dbManager);
  } else {
    fo_dbManager_rollback(dbManager);
  }

  return success;
}

/* check if we have other results for this file.
 * We do it now to minimize races with a concurrent scan of this file:
 * the same file could be inside more than upload
 */
int sched_ignore(MonkState* state, const File* file)
{
  return hasAlreadyResultsFor(state->dbManager, state->agentId, file->id);
}

#pragma GCC diagnostic push
#pragma GCC diagnostic ignored "-Wunused-parameter"
int sched_noop(MonkState* state) {
  return 1;
}
#pragma GCC diagnostic pop
