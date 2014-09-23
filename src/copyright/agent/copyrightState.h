#ifndef copyrightState_h
#define copyrightState_h

#include "libfossdbmanagerclass.hpp"

class CopyrightState {
public:
  CopyrightState(DbManager* _dbManager,  int _agentId, int _verbosity);
  ~CopyrightState();

  int getAgentId();
  int getVerbosity();
  DbManager* getDbManager();
  PGconn * getConnection();

private:
  DbManager*  dbManager;
  int agentId;
  int verbosity;
};

#endif
