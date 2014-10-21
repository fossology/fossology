/*
 * Copyright (C) 2014, Siemens AG
 * Author: Daniele Fognini, Johannes Najjar
 *
 * This program is free software; you can redistribute it and/or modify it under the terms of the GNU General Public License version 2 as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along with this program; if not, write to the Free Software Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 */

#include "copyrightUtils.hpp"

using namespace std;

void queryAgentId(int& agent, PGconn* dbConn) {
  char* SVN_REV = fo_sysconfig(AGENT_NAME, "SVN_REV");
  char* VERSION = fo_sysconfig(AGENT_NAME, "VERSION");
  char* agentRevision;
  if (!asprintf(&agentRevision, "%s.%s", VERSION, SVN_REV)) {
    exit(-1);
  };

  int agentId = fo_GetAgentKey(dbConn,
                     AGENT_NAME, 0, agentRevision, AGENT_DESC);
  free(agentRevision);

  if (agentId > 0)
    agent = agentId;
  else
    exit(1);
}

int writeARS(CopyrightState& state, int arsId, long uploadId, int success) {
  return fo_WriteARS(state.getConnection(), arsId, uploadId, state.getAgentId(), AGENT_ARS, NULL, success);
}

void bail(int exitval) {
  fo_scheduler_disconnect(exitval);
  exit(exitval);
}

CopyrightState getState(DbManager& dbManager, int verbosity){
  int agentID;
  queryAgentId(agentID, dbManager.getConnection());
  return CopyrightState(dbManager, agentID, verbosity);
}

void fillMatchers(CopyrightState& state) {
#ifdef IDENTITY_COPYRIGHT
  state.addMatcher(RegexMatcher(regCopyright::getType(), regCopyright::getRegex()));
  state.addMatcher(RegexMatcher(regURL::getType(), regURL::getRegex()));
  state.addMatcher(RegexMatcher(regEmail::getType(), regEmail::getRegex(), 1)); // TODO move 1 to getRegexId
#endif

#ifdef IDENTITY_IP
  state.addMatcher(RegexMatcher(regIp::getType(), regIp::getRegex()));
#endif

#ifdef IDENTITY_ECC
  state.addMatcher(RegexMatcher(regEcc::getType(), regEcc::getRegex()));
#endif
}

vector<CopyrightMatch> matchStringToRegexes(const string& content, std::vector< RegexMatcher > matchers ) {
  vector<CopyrightMatch> result;

  typedef  std::vector< RegexMatcher >::const_iterator rgm;
  for (rgm item = matchers.begin(); item != matchers.end(); ++item){
    vector<CopyrightMatch>  newMatch = item->match(content);
    result.insert(result.end(), newMatch.begin(), newMatch.end() ) ;
  }

  return result;
}


bool saveToDatabase(const vector<CopyrightMatch> & matches, const CopyrightState& state, long pFileId) {
  if (!state.getDbManager().begin())
    return false;

  size_t count = 0;
  typedef vector<CopyrightMatch>::const_iterator cpm;
  for (cpm it=matches.begin(); it!=matches.end(); ++it ){
    const CopyrightMatch& match = *it;

    DatabaseEntry entry;
    entry.agent_fk=state.getAgentId();
    entry.content=match.getContent();
    entry.copy_endbyte=match.getStart() + match.getLength();
    entry.copy_startbyte = match.getStart();
    entry.pfile_fk = pFileId;
    entry.type = match.getType();

    if(CleanDatabaseEntry(entry)) {
      ++count;
      if (!state.copyrightDatabaseHandler.insertInDatabase(state.getDbManager(), entry)) {
        state.getDbManager().rollback();
        return false;
      };
    }
  }

  if (count==0) {
    state.copyrightDatabaseHandler.insertNoResultInDatabase(state.getDbManager(), state.getAgentId(), pFileId);
  }

  return state.getDbManager().commit();
};

vector<CopyrightMatch> findAllMatches(const fo::File& file, const CopyrightState& state) {
  string fileContent = file.getContent(0);
  return matchStringToRegexes(fileContent, state.getRegexMatchers());
}

void matchFileWithLicenses(long pFileId, const fo::File& file, const CopyrightState& state){
  vector<CopyrightMatch> matches = findAllMatches(file, state);
  saveToDatabase(matches, state, pFileId);
}

void matchPFileWithLicenses(const CopyrightState& state, long pFileId) {
  char* pFile = queryPFileForFileId(state.getDbManager().getStruct_dbManager(), pFileId);

  if(!pFile) {
    cout << "File not found " << pFileId << endl;
    bail(8);
  }

  char * fil = g_strdup("files");
  char* fileName = fo_RepMkPath(fil, pFile);
  free(fil);
  if (fileName)
  {
    fo::File file(pFileId, fileName);

    matchFileWithLicenses(pFileId, file, state);


    free(fileName);
    free(pFile);
  }
  else
  {
    cout << "PFile not found in repo " << pFileId << endl;
    bail(7);
  }
}


bool processUploadId (const CopyrightState& state, int uploadId) {
  vector<long> fileIds = state.queryFileIdsForUpload(uploadId);

#pragma omp parallel
  {
    CopyrightState threadLocalState = state.spawn();

    size_t pFileCount = fileIds.size();
    #pragma omp for
    for (size_t it = 0; it < pFileCount; ++it) {
      long pFileId = fileIds[it];

      if (pFileId <= 0)
        continue;

      matchPFileWithLicenses(threadLocalState, pFileId);

      fo_scheduler_heart(1);
    }
  }

  return true;
}
