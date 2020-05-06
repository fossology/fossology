/*
 * Copyright (C) 2019, Siemens AG
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * version 2 as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
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
