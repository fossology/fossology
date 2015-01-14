/*
Author: Daniele Fognini
Copyright (C) 2013-2014, Siemens AG

This program is free software; you can redistribute it and/or modify it under the terms of the GNU General Public License version 2 as published by the Free Software Foundation.

This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.

You should have received a copy of the GNU General Public License along with this program; if not, write to the Free Software Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
*/

#include "scheduler.h"

#include "database.h"
#include "monk.h"

int sched_onNoMatch(MonkState* state, File* file) {
  return saveNoResultToDb(state->dbManager, state->agentId, file->id);
}

int sched_onFullMatch(MonkState* state, File* file, License* license, DiffMatchInfo* matchInfo) {
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

int sched_onDiffMatch(MonkState* state, File* file, License* license, DiffResult* diffResult) {
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
int sched_ignore(MonkState* state, File* file)
{
  return hasAlreadyResultsFor(state->dbManager, state->agentId, file->id);
}
