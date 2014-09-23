#include "copyrightState.h"


CopyrightState::CopyrightState(DbManager*  _dbManager,
            int _agentId,
            int _verbosity):
                              dbManager(_dbManager),
                              agentId(_agentId),
                              verbosity(_verbosity)
{

}

CopyrightState::~CopyrightState(){
//  fo_dbManager_finish(dbManager); This has to be done by hand outside, as a dbManager can be shared between States
}



int CopyrightState::getAgentId(){return agentId;};
int CopyrightState::getVerbosity(){return verbosity;};
DbManager* CopyrightState::getDbManager(){return dbManager;};

PGconn * CopyrightState::getConnection(){return dbManager->getConnection();};
