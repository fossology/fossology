/*
Author: Daniele Fognini, Andreas Wuerl, Johannes Najjar
Copyright (C) 2014, Siemens AG

This program is free software; you can redistribute it and/or modify it under the terms of the GNU General Public License version 2 as published by the Free Software Foundation.

This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.

You should have received a copy of the GNU General Public License along with this program; if not, write to the Free Software Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
*/
#include <stdio.h>

extern "C"{
#include "libfossology.h"
}

#include <iostream>
#include "copyright.h"

using namespace std;

class File {
public:
  long id;
  char* fileName;

  File() :id(0), fileName(NULL) {}

  ~File(){
    if(fileName!=NULL)
      free(fileName);
  }
};

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

string readFileToString(const char* fileName){
  return "this is the copyright of Copyright";
}

vector<CopyrightMatch*> matchStringToRegexes(const string& content, CopyrightState* state) {
  vector<CopyrightMatch*> matches;
  vector<RegexMatcher> matchers = state->getRegexMatchers();

  for (auto it = matchers.cbegin(); it != matchers.cend(); ++it) {
    CopyrightMatch* newMatch = it->match(content);
    matches.push_back(newMatch);
  }

  return matches;
}


void saveToDatabase(vector<CopyrightMatch*> matches, CopyrightState* state) {
  for (auto it = matches.cbegin(); it != matches.cend(); ++it) {
    for (unsigned matchI = 0; matchI < it->size(); ++matchI) {
      cout << "match [" << matchI << "] = " << (*it)[matchI] << endl;
    }
  }
};

void matchPFileWithLicenses(CopyrightState* state, long pFileId) {
  File* file = new File();
  file->id = pFileId;

  char* pFile = queryPFileForFileId(state->getDbManager()->getStruct_dbManager(), pFileId);

  if(!pFile) {
    cout << "File not found " << pFileId << endl;
    bail(state, 8);
  }

  file->fileName = fo_RepMkPath("files", pFile);

  if (file->fileName != NULL) {
    string fileContent = readFileToString(file->fileName);
    vector<CopyrightMatch*> matches = matchStringToRegexes(fileContent, state);

    saveToDatabase(matches, state);
  }

  delete(file);
}


bool processUploadId (CopyrightState* state, int uploadId) {
  PGresult* fileIdResult = queryFileIdsForUpload(state->getDbManager()->getStruct_dbManager(), uploadId);

  if (PQntuples(fileIdResult) == 0) {
    PQclear(fileIdResult);
    fo_scheduler_heart(0);
    return 0;
  }

  int count = PQntuples(fileIdResult);
  for (int i = 0; i < count; i++) {
    long pFileId = atol(PQgetvalue(fileIdResult, i, 0));

    if (pFileId <= 0)
      continue;

    matchPFileWithLicenses(state, pFileId);

    fo_scheduler_heart(1);
  }

  return false;
}

int main(int argc, char** argv) {
  /* before parsing argv and argc make sure */
  /* to initialize the scheduler connection */

  DbManager* dbManager = new DbManager(&argc, argv);

  int verbosity=8;
  CopyrightState* state;
  state = getState(dbManager, verbosity);

  state->addMatcher(RegexMatcher("copyright"));

  while (fo_scheduler_next() != NULL) {
    int uploadId = atoi(fo_scheduler_current());

    if (uploadId == 0) continue;

    int arsId = fo_WriteARS(state->getConnection(),
                            0, uploadId, state->getAgentId(), AGENT_ARS, NULL, 0);

    if (!processUploadId(state, uploadId))
      bail(state, 2);


    fo_scheduler_heart(1);
    fo_WriteARS(state->getConnection(),
                arsId, uploadId, state->getAgentId(), AGENT_ARS, NULL, 1);
  }
  fo_scheduler_heart(0);


  delete (state);
  delete(dbManager);
  /* after cleaning up agent, disconnect from */
  /* the scheduler, this doesn't return */
  exit(0);
}
