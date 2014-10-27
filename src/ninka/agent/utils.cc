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

State* getState(DbManager* dbManager) {
  int agentId = queryAgentId(dbManager);
  return new State(agentId, dbManager);
}

int queryAgentId(DbManager* dbManager) {
  char* SVN_REV = fo_sysconfig(AGENT_NAME, "SVN_REV");
  char* VERSION = fo_sysconfig(AGENT_NAME, "VERSION");
  char* agentRevision;

  if (!asprintf(&agentRevision, "%s.%s", VERSION, SVN_REV))
    bail(dbManager, -1);

  int agentId = fo_GetAgentKey(dbManager->getConnection(), AGENT_NAME, 0, agentRevision, AGENT_DESC);
  free(agentRevision);

  if (agentId <= 0)
    bail(dbManager, 1);

  return agentId;
}

int writeARS(State* state, int arsId, long uploadId, int success) {
  PGconn* connection = state->getDbManager()->getConnection();
  int agentId = state->getAgentId();

  return fo_WriteARS(connection, arsId, uploadId, agentId, AGENT_ARS, NULL, success);
}

void bail(State* state, int exitval) {
  delete(state);
  fo_scheduler_disconnect(exitval);
  exit(exitval);
}

void bail(DbManager* dbManager, int exitval) {
  delete(dbManager);
  fo_scheduler_disconnect(exitval);
  exit(exitval);
}

bool processUploadId(State* state, int uploadId) {
  vector<long> fileIds = state->getDatabaseHandler()->queryFileIdsForUpload(uploadId);

  for (vector<long>::const_iterator it = fileIds.begin(); it != fileIds.end(); ++it) {
    long pFileId = *it;

    if (pFileId <= 0)
      continue;

    matchPFileWithLicenses(state, pFileId);

    fo_scheduler_heart(1);
  }

  return true;
}

void matchPFileWithLicenses(State* state, long pFileId) {
  char* pFile = queryPFileForFileId(state->getDbManager()->getStruct_dbManager(), pFileId);
  if (!pFile) {
    cout << "File not found " << pFileId << endl;
    bail(state, 8);
  }

  char* fileName = fo_RepMkPath("files", pFile);
  if (fileName) {
    fo::File* file = new fo::File(pFileId, fileName);

    matchFileWithLicenses(state, file);

    free(pFile);
    delete(file);
  } else {
    cout << "PFile not found in repo " << pFileId << endl;
    bail(state, 7);
  }
}

void matchFileWithLicenses(State* state, fo::File* file) {
  string ninkaResult = scanFileWithNinka(state, file);
  vector<string> ninkaLicenseNames = extractLicensesFromNinkaResult(ninkaResult);
  vector<LicenseMatch> matches = createMatches(ninkaLicenseNames);
  saveLicenseMatchesToDatabase(state, matches, file->id);
}

bool saveLicenseMatchesToDatabase(State* state, const vector<LicenseMatch>& matches, long pFileId) {
  if (!state->getDbManager()->begin())
    return false;

  for (vector<LicenseMatch>::const_iterator it = matches.begin(); it != matches.end(); ++it) {
    const LicenseMatch& match = *it;

    int agentId = state->getAgentId();
    long refId = getLicenseId(state, match.getLicenseName());
    unsigned percent = match.getPercentage();

    if (!state->getDatabaseHandler()->saveLicenseMatch(agentId, pFileId, refId, percent)) {
      state->getDbManager()->rollback();
      return false;
    };
  }

  return state->getDbManager()->commit();
}

// TODO: see function get_rfpk() from src/nomos/agent/nomos_utils.c
long getLicenseId(State* state, string rfShortname) {
  long licenseId;

  if (rfShortname.length() == 0) {
    cout << "getLicenseId() passed empty license name" << endl;
    bail(state, 1);
  }

  licenseId = state->getDatabaseHandler()->queryLicenseIdForLicense(rfShortname);
  if (licenseId)
    return licenseId;

  licenseId = state->getDatabaseHandler()->saveLicense(rfShortname);

  return licenseId;
}
