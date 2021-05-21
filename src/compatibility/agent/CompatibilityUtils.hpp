/*
 SPDX-FileCopyrightText: © 2024 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

#ifndef COMPATIBILITY_AGENT_UTILS_HPP
#define COMPATIBILITY_AGENT_UTILS_HPP

#define AGENT_NAME "compatibility"
#define AGENT_DESC "compatibility agent"
#define AGENT_ARS  "compatibility_ars"

#include "CompatibilityDatabaseHandler.hpp"
#include "CompatibilityState.hpp"
#include "libfossologyCPP.hpp"

#include <boost/program_options.hpp>
#include <json/json.h>
#include <utility>
#include <vector>

extern "C"
{
#include "libfossology.h"
}

using namespace std;

CompatibilityState getState(fo::DbManager& dbManager,
                            CompatibilityCliOptions&& cliOptions);
CompatibilityState getState(CompatibilityCliOptions&& cliOptions);
int queryAgentId(fo::DbManager& dbManager);
int writeARS(const CompatibilityState& state, int arsId, int uploadId,
             int success, fo::DbManager& dbManager);
void bail(int exitval);
bool processUploadId(const CompatibilityState& state, int uploadId,
                     CompatibilityDatabaseHandler& databaseHandler);
bool parseCliOptions(int argc, char** argv, CompatibilityCliOptions& dest,
                     std::string& types, std::string& rules, string& jFile);
void appendToJson(const vector<tuple<string, string, string>>& resultPair,
                  const std::string& fileName, bool& printComma);
void printResultToStdout(
    const vector<tuple<string, string, string>>& resultPair,
    const std::string& fileName);

#endif // COMPATIBILITY_AGENT_UTILS_HPP
