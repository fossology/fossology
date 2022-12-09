/*
 SPDX-FileCopyrightText: © 2021 Sarita Singh <saritasingh.0425@gmail.com>

 SPDX-License-Identifier: GPL-2.0-only
*/

#ifndef SCANCODE_AGENT_UTILS_HPP
#define SCANCODE_AGENT_UTILS_HPP

#define AGENT_NAME "scancode"
#define AGENT_DESC "scancode agent"
#define AGENT_ARS  "scancode_ars"

#include <iostream>
#include <string>
#include <vector>

#include <boost/program_options.hpp>

#include "files.hpp"
#include "match.hpp"
#include "scancode_state.hpp"
#include "scancode_wrapper.hpp"

extern "C" {
#include "libfossology.h"
}

using namespace std;

/**
 * @brief Utility functions for file handling
 */
using namespace fo;

State getState(fo::DbManager& dbManager);
int queryAgentId(fo::DbManager& dbManager);
int writeARS(const State& state, int arsId, int uploadId, int success, fo::DbManager& dbManager);
void bail(int exitval);
bool processUploadId(const State& state, int uploadId, ScancodeDatabaseHandler& databaseHandler, bool ignoreFilesWithMimeType);
bool matchPFileWithLicenses(const State& state, unsigned long pFileId, ScancodeDatabaseHandler& databaseHandler);
bool matchFileWithLicenses(const State& state, const fo::File& file, ScancodeDatabaseHandler& databaseHandler);
bool saveLicenseMatchesToDatabase(const State& state, const vector<Match>& matches, unsigned long pFileId, ScancodeDatabaseHandler& databaseHandler);
bool saveOtherMatchesToDatabase(const State& state, const vector<Match>& matches, unsigned long pFileId, ScancodeDatabaseHandler& databaseHandler);
bool parseCommandLine(int argc, char** argv, string& cliOption, bool& ignoreFilesWithMimeType);

#endif // SCANCODE_AGENT_UTILS_HPP