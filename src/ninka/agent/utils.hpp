/*
 SPDX-FileCopyrightText: © 2014-2015 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

#ifndef NINKA_AGENT_UTILS_HPP
#define NINKA_AGENT_UTILS_HPP

#define AGENT_NAME "ninka"
#define AGENT_DESC "ninka agent"
#define AGENT_ARS  "ninka_ars"

#include <string>
#include <vector>
#include "files.hpp"
#include "licensematch.hpp"
#include "state.hpp"

extern "C" {
#include "libfossology.h"
}

using namespace std;

State getState(fo::DbManager& dbManager);
int queryAgentId(fo::DbManager& dbManager);
int writeARS(const State& state, int arsId, int uploadId, int success, fo::DbManager& dbManager);
void bail(int exitval);
bool processUploadId(const State& state, int uploadId, NinkaDatabaseHandler& databaseHandler);
bool matchPFileWithLicenses(const State& state, unsigned long pFileId, NinkaDatabaseHandler& databaseHandler);
bool matchFileWithLicenses(const State& state, const fo::File& file, NinkaDatabaseHandler& databaseHandler);
bool saveLicenseMatchesToDatabase(const State& state, const vector<LicenseMatch>& matches, unsigned long pFileId, NinkaDatabaseHandler& databaseHandler);

#endif // NINKA_AGENT_UTILS_HPP
