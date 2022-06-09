/*
 SPDX-FileCopyrightText: © 2013-2014 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

#include "libfossdbmanagerclass.hpp"
#include "libfossAgentDatabaseHandler.hpp"
#include "libfossUtils.hpp"

extern "C" {
#include "libfossagent.h"
}

/**
 * \file
 * \brief DB utility functions for agents
 */

/**
 * Constructor for AgentDatabaseHandler
 * @param _dbManager DbManager to use
 */
fo::AgentDatabaseHandler::AgentDatabaseHandler(DbManager _dbManager) :
  dbManager(_dbManager)
{
}

/**
 * \overload fo::AgentDatabaseHandler::AgentDatabaseHandler(AgentDatabaseHandler&& other)
 */
fo::AgentDatabaseHandler::AgentDatabaseHandler(AgentDatabaseHandler&& other) :
  dbManager(other.dbManager)
{
}

/**
 * Default destructor for AgentDatabaseHandler
 */
fo::AgentDatabaseHandler::~AgentDatabaseHandler()
{
}

/**
 * \brief BEGIN a transaction block in DB
 * \return True on success;\n
 * False on failure;
 */
bool fo::AgentDatabaseHandler::begin() const
{
  return dbManager.begin();
}

/**
 * \brief COMMIT a transaction block in DB
 * \return True on success;\n
 * False on failure;
 */
bool fo::AgentDatabaseHandler::commit() const
{
  return dbManager.commit();
}

/**
 * \brief ROLLBACK a transaction block in DB
 * \return True on success;\n
 * False on failure;
 */
bool fo::AgentDatabaseHandler::rollback() const
{
  return dbManager.rollback();
}

/**
 * \brief Get the file name of a give pfile id
 * \param pfileId Pfile to search
 * \return The file name (`SHA1.MD5.SIZE`)
 * \sa queryPFileForFileId()
 */
char* fo::AgentDatabaseHandler::getPFileNameForFileId(unsigned long pfileId) const
{
  return queryPFileForFileId(dbManager.getStruct_dbManager(), pfileId);
}

/**
 * \brief Get pfile ids for a given upload id
 * \param uploadId Upload id to fetch from
 * \param ignoreFilesWithMimeType ignore files with particular mimetype
 * \return Vector of pfile ids in given upload id
 * \sa queryFileIdsForUpload()
 */
std::vector<unsigned long> fo::AgentDatabaseHandler::queryFileIdsVectorForUpload(int uploadId, bool ignoreFilesWithMimeType) const
{
  QueryResult queryResult(queryFileIdsForUpload(dbManager.getStruct_dbManager(), uploadId, ignoreFilesWithMimeType));
  return queryResult.getSimpleResults(0, fo::stringToUnsignedLong);
}

/**
 * \brief Get pfile ids for a given upload id which agent has not scanned
 * \param uploadId Upload id to fetch from
 * \param agentId  Agent id to filter pfiles for
 * \param ignoreFilesWithMimeType ignore files with particular mimetype
 * \return Vector of pfile ids in given upload id
 * \sa getSelectedPFiles()
 */
std::vector<unsigned long> fo::AgentDatabaseHandler::queryFileIdsVectorForUpload (
    int uploadId, int agentId, bool ignoreFilesWithMimeType) const
{
  QueryResult queryResult(
    getSelectedPFiles(dbManager.getConnection(), uploadId, agentId,
                      ignoreFilesWithMimeType));
  return queryResult.getSimpleResults(0, fo::stringToUnsignedLong);
}

/**
 * \brief Get the upload tree table name for a given upload id
 * \param uploadId Upload id to check
 * \return Name of the table holding the upload tree
 */
std::string fo::AgentDatabaseHandler::queryUploadTreeTableName(int uploadId)
{
  return std::string(getUploadTreeTableName(dbManager.getStruct_dbManager(), uploadId));
}
