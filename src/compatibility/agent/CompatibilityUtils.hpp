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
#include "yaml-cpp/yaml.h"
#include "json/json.h"
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
                     CompatibilityDatabaseHandler& databaseHandler,
                     int groupId);
bool parseCliOptions(int argc, char** argv, CompatibilityCliOptions& dest,
                     std::string& types, std::string& rules, string& jFile,
                     string& mainLicense);
void appendToJson(const std::vector<tuple<string, string, bool>>& resultPair,
                  const std::string& fileName, bool& printComma);
void printResultToStdout(
    const std::vector<tuple<string, string, bool>>& resultPair,
    const std::string& fileName);
std::set<std::string> mainLicenseToSet(const string& mainLicense);
bool are_licenses_compatible(const string& first_name, const string& first_type,
    const string& second_name, const string& second_type,
    const map<tuple<string, string, string, string>, bool>& rule_list);
map<tuple<string, string, string, string>, bool> initialize_rule_list(
    const string& file_location);
unordered_map<string, string> initialize_license_map(
    const string& file_location);

#endif // COMPATIBILITY_AGENT_UTILS_HPP
