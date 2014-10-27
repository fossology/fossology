/*
 * Copyright (C) 2014, Siemens AG
 *
 * This program is free software; you can redistribute it and/or modify it under the terms of the GNU General Public License version 2 as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along with this program; if not, write to the Free Software Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 */

#include <iostream>
#include "ninkawrapper.hpp"
#include "utils.hpp"

State getState(DbManager& dbManager) {
  int agentId = queryAgentId(dbManager);
  return State(agentId);
}

int queryAgentId(DbManager& dbManager) {
  char* SVN_REV = fo_sysconfig(AGENT_NAME, "SVN_REV");
  char* VERSION = fo_sysconfig(AGENT_NAME, "VERSION");
  char* agentRevision;

  if (!asprintf(&agentRevision, "%s.%s", VERSION, SVN_REV))
    bail(-1);

  int agentId = fo_GetAgentKey(dbManager.getConnection(), AGENT_NAME, 0, agentRevision, AGENT_DESC);
  free(agentRevision);

  if (agentId <= 0)
    bail(1);

  return agentId;
}

int writeARS(const State& state, int arsId, int uploadId, int success, DbManager& dbManager) {
  PGconn* connection = dbManager.getConnection();
  int agentId = state.getAgentId();

  return fo_WriteARS(connection, arsId, uploadId, agentId, AGENT_ARS, NULL, success);
}

void bail(int exitval)
{
  fo_scheduler_disconnect(exitval);
  exit(exitval);
}

bool processUploadId(State& state, int uploadId, NinkaDatabaseHandler& databaseHandler) {
  vector<unsigned long> fileIds = databaseHandler.queryFileIdsForUpload(uploadId);

  for (vector<unsigned long>::const_iterator it = fileIds.begin(); it != fileIds.end(); ++it) {
    unsigned long pFileId = *it;

    if (pFileId == 0)
      continue;

    matchPFileWithLicenses(state, pFileId, databaseHandler);

    fo_scheduler_heart(1);
  }

  return true;
}

void matchPFileWithLicenses(State& state, unsigned long pFileId, NinkaDatabaseHandler& databaseHandler) {
  char* pFile = databaseHandler.getPFileNameForFileId(pFileId);

  if (!pFile)
  {
    cout << "File not found " << pFileId << endl;
    bail(8);
  }

  char* fileName = NULL;
  {
#pragma omp critical (repo_mk_path)
    fileName = fo_RepMkPath("files", pFile);
  }
  if (fileName)
  {
    fo::File file(pFileId, fileName);

    matchFileWithLicenses(file, state, databaseHandler);

    free(fileName);
    free(pFile);
  }
  else
  {
    cout << "PFile not found in repo " << pFileId << endl;
    bail(7);
  }
}

void matchFileWithLicenses(const State& state, const fo::File& file, NinkaDatabaseHandler& databaseHandler) {
  string ninkaResult = scanFileWithNinka(state, file);
  vector<string> ninkaLicenseNames = extractLicensesFromNinkaResult(ninkaResult);
  vector<LicenseMatch> matches = createMatches(ninkaLicenseNames);
  saveLicenseMatchesToDatabase(state, matches, file.getId(), databaseHandler);
}

bool saveLicenseMatchesToDatabase(const State& state, const vector<LicenseMatch>& matches, unsigned long pFileId, NinkaDatabaseHandler& databaseHandler) {
  if (!databaseHandler.begin())
    return false;

  for (vector<LicenseMatch>::const_iterator it = matches.begin(); it != matches.end(); ++it) {
    const LicenseMatch& match = *it;

    int agentId = state.getAgentId();
    long refId = getLicenseId(match.getLicenseName(), databaseHandler);
    unsigned percent = match.getPercentage();

    if (!databaseHandler.saveLicenseMatch(agentId, pFileId, refId, percent)) {
      databaseHandler.rollback();
      return false;
    };
  }

  return databaseHandler.commit();
}

// TODO: see function get_rfpk() from src/nomos/agent/nomos_utils.c
long getLicenseId(string rfShortname, NinkaDatabaseHandler& databaseHandler)
{
  long licenseId;

  if (rfShortname.length() == 0) {
    cout << "getLicenseId() passed empty license name" << endl;
    bail(1);
  }

  licenseId = databaseHandler.queryLicenseIdForLicense(rfShortname);
  if (licenseId)
    return licenseId;

  licenseId = databaseHandler.saveLicense(rfShortname);

  return licenseId;
}
