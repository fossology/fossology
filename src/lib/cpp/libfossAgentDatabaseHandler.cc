#include "database.hpp"
#include <iostream>
#include <libfossagent.h>
#include "libfossAgentDatabaseHandler.hpp"

fo::AgentDatabaseHandler::AgentDatabaseHandler(DbManager _dbManager) :
  dbManager(_dbManager)
{
}

fo::AgentDatabaseHandler::AgentDatabaseHandler(AgentDatabaseHandler&& other) :
  dbManager(std::move(other.dbManager))
{
}

fo::AgentDatabaseHandler::~AgentDatabaseHandler()
{
}

bool fo::AgentDatabaseHandler::begin() const
{
  return dbManager.begin();
}

bool fo::AgentDatabaseHandler::commit() const
{
  return dbManager.commit();
}

bool fo::AgentDatabaseHandler::rollback() const
{
  return dbManager.rollback();
}

char* fo::AgentDatabaseHandler::getPFileNameForFileId(unsigned long pfileId) const
{
  return queryPFileForFileId(dbManager.getStruct_dbManager(), pfileId);
}