/*
Author: Daniele Fognini, Andreas Wuerl
Copyright (C) 2013-2014, Siemens AG

This program is free software; you can redistribute it and/or modify it under the terms of the GNU General Public License version 2 as published by the Free Software Foundation.

This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.

You should have received a copy of the GNU General Public License along with this program; if not, write to the Free Software Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
*/

#include "extended.h"
#include "cli.h"
#include "bulk.h"
#include "file_operations.h"
#include "database.h"
#include "license.h"
#include "match.h"
#include "getopt.h"

int parseArguments(MonkState* state, int argc, char** argv, int* fileOptInd, long* bulkOptId) {
  int c;
  state->verbosity = 0;
  while ((c = getopt(argc, argv, "VvhB:")) != -1) {
    switch (c) {
      case 'v':
        state->verbosity++;
        break;
      case 'B':
        *bulkOptId = atol(optarg);
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
  long bulkOptId = -1;
  if (!parseArguments(state, argc, argv, &fileOptInd, &bulkOptId))
    return 0;

  int result;
  if (bulkOptId > 0) {
    state->scanMode = MODE_BULK;
    result = handleBulkMode(state, bulkOptId);
  } else {
    state->scanMode = MODE_CLI;
    result = handleCliMode(state, argc, argv, fileOptInd);
  }
  return result;
}

void onNoMatch(MonkState* state, File* file) {
  if (state->scanMode == MODE_CLI) {
    onNoMatch_Cli(file);
  } else {
    // ignore for bulk mode
  }
}

void onFullMatch(MonkState* state, File* file, License* license, DiffMatchInfo* matchInfo) {
  if (state->scanMode == MODE_CLI) {
    onFullMatch_Cli(file, license, matchInfo);
  }
}

void onDiffMatch(MonkState* state, File* file, License* license, DiffResult* diffResult, unsigned short rank) {
  if (state->scanMode == MODE_CLI) {
    onDiffMatch_Cli(file, license, diffResult, rank);
  } else {
    // ignore for bulk mode
  }
}
