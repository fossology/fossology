/*
Author: Daniele Fognini, Andreas Wuerl
Copyright (C) 2013-2014, Siemens AG

 This program is free software; you can redistribute it and/or
 modify it under the terms of the GNU General Public License
 version 2 as published by the Free Software Foundation.

 This program is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 GNU General Public License for more details.

 You should have received a copy of the GNU General Public License along
 with this program; if not, write to the Free Software Foundation, Inc.,
 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 */
#include "cli.h"
#include "file_operations.h"
#include "database.h"
#include "match.h"

MatchCallbacks cliCallbacks =
  { .onNo = cli_onNoMatch,
    .onFull = cli_onFullMatch,
    .onBeginOutput = cli_onBeginOutput,
    .onBetweenIndividualOutputs = cli_onBetweenIndividualOutputs,
    .onEndOutput = cli_onEndOutput,
    .onDiff = cli_onDiff
  };

int matchCliFileWithLicenses(MonkState* state, const Licenses* licenses, int argi, char** argv) {
  File file;
  file.id = argi;
  file.fileName = argv[argi];
  if (!readTokensFromFile(file.fileName, &(file.tokens), DELIMITERS))
    return 0;

  int result = matchFileWithLicenses(state, &file, licenses, &cliCallbacks);

  tokens_free(file.tokens);

  return result;
}

int handleCliMode(MonkState* state, const Licenses* licenses, int argc, char** argv, int fileOptInd) {
#ifdef MONK_MULTI_THREAD
  #pragma omp parallel
#endif
  {
    MonkState threadLocalStateStore = *state;
    MonkState* threadLocalState = &threadLocalStateStore;

#ifdef MONK_MULTI_THREAD
    #pragma omp for schedule(dynamic)
#endif
    for (int fileId = fileOptInd; fileId < argc; fileId++) {
      matchCliFileWithLicenses(threadLocalState, licenses, fileId, argv);
    }
  }

  return 1;
}

int cli_onNoMatch(MonkState* state, const File* file) {
  if (state->verbosity >= 1) {
    printf("File %s contains license(s) No_license_found\n", file->fileName);
  }
  if (state->json) {
    printf("{\"type\":\"no-match\"}");
  }
  return 1;
}

int cli_onFullMatch(MonkState* state, const File* file, const License* license, const DiffMatchInfo* matchInfo) {
  if (state->json) {
    printf("{\"type\":\"full\",\"license\":\"%s\",\"ref-pk\":%ld,\"matched\":\"%zu+%zu\"}",
           license->shortname, license->refId,
           matchInfo->text.start, matchInfo->text.length);
  } else {
    printf("found full match between \"%s\" and \"%s\" (rf_pk=%ld); matched: %zu+%zu\n",
           file->fileName, license->shortname, license->refId,
           matchInfo->text.start, matchInfo->text.length);
  }
  return 1;
}

int cli_onDiff(MonkState* state, const File* file, const License* license, const DiffResult* diffResult) {
  unsigned short rank = diffResult->percentual;

  char * formattedMatchArray = formatMatchArray(diffResult->matchedInfo);

  if (state->json) {
    printf("{\"type\":\"diff\",\"license\":\"%s\",\"ref-pk\":%ld,\"rank\":%u,\"diffs\":\"%s\"}",
           license->shortname, license->refId,
           rank, formattedMatchArray);
  } else {
    printf("found diff match between \"%s\" and \"%s\" (rf_pk=%ld); rank %u; diffs: {%s}\n",
           file->fileName, license->shortname, license->refId,
           rank,
           formattedMatchArray);
  }

  free(formattedMatchArray);
  return 1;
}


int cli_onBeginOutput(MonkState* state) {
  if (state->json) {
    printf("[");
  }
  return 1;
}
int cli_onBetweenIndividualOutputs(MonkState* state) {
  if (state->json) {
    printf(",");
  }
  return 1;
}
int cli_onEndOutput(MonkState* state) {
  if (state->json) {
    printf("]");
  }
  return 1;
}

