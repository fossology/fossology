/*
 SPDX-FileCopyrightText: © 2014-2015 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

#include <iostream>
#include "ninkawrapper.hpp"
#include "utils.hpp"

using namespace fo;

State getState(DbManager& dbManager)
{
  int agentId = queryAgentId(dbManager);
  return State(agentId);
}

int queryAgentId(DbManager& dbManager)
{
  char* COMMIT_HASH = fo_sysconfig(AGENT_NAME, "COMMIT_HASH");
  char* VERSION = fo_sysconfig(AGENT_NAME, "VERSION");
  char* agentRevision;

  if (!asprintf(&agentRevision, "%s.%s", VERSION, COMMIT_HASH))
    bail(-1);

  int agentId = fo_GetAgentKey(dbManager.getConnection(), AGENT_NAME, 0, agentRevision, AGENT_DESC);
  free(agentRevision);

  if (agentId <= 0)
    bail(1);

  return agentId;
}

int writeARS(const State& state, int arsId, int uploadId, int success, DbManager& dbManager)
{
  PGconn* connection = dbManager.getConnection();
  int agentId = state.getAgentId();

  return fo_WriteARS(connection, arsId, uploadId, agentId, AGENT_ARS, NULL, success);
}

void bail(int exitval)
{
  fo_scheduler_disconnect(exitval);
  exit(exitval);
}

bool processUploadId(const State& state, int uploadId, NinkaDatabaseHandler& databaseHandler)
{
  vector<unsigned long> fileIds = databaseHandler.queryFileIdsForUpload(uploadId);

  bool errors = false;
#pragma omp parallel
  {
    NinkaDatabaseHandler threadLocalDatabaseHandler(databaseHandler.spawn());

    size_t pFileCount = fileIds.size();
#pragma omp for
    for (size_t it = 0; it < pFileCount; ++it)
    {
      if (errors)
        continue;

      unsigned long pFileId = fileIds[it];

      if (pFileId == 0)
        continue;

      if (!matchPFileWithLicenses(state, pFileId, threadLocalDatabaseHandler))
      {
        errors = true;
      }

      fo_scheduler_heart(1);
    }
  }

  return !errors;
}

bool matchPFileWithLicenses(const State& state, unsigned long pFileId, NinkaDatabaseHandler& databaseHandler)
{
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

    if (!matchFileWithLicenses(state, file, databaseHandler))
      return false;

    free(fileName);
    free(pFile);
  }
  else
  {
    cout << "PFile not found in repo " << pFileId << endl;
    bail(7);
  }

  return true;
}

bool matchFileWithLicenses(const State& state, const fo::File& file, NinkaDatabaseHandler& databaseHandler)
{
  string ninkaResult = scanFileWithNinka(state, file);
  vector<string> ninkaLicenseNames = extractLicensesFromNinkaResult(ninkaResult);
  vector<LicenseMatch> matches = createMatches(ninkaLicenseNames);
  return saveLicenseMatchesToDatabase(state, matches, file.getId(), databaseHandler);
}

bool saveLicenseMatchesToDatabase(const State& state, const vector<LicenseMatch>& matches, unsigned long pFileId, NinkaDatabaseHandler& databaseHandler)
{
  for (vector<LicenseMatch>::const_iterator it = matches.begin(); it != matches.end(); ++it)
  {
    const LicenseMatch& match = *it;
    databaseHandler.insertOrCacheLicenseIdForName(match.getLicenseName());
  }

  if (!databaseHandler.begin())
    return false;

  for (vector<LicenseMatch>::const_iterator it = matches.begin(); it != matches.end(); ++it)
  {
    const LicenseMatch& match = *it;

    int agentId = state.getAgentId();
    string rfShortname = match.getLicenseName();
    unsigned percent = match.getPercentage();

    unsigned long licenseId = databaseHandler.getCachedLicenseIdForName(rfShortname);

    if (licenseId == 0)
    {
      databaseHandler.rollback();
      cout << "cannot get licenseId for shortname '" + rfShortname + "'" << endl;
      return false;
    }


    if (!databaseHandler.saveLicenseMatch(agentId, pFileId, licenseId, percent))
    {
      databaseHandler.rollback();
      cout << "failing save licenseMatch" << endl;
      return false;
    };
  }

  return databaseHandler.commit();
}
