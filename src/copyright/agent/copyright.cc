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
#include "copyright.hpp"
#include "files.hpp"

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

    cout << "pFileId=" << pFileId << " has " << match.getType() << ": " << match.content() << endl;
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
    // cout << "reading " << file->fileName << endl;
    string fileContent = fo::getStringFromFile(file->fileName,-1);
    vector<CopyrightMatch> matches = matchStringToRegexes(fileContent, state->getRegexMatchers());

    saveToDatabase(matches, state, pFileId);
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

int main(int argc, char** argv) {
  /* before parsing argv and argc make sure */
  /* to initialize the scheduler connection */

  DbManager* dbManager = new DbManager(&argc, argv);

  int verbosity=8;
  CopyrightState* state;
  state = getState(dbManager, verbosity);

  const char* copyrightRegex = "("
  "("
  "(Copyright|(\\(C\\) Copyright([[:punct:]]?))) "
  "("
  "((and|hold|info|law|licen|message|notice|owner|state|string|tag|copy|permission|this|timestamp|@author)*)"
  "|"
  "([[:print:]]{0,10}|[[:print:]]*)" // TODO this is equivalent to [[:print:]]*
  ")"
  "("
  "([[:digit:]]{4,4}([[:punct:]]|[[:space:]])[[:digit:]]{4,4})+ |[[:digit:]]{4,4}"
  ")"
  "(([[:space:]]|[[:punct:]]))" // TODO wth do we match all this junk?
  "([[:print:]]*)" // TODO wth do we match all this junk?
  ")|("
  "Copyright([[:punct:]]*) \\(C\\) "
  "("
  "((and|hold|info|law|licen|message|notice|owner|state|string|tag|copy|permission|this|timestamp)*)"
  "|"
  "[[:print:]]*" // TODO this matches everything and overrides the previous ???
  ")"
  "("
  "([[:digit:]]{4,4}([[:punct:]]|[[:space:]])[[:digit:]]{4,4})+ |[[:digit:]]{4,4}"
  ")"
  "(([[:space:]]|[[:punct:]]))"
  "([[:print:]]*)"
  ")|("
  "(\\(C\\)) ([[:digit:]]{4,4}[[:punct:]]*[[:digit:]]{4,4})([[:print:]]){0,60}"
  ")|("
  "Copyrights [[:blank:]]*[a-zA-Z]([[:print:]]{0,60})"
  ")|("
  "(all[[:space:]]*rights[[:space:]]*reserved)"
  ")|("
  "(author|authors)[[:space:]|[:punct:]]+([a-zA-Z]*[[:punct:]]*[a-zA-Z]*)*[[:space:]]*([[:print:]]{0,60})|(contributors|contributor)[[:space:]|[:punct:]]+([a-zA-Z]*[[:punct:]]*[a-zA-Z]*)*[[:space:]]*([[:print:]]{0,60})|written[[:space:]]*by[[:space:]|[:punct:]]*([a-zA-Z]*[[:punct:]]*[a-zA-Z]*)*([[:print:]]{0,60})|contributed[[:space:]]*by[[:space:]|[:punct:]]*([a-zA-Z]*[[:punct:]]*[a-zA-Z]*)*([[:print:]]{0,60})"
  ")"
  ")";

  state->addMatcher(RegexMatcher("statement", copyrightRegex));
  state->addMatcher(RegexMatcher("url", "(?:(:?ht|f)tps?\\:\\/\\/[^\\s\\<]+[^\\<\\.\\,\\s])"));
  state->addMatcher(RegexMatcher("email", "[\\<\\(]?([\\w\\-\\.\\+]{1,100}@[\\w\\-\\.\\+]{1,100}\\.[a-z]{1,4})[\\>\\)]?", 1));

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

  /* after cleaning up agent, disconnect from */
  /* the scheduler, this doesn't return */
  bail(state, 0);
}
