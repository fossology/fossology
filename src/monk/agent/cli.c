/*
Author: Daniele Fognini, Andreas Wuerl
Copyright (C) 2013-2014, Siemens AG

This program is free software; you can redistribute it and/or modify it under the terms of the GNU General Public License version 2 as published by the Free Software Foundation.

This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.

You should have received a copy of the GNU General Public License along with this program; if not, write to the Free Software Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
*/

#include "cli.h"
#include "file_operations.h"
#include "database.h"
#include "license.h"
#include "match.h"
#include "extended.h"
#include "monk.h"

MatchCallbacks cliCallbacks = {NULL, cli_onNoMatch, cli_onFullMatch, cli_onDiff};

int matchCliFileWithLicenses(MonkState* state, Licenses* licenses, int argi, char** argv) {
  File file;
  file.id = argi;
  file.fileName = argv[argi];
  if (!readTokensFromFile(file.fileName, &(file.tokens), DELIMITERS))
    return 0;

  int result = matchFileWithLicenses(state, &file, licenses, &cliCallbacks);

  g_array_free(file.tokens, TRUE);

  return result;
}

int handleCliMode(MonkState* state, int argc, char** argv) {
  int fileOptInd;
  long bulkOptId = -1;
  if (!parseArguments(state, argc, argv, &fileOptInd, &bulkOptId))
    return 0;

  state->scanMode = MODE_CLI;

  PGresult* licensesResult = queryAllLicenses(state->dbManager);
  Licenses* licenses = extractLicenses(state->dbManager, licensesResult, MIN_ADJACENT_MATCHES, MAX_LEADING_DIFF);

  int threadError = 0;
#ifdef MONK_MULTI_THREAD
  #pragma omp parallel
#endif
  {
    MonkState threadLocalStateStore = *state;
    MonkState* threadLocalState = &threadLocalStateStore;

    threadLocalState->dbManager = fo_dbManager_fork(state->dbManager);
    if (threadLocalState->dbManager) {
#ifdef MONK_MULTI_THREAD
      #pragma omp for schedule(dynamic)
#endif
      for (int fileId = fileOptInd; fileId < argc; fileId++) {
        matchCliFileWithLicenses(threadLocalState, licenses, fileId, argv);
      }
      fo_dbManager_finish(threadLocalState->dbManager);
    } else {
      threadError = 1;
    }
  }

  licenses_free(licenses);
  PQclear(licensesResult);

  return !threadError;
}

int cli_onNoMatch(MonkState* state, File* file) {
  if (state->verbosity >= 1) {
    printf("File %s contains license(s) No_license_found\n", file->fileName);
  }
  return 1;
}

int cli_onFullMatch(MonkState* state, File* file, License* license, DiffMatchInfo* matchInfo) {
  if (state->scanMode != MODE_CLI)
    return 0;

  printf("found full match between \"%s\" and \"%s\" (rf_pk=%ld); ",
         file->fileName, license->shortname, license->refId);
  printf("matched: %zu+%zu\n", matchInfo->text.start, matchInfo->text.length);
  return 1;
}

int cli_onDiff(MonkState* state, File* file, License* license, DiffResult* diffResult) {
  if (state->scanMode != MODE_CLI)
    return 0;

  unsigned short rank = diffResult->percentual;

  printf("found diff match between \"%s\" and \"%s\" (rf_pk=%ld); ",
         file->fileName, license->shortname, license->refId);
  printf("rank %u; ", rank);

  char * formattedMatchArray = formatMatchArray(diffResult->matchedInfo);
  printf("diffs: {%s}\n", formattedMatchArray);
  free(formattedMatchArray);
  return 1;
}
