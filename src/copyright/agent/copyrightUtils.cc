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

void bail(CopyrightState* state, int exitval) {
  delete(state->getDbManager());
  delete(state);
  fo_scheduler_disconnect(exitval);
  exit(exitval);
}

CopyrightState* getState(DbManager* dbManager, int verbosity){
  int agentID;
  queryAgentId(agentID, dbManager->getConnection());
  return new CopyrightState(dbManager, agentID, verbosity);
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


void saveToDatabase(const vector<CopyrightMatch> & matches, CopyrightState* state, long pFileId) {
  typedef vector<CopyrightMatch>::const_iterator cpm;
  for (cpm it=matches.begin(); it!=matches.end(); ++it ){
    const CopyrightMatch& match = *it;

    DatabaseEntry entry;
    entry.agent_fk=state->getAgentId();
    entry.content=match.content();
    entry.copy_endbyte=match.start() + match.length();
    entry.copy_startbyte = match.start();
    entry.pfile_fk = pFileId;
    entry.type = match.getType();

    if(CleanDatabaseEntry(entry)) {

      cout << "pFileId=" << entry.pfile_fk << " has " << entry.type << ": " << entry.content << " hash " << entry.hash << endl;

    }
  }
};

void matchFileWithLicenses(long pFileId, fo::File* file, CopyrightState* state){
  string fileContent = file->getContent(0);
  vector<CopyrightMatch> matches = matchStringToRegexes(fileContent, state->getRegexMatchers());
  saveToDatabase(matches, state, pFileId);
}

void matchPFileWithLicenses(CopyrightState* state, long pFileId) {

  char* pFile = queryPFileForFileId(state->getDbManager()->getStruct_dbManager(), pFileId);

  if(!pFile) {
    cout << "File not found " << pFileId << endl;
    bail(state, 8);
  }

  fo::File* file = new fo::File(pFileId, fo_RepMkPath("files", pFile));

  if (file->fileName != NULL) {
    // cout << "reading " << file->fileName << endl;
    matchFileWithLicenses(pFileId, file, state);
  }

  free(pFile);
  delete(file);
}


bool processUploadId (CopyrightState* state, int uploadId) {
  PGresult* fileIdResult = queryFileIdsForUpload(state->getDbManager()->getStruct_dbManager(), uploadId);

  if (PQntuples(fileIdResult) == 0) {
    PQclear(fileIdResult);
    fo_scheduler_heart(0);
    return false;
  }

  int count = PQntuples(fileIdResult);
  for (int i = 0; i < count; i++) {
    long pFileId = atol(PQgetvalue(fileIdResult, i, 0));

    if (pFileId <= 0)
      continue;

    matchPFileWithLicenses(state, pFileId);

    fo_scheduler_heart(1);
  }

  return true;
}
