/*
Author: Daniele Fognini, Andreas Wuerl
Copyright (C) 2013-2014, Siemens AG

This program is free software; you can redistribute it and/or modify it under the terms of the GNU General Public License version 2 as published by the Free Software Foundation.

This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.

You should have received a copy of the GNU General Public License along with this program; if not, write to the Free Software Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
*/

#include "cli.h"

#include "monk.h"
#include "file_operations.h"
#include "database.h"
#include "match.h"
#include <getopt.h>

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

int parseArguments(MonkState* state, int argc, char** argv, int* fileOptInd)
{
  int c;
  state->verbosity = 0;
  while ((c = getopt(argc, argv, "VvhB:")) != -1) {
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

int handleCliMode(MonkState* state, Licenses* licenses, int argc, char** argv) {
  int fileOptInd;
  if (!parseArguments(state, argc, argv, &fileOptInd))
    return 0;

  state->scanMode = MODE_CLI;

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

