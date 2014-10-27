/*
 * Copyright (C) 2014, Siemens AG
 *
 * This program is free software; you can redistribute it and/or modify it under the terms of the GNU General Public License version 2 as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along with this program; if not, write to the Free Software Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
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

State getState(DbManager& dbManager);
int queryAgentId(DbManager& dbManager);
int writeARS(const State& state, int arsId, long uploadId, int success, DbManager& dbManager);
void bail(int exitval);
bool processUploadId(const State& state, int uploadId, NinkaDatabaseHandler& databaseHandler);
void matchPFileWithLicenses(State& state, unsigned long pFileId, NinkaDatabaseHandler& databaseHandler);
void matchFileWithLicenses(State* state, fo::File* file);
bool saveLicenseMatchesToDatabase(State* state, const vector<LicenseMatch>& matches, long pFileId);
long getLicenseId(State* state, string rfShortname);

#endif // NINKA_AGENT_UTILS_HPP
