/*
Author: Daniele Fognini
Copyright (C) 2013-2014, Siemens AG

This program is free software; you can redistribute it and/or modify it under the terms of the GNU General Public License version 2 as published by the Free Software Foundation.

This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.

You should have received a copy of the GNU General Public License along with this program; if not, write to the Free Software Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
*/

#include "scheduler.h"

#include "database.h"

/* check if we have other results for this file.
 * We do it now to minimize races with a concurrent scan of this file:
 * the same file could be inside more than upload
 */
#define beginAndCheckPrevious(dbManager,agentId,fileId) \
  fo_dbManager_begin(dbManager); \
  if (hasAlreadyResultsFor(dbManager, agentId, fileId)) \
  {\
    fo_dbManager_commit(dbManager);\
    return 1;\
  }

#define commitAndReturn(dbManager,success) \
  if (success) \
    return fo_dbManager_commit(dbManager); \
  else { \
    fo_dbManager_rollback(dbManager); \
    return 0; \
  }

int sched_onNoMatch(MonkState* state, File* file) {
  fo_dbManager* dbManager = state->dbManager;
  const int agentId = state->agentId;
  const long fileId = file->id;

  beginAndCheckPrevious(dbManager, agentId, fileId);

  commitAndReturn(dbManager, saveNoResultToDb(dbManager, agentId, fileId));
}

int sched_onFullMatch(MonkState* state, File* file, License* license, DiffMatchInfo* matchInfo) {
  fo_dbManager* dbManager = state->dbManager;
  const int agentId = state->agentId;
  const long fileId = file->id;

#ifdef DEBUG
    printf("found full match between (pFile=%ld) and \"%s\" (rf_pk=%ld)\n", file->id, license->shortname, license->refId);
#endif //DEBUG

  beginAndCheckPrevious(dbManager, agentId, fileId);

  int success = 0;

  long licenseFileId = saveToDb(dbManager, agentId, license->refId, fileId, 100);
  if (licenseFileId > 0) {
    success = saveDiffHighlightToDb(dbManager, matchInfo, licenseFileId);
  }

  commitAndReturn(dbManager, success);
}

int sched_onDiffMatch(MonkState* state, File* file, License* license, DiffResult* diffResult) {
  fo_dbManager* dbManager = state->dbManager;
  const int agentId = state->agentId;
  const long fileId = file->id;

  unsigned short matchPercent = diffResult->percentual;
  convertToAbsolutePositions(diffResult->matchedInfo, file->tokens, license->tokens);

#ifdef DEBUG
    printf("found diff match between (pFile=%ld) and \"%s\" (rf_pk=%ld); ", file->id, license->shortname, license->refId);
    printf("%u%%; ", diffResult->percentual);

    char * formattedMatchArray = formatMatchArray(diffResult->matchedInfo);
    printf("diffs: {%s}\n", formattedMatchArray);
    free(formattedMatchArray);
#endif //DEBUG

  beginAndCheckPrevious(dbManager, agentId, fileId);

  int success = 0;
  long licenseFileId = saveToDb(dbManager, agentId, license->refId, fileId, matchPercent);
  if (licenseFileId > 0) {
    success = saveDiffHighlightsToDb(dbManager, diffResult->matchedInfo, licenseFileId);
  }

  commitAndReturn(dbManager, success);
}
