/*
 Author: Daniele Fognini, Andreas Wuerl
 SPDX-FileCopyrightText: © 2013-2015 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

#include "monk.h"

#include "license.h"
#include "scheduler.h"
#include "cli.h"
#include "common.h"
#include "serialize.h"
#include <getopt.h>

void parseArguments(MonkState* state, int argc, char** argv, int* fileOptInd) {
  int c;
  static struct option long_options[] = {{"config", required_argument, 0, 'c'},
                                         {"userID", required_argument, 0, 'u'},
                                         {"groupID", required_argument, 0, 'g'},
                                         {"scheduler_start", no_argument, 0, 'S'},
                                         {"jobId", required_argument, 0, 'j'},
                                         {NULL, 0, NULL, 0}};
  int option_index = 0;
  while ((c = getopt_long(argc, argv, "VvJhIs:k:c:", long_options, &option_index)) != -1) {
    switch (c) {
      case 'c':
        break;
      case 'u':
      case 'g':
      case 'S':
      case 'j':
        /* these options are handled by the fo_scheduler_connect_dbMan call later in detail */
        state->scanMode = MODE_SCHEDULER;
        break;
      case 'v':
        state->verbosity++;
        break;
       case 'V':
#ifdef COMMIT_HASH_S
        printf(AGENT_NAME " version " VERSION_S " r(" COMMIT_HASH_S ")\n");
#else
        printf(AGENT_NAME " (no version available)\n");
#endif
        state->scanMode = 0;
        return;
      case 'J':
        state->json = 1;
        break;
      case 's':
        state->scanMode = MODE_EXPORT_KOWLEDGEBASE;
        state->knowledgebaseFile = optarg;
        break;
      case 'k':
        state->scanMode = MODE_CLI_OFFLINE;
        state->knowledgebaseFile = optarg;
        break;
      case 'I':
        state->ignoreFilesWithMimeType = true;
        break;
      case 'h':
      case '?':
        printf("Usage:\n"
               "\nAs CLI tool using the licenses from the FOSSology database:\n");
        printf("       %s [options] file [file [...]]\n", argv[0]);
        printf("               options:\n"
               "                  -h          :: help (print this message), then exit.\n"
               "                  -c config   :: specify the directory for the system configuration.\n"
               "                  -v          :: verbose output.\n"
               "                  -J          :: JSON output.\n"
               "                  file        :: scan file and print licenses detected within it.\n"
               "                  -V          :: print the version info, then exit.\n"
               "\nSave knowledgebase to knowledgebaseFile for offline usage without db:\n");
        printf("       %s [options] -s knowledgebaseFile\n", argv[0]);
        printf("               options:\n"
               "                  -c config   :: specify the directory for the system configuration.\n"
               "\nUse previously saved knowledgebaseFile for offline usage without db.\n");
        printf("       %s -k knowledgebaseFile [options] file [file [...]]\n", argv[0]);
        printf("               options:\n"
               "                  -J          :: JSON output.\n"
               "                  file        :: scan file and print licenses detected within it.\n"
               "\nThe following should only be called by the FOSSology scheduler:\n");
        printf("       %s --scheduler_start [options]\n", argv[0]);
        printf("               options:\n"
               "                  -c config   :: specify the directory for the system configuration.\n"
               "                  --userID i  :: the id of the user that created the job\n"
               "                  --groupID i :: the id of the group of the user that created the job\n"
               "                  --jobID i   :: the id of the job\n");
        state->scanMode = 0;
        return;
    }
  }
  *fileOptInd = optind;
  if (optind < argc && state->scanMode != MODE_CLI_OFFLINE) {
    state->scanMode = MODE_CLI;
  }
  if((state->scanMode == MODE_CLI_OFFLINE ||
      state->scanMode == MODE_EXPORT_KOWLEDGEBASE) &&
     state->knowledgebaseFile == NULL) {
    fprintf( stderr, "necessary path to knowledgebase file not provided\n");
    bail(state,4);
  }
}

int main(int argc, char** argv) {
  int fileOptInd;

  MonkState stateStore = { .dbManager = NULL,
                           .agentId = 0,
                           .scanMode = 0,
                           .verbosity = 0,
                           .knowledgebaseFile = NULL,
                           .json = 0,
                           .ignoreFilesWithMimeType = false,
                           .ptr = NULL };
  MonkState* state = &stateStore;
  parseArguments(state, argc, argv, &fileOptInd);
  int wasSuccessful = 1;

  if (state->scanMode == 0) {
    return 0;
  }

  Licenses* licenses;
  if (state->scanMode != MODE_CLI_OFFLINE) {
    int oldArgc = argc;
    fo_scheduler_connect_dbMan(&argc, argv, &(state->dbManager));
    fileOptInd = fileOptInd - oldArgc + argc;

    PGresult* licensesResult = queryAllLicenses(state->dbManager);
    licenses = extractLicenses(state->dbManager, licensesResult, MIN_ADJACENT_MATCHES, MAX_LEADING_DIFF);
    PQclear(licensesResult);
  } else {
    licenses = deserializeFromFile(state->knowledgebaseFile, MIN_ADJACENT_MATCHES, MAX_LEADING_DIFF);
  }

  if (state->scanMode == MODE_SCHEDULER) {
    wasSuccessful = handleSchedulerMode(state, licenses);
    scheduler_disconnect(state, ! wasSuccessful);
  } else if (state->scanMode == MODE_CLI ||
             state->scanMode == MODE_CLI_OFFLINE) {
    /* we no longer need a database connection */
    if (state->dbManager != NULL) {
      scheduler_disconnect(state, 0);
    }
    state->dbManager = NULL;

    wasSuccessful = handleCliMode(state, licenses, argc, argv, fileOptInd);
  } else if (state->scanMode == MODE_EXPORT_KOWLEDGEBASE) {
    printf("Write knowledgebase to %s\n", state->knowledgebaseFile);
    wasSuccessful = serializeToFile(licenses, state->knowledgebaseFile);
  }

  licenses_free(licenses);

  return ! wasSuccessful;
}
