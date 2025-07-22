/*
 * Copyright (C) 2019-2020, Siemens AG
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

#include <iostream>
#include "atarashiwrapper.hpp"
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

bool processUploadId(const State& state, int uploadId, AtarashiDatabaseHandler& databaseHandler)
{
  vector<unsigned long> fileIds = databaseHandler.queryFileIdsForUpload(uploadId);

  bool errors = false;
#pragma omp parallel
  {
    AtarashiDatabaseHandler threadLocalDatabaseHandler(databaseHandler.spawn());

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

bool matchPFileWithLicenses(const State& state, unsigned long pFileId, AtarashiDatabaseHandler& databaseHandler)
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

vector<LicenseMatch> createMatches(std::string licenceName, unsigned percentage)
{
  vector<LicenseMatch> matches;

  std::string fossologyLicenseName = licenceName;
  unsigned score = percentage;
  LicenseMatch match = LicenseMatch(fossologyLicenseName, score);

  matches.push_back(match);
  return matches;
}

bool matchFileWithLicenses(const State& state, const fo::File& file, AtarashiDatabaseHandler& databaseHandler)
{
  string atarashiResult = scanFileWithAtarashi(state, file);
  vector<LicenseMatch> matches = extractLicensesFromAtarashiResult(atarashiResult);
  return saveLicenseMatchesToDatabase(state, matches, file.getId(), databaseHandler);
}

bool saveLicenseMatchesToDatabase(const State& state, const vector<LicenseMatch>& matches, unsigned long pFileId, AtarashiDatabaseHandler& databaseHandler)
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
