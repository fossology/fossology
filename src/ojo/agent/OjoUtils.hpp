/*
 SPDX-FileCopyrightText: © 2019 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

#ifndef OJOS_AGENT_UTILS_HPP
#define OJOS_AGENT_UTILS_HPP

#define AGENT_NAME "ojo"
#define AGENT_DESC "ojo agent"
#define AGENT_ARS  "ojo_ars"

#include <vector>
#include <utility>
#include <json/json.h>
#include <boost/program_options.hpp>

#include "ojomatch.hpp"
#include "OjoState.hpp"
#include "libfossologyCPP.hpp"
#include "OjosDatabaseHandler.hpp"

extern "C" {
#include "libfossology.h"
}

using namespace std;

OjoState getState(fo::DbManager &dbManager, OjoCliOptions &&cliOptions);
OjoState getState(OjoCliOptions &&cliOptions);
int queryAgentId(fo::DbManager &dbManager);
int writeARS(const OjoState &state, int arsId, int uploadId, int success,
  fo::DbManager &dbManager);
void bail(int exitval);
bool processUploadId(const OjoState &state, int uploadId,
  OjosDatabaseHandler &databaseHandler, bool ignoreFilesWithMimeType);
bool storeResultInDb(const vector<ojomatch> &matches,
  OjosDatabaseHandler &databaseHandle, const int agent_fk,
  const int pfile_fk);
bool parseCliOptions(int argc, char **argv, OjoCliOptions &dest,
  std::vector<std::string> &fileNames, std::string &directoryToScan);
void appendToJson(const std::string fileName,
    const pair<string, vector<ojomatch>> resultPair, bool &printComma);
void printResultToStdout(const std::string fileName,
  const pair<string, vector<ojomatch>> resultPair);

#endif // OJOS_AGENT_UTILS_HPP
