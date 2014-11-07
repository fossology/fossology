/*
Copyright (C) 2013-2014, Siemens AG

This program is free software; you can redistribute it and/or modify it under the terms of the GNU General Public License version 2 as published by the Free Software Foundation.

This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.

You should have received a copy of the GNU General Public License along with this program; if not, write to the Free Software Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
*/

#include "libfossdbmanagerclass.hpp"
#include "libfossAgentDatabaseHandler.hpp"
#include "libfossUtils.hpp"

extern "C" {
#include "libfossagent.h"
}

fo::AgentDatabaseHandler::AgentDatabaseHandler(DbManager _dbManager) :
  dbManager(_dbManager)
{
}

fo::AgentDatabaseHandler::AgentDatabaseHandler(AgentDatabaseHandler&& other) :
  dbManager(other.dbManager)
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

std::vector<unsigned long> fo::AgentDatabaseHandler::queryFileIdsVectorForUpload(int uploadId) const
{
  QueryResult queryResult(queryFileIdsForUpload(dbManager.getStruct_dbManager(), uploadId));
  return queryResult.getSimpleResults(0, fo::stringToUnsignedLong);
}