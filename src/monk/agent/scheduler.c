/*
 Author: Daniele Fognini
 SPDX-FileCopyrightText: © 2013-2014 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

#include "scheduler.h"

#include <stdio.h>
#include <stdlib.h>
#include <string.h>
#include "common.h"
#include "database.h"

/**
 * @brief Thread-local file content cache for offset conversion.
 *
 * During match processing monk may invoke sched_onFullMatch /
 * sched_onDiffMatch many times for the same file (once per license match).
 * Re-reading the file from disk on every callback is extremely expensive
 * for large files with many matches and can cause the scheduler watchdog
 * to kill the agent.  This cache stores the last-read file content per
 * thread so that it is reused across callbacks for the same file.
 *
 * The cache is invalidated (freed) when a new file is encountered.
 * Call flushFileCache() after processing each file to free memory promptly.
 */
static __thread unsigned char* s_cachedContent = NULL;
static __thread size_t s_cachedSize = 0;
static __thread char* s_cachedName = NULL;

/**
 * @brief Get file content, using a thread-local cache.
 *
 * Returns a pointer to the cached file content (caller MUST NOT free it).
 * Returns NULL if the file could not be read or is too large.
 */
static unsigned char* getCachedFileBytes(const char* fileName, size_t* outSize)
{
  if (s_cachedName && strcmp(s_cachedName, fileName) == 0)
  {
    *outSize = s_cachedSize;
    return s_cachedContent;
  }

  /* Invalidate old cache */
  free(s_cachedContent);
  g_free(s_cachedName);
  s_cachedContent = NULL;
  s_cachedSize = 0;
  s_cachedName = NULL;

  s_cachedContent = fo_readFileBytes(fileName, &s_cachedSize);
  if (s_cachedContent)
  {
    s_cachedName = g_strdup(fileName);
    *outSize = s_cachedSize;
  }
  else
  {
    *outSize = 0;
  }
  return s_cachedContent;
}

/**
 * @brief Flush the thread-local file cache.
 *
 * Should be called after all match callbacks for a given file have completed
 * to free memory.  It is safe to call this multiple times.
 */
static void flushFileCache(void)
{
  free(s_cachedContent);
  g_free(s_cachedName);
  s_cachedContent = NULL;
  s_cachedSize = 0;
  s_cachedName = NULL;
}

/**
 * @brief Read a file into a malloc'd buffer (legacy wrapper).
 *
 * Used by monkbulk.c which manages its own file reading lifetime.
 */
unsigned char* readFileBytes(const char* fileName, size_t* outSize)
{
  return fo_readFileBytes(fileName, outSize);
}

/**
 * @brief Convert a DiffPoint's byte offsets to UChar16 offsets.
 *
 * @param pt          DiffPoint to convert (modified in place)
 * @param fileContent UTF-8 file content
 * @param fileSize    Total number of bytes in fileContent
 */
void convertDiffPointToUChar16(DiffPoint* pt,
    const unsigned char* fileContent, size_t fileSize)
{
  size_t byteStart = pt->start;
  size_t byteEnd   = byteStart + pt->length;

  if (byteStart > fileSize) byteStart = fileSize;
  if (byteEnd   > fileSize) byteEnd   = fileSize;

  size_t charStart  = fo_utf8ByteLenToUChar16Len(fileContent, byteStart);
  size_t charEnd    = fo_utf8ByteLenToUChar16Len(fileContent, byteEnd);

  pt->start  = charStart;
  pt->length = (charEnd >= charStart) ? (charEnd - charStart) : 0;
}

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
        flushFileCache();
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

  /* Convert byte offsets to UChar16 offsets so that the stored positions
   * are consistent with the ICU-based copyright agent output.
   * NOTE: only .text (scanned file positions) is converted. The .search
   * field holds positions in the reference license text, which is virtually
   * always ASCII (byte == UChar16). */
  DiffMatchInfo convertedInfo = *matchInfo;
  size_t fileSize = 0;
  unsigned char* fileContent = getCachedFileBytes(file->fileName, &fileSize);
  if (fileContent && fileSize > 0)
  {
    convertDiffPointToUChar16(&convertedInfo.text, fileContent, fileSize);
  }

  int success = 0;
  long licenseFileId = saveToDb(dbManager, agentId, license->refId, fileId, 100);
  if (licenseFileId > 0) {
    success = saveDiffHighlightToDb(dbManager, &convertedInfo, licenseFileId);
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

  /* Read file once and convert all byte offsets in matchedInfo to UChar16 offsets
   * so the stored positions match the ICU-based copyright agent output.
   * NOTE: only .text (scanned file positions) is converted. The .search
   * field holds positions in the reference license text, which is virtually
   * always ASCII (byte == UChar16). */
  size_t fileSize = 0;
  unsigned char* fileContent = getCachedFileBytes(file->fileName, &fileSize);

  GArray* convertedInfo = NULL;
  const GArray* infoToSave = diffResult->matchedInfo;

  if (fileContent && fileSize > 0)
  {
    size_t len = diffResult->matchedInfo->len;
    convertedInfo = g_array_sized_new(FALSE, FALSE, sizeof(DiffMatchInfo), len);
    for (size_t i = 0; i < len; i++)
    {
      DiffMatchInfo entry = g_array_index(diffResult->matchedInfo, DiffMatchInfo, i);
      convertDiffPointToUChar16(&entry.text, fileContent, fileSize);
      g_array_append_val(convertedInfo, entry);
    }
    infoToSave = convertedInfo;
  }

  int success = 0;
  long licenseFileId = saveToDb(dbManager, agentId, license->refId, fileId, matchPercent);
  if (licenseFileId > 0) {
    success = saveDiffHighlightsToDb(dbManager, infoToSave, licenseFileId);
  }

  if (convertedInfo)
    g_array_free(convertedInfo, TRUE);

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
