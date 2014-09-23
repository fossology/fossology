#ifndef copyrightState_h
#define copyrightState_h

#include "libfossdbmanagerclass.hpp"
#include "regexMatcher.hpp"
#include <vector>

class CopyrightState {
public:
  CopyrightState(DbManager* _dbManager,  int _agentId, int _verbosity);
  ~CopyrightState();

  int getAgentId();
  int getVerbosity();
  DbManager* getDbManager();
  PGconn * getConnection();
  void addMatcher(RegexMatcher regexMatcher);
  std::vector<RegexMatcher> getRegexMatchers();

private:
  DbManager* dbManager;
  int agentId;
  int verbosity;
  std::vector<RegexMatcher> regexMatchers;
};

#endif
