/*
 Author: Harshit Gandhi
 SPDX-FileCopyrightText: © 2025 Harshit Gandhi <gandhiharshit716@gmail.com>

 SPDX-License-Identifier: GPL-2.0-only
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

int matchCliFileWithLicenses(KotobaState* state, const Licenses* licenses, int argi, char** argv) {
  File file;
  file.id = argi;
  file.fileName = argv[argi];
  if (!readTokensFromFile(file.fileName, &(file.tokens), DELIMITERS))
    return 0;

  int result = matchFileWithLicenses(state, &file, licenses, &cliCallbacks);

  tokens_free(file.tokens);

  return result;
}

int handleCliMode(KotobaState* state, const Licenses* licenses, int argc, char** argv, int fileOptInd) {
#ifdef KOTOBA_MULTI_THREAD
  #pragma omp parallel
#endif
  {
    KotobaState threadLocalStateStore = *state;
    KotobaState* threadLocalState = &threadLocalStateStore;

#ifdef KOTOBA_MULTI_THREAD
    #pragma omp for schedule(dynamic)
#endif
    for (int fileId = fileOptInd; fileId < argc; fileId++) {
      matchCliFileWithLicenses(threadLocalState, licenses, fileId, argv);
    }
  }

  return 1;
}

int cli_onNoMatch(KotobaState* state, const File* file) {
  if (state->verbosity >= 1) {
    printf("File %s contains license(s) No_license_found\n", file->fileName);
  }
  if (state->json) {
    printf("{\"type\":\"no-match\"}");
  }
  return 1;
}

int cli_onFullMatch(KotobaState* state, const File* file, const License* license, const DiffMatchInfo* matchInfo) {
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

int cli_onDiff(KotobaState* state, const File* file, const License* license, const DiffResult* diffResult) {
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


int cli_onBeginOutput(KotobaState* state) {
  if (state->json) {
    printf("[");
  }
  return 1;
}
int cli_onBetweenIndividualOutputs(KotobaState* state) {
  if (state->json) {
    printf(",");
  }
  return 1;
}
int cli_onEndOutput(KotobaState* state) {
  if (state->json) {
    printf("]");
  }
  return 1;
}

