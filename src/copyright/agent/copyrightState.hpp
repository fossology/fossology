#ifndef copyrightState_h
#define copyrightState_h

#include "libfossdbmanagerclass.hpp"
#include "regexMatcher.hpp"
#include "database.hpp"
#include <vector>

class CopyrightState {
public:
  CopyrightState(DbManager* _dbManager,  int _agentId, int _verbosity, const char* name);
  ~CopyrightState();

  int getAgentId();
  int getVerbosity();
  DbManager* getDbManager();
  PGconn * getConnection();
  void addMatcher(RegexMatcher regexMatcher);
  std::vector<RegexMatcher> getRegexMatchers();
  CopyrightDatabaseHandler copyrightDatabaseHandler;
  std::vector<long> queryFileIdsForUpload(long uploadId);
private:
  DbManager* dbManager;
  int agentId;
  int verbosity;
  std::vector<RegexMatcher> regexMatchers;
};

#endif
