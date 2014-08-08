/*
Author: Daniele Fognini, Andreas Wuerl
Copyright (C) 2013-2014, Siemens AG

This program is free software; you can redistribute it and/or modify it under the terms of the GNU General Public License version 2 as published by the Free Software Foundation.

This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.

You should have received a copy of the GNU General Public License along with this program; if not, write to the Free Software Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 */

#include "extended.h"
#include "file_operations.h"
#include "database.h"
#include "license.h"
#include "match.h"

void matchCliFileWithLicenses(MonkState* state, GArray* licenses, int argi, char** argv) {
  File file;
  file.id = argi;
  file.fileName = argv[argi];
  file.tokens = readTokensFromFile(file.fileName, DELIMITERS);

  matchFileWithLicenses(state, &file, licenses);

  g_array_free(file.tokens, TRUE);
}

int handleArguments(MonkState* state, int argc, char** argv) {
  /* extended mode */
  PGresult* licensesResult = queryAllLicenses(state->dbManager);
  GArray* licenses = extractLicenses(state->dbManager, licensesResult);

  int threadError = 0;
  #pragma omp parallel
  {
    MonkState threadLocalStateStore = *state;
    MonkState* threadLocalState = &threadLocalStateStore;

    threadLocalState->dbManager = fo_dbManager_fork(state->dbManager);
    if (threadLocalState->dbManager) {
      #pragma omp for
      for (int fileId = 1; fileId < argc; fileId++) {
        matchCliFileWithLicenses(threadLocalState, licenses, fileId, argv);
      }
      fo_dbManager_finish(threadLocalState->dbManager);
    } else {
      threadError = 1;
    }
  }

  freeLicenseArray(licenses);
  PQclear(licensesResult);

  return !threadError;
}

void onFullMatch(File* file, License* license, DiffMatchInfo* matchInfo) {
  printf("found full match between \"%s\" and \"%s\" (rf_pk=%ld); ",
         file->fileName, license->shortname, license->refId);
  printf("matched: %zu+%zu\n", matchInfo->text.start, matchInfo->text.length);
}

void onDiffMatch(File* file, License* license, DiffResult* diffResult, unsigned short rank) {
  printf("found diff match between \"%s\" and \"%s\" (rf_pk=%ld); ",
         file->fileName, license->shortname, license->refId);
  printf("rank %u; ", rank);

  char * formattedMatchArray = formatMatchArray(diffResult->matchedInfo);
  printf("diffs: {%s}\n", formattedMatchArray);
  free(formattedMatchArray);
}
