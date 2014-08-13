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
#include "getopt.h"

void matchCliFileWithLicenses(MonkState* state, GArray* licenses, int argi, char** argv) {
  File file;
  file.id = argi;
  file.fileName = argv[argi];
  file.tokens = readTokensFromFile(file.fileName, DELIMITERS);

  matchFileWithLicenses(state, &file, licenses);

  g_array_free(file.tokens, TRUE);
}

int parseArguments(MonkState* state, int argc, char** argv, int* fileOptInd) {
  int c;
  state->verbosity = 0;
  while ((c = getopt(argc, argv, "Vvh")) != -1)
  {
    switch (c) {
      case 'v':
        state->verbosity++;
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

int handleArguments(MonkState* state, int argc, char** argv) {
  int fileOptInd;
  if (!parseArguments(state, argc, argv, &fileOptInd))
    return 0;

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
      for (int fileId = fileOptInd; fileId < argc; fileId++) {
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
void onNoMatch(File* file) {
  printf("found no match for \"%s\"\n", file->fileName);
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
