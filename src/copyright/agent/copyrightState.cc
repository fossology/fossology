#include "copyrightState.hpp"


CopyrightState::CopyrightState(DbManager* _dbManager, int _agentId, int _verbosity, const char* name):
                              copyrightDatabaseHandler(name),
                              dbManager(_dbManager),
                              agentId(_agentId),
                              verbosity(_verbosity)
{

}

CopyrightState::~CopyrightState(){
}



int CopyrightState::getAgentId(){return agentId;};
int CopyrightState::getVerbosity(){return verbosity;};
DbManager* CopyrightState::getDbManager(){return dbManager;};

PGconn * CopyrightState::getConnection(){return dbManager->getConnection();};

void CopyrightState::addMatcher(RegexMatcher regexMatcher)
{
  regexMatchers.push_back(regexMatcher);
}

std::vector< RegexMatcher > CopyrightState::getRegexMatchers()
{
  return regexMatchers;
}

std::vector< long > CopyrightState::queryFileIdsForUpload(long uploadId) {
 return copyrightDatabaseHandler.queryFileIdsForUpload(dbManager, agentId, uploadId);
}

