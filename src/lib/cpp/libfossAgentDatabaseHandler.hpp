/*
 SPDX-FileCopyrightText: © 2013-2014 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

#ifndef LIBFOSS_AGENT_DATABASE_HANDLER_HPP_
#define LIBFOSS_AGENT_DATABASE_HANDLER_HPP_

#include <vector>

#include "libfossdbmanagerclass.hpp"

/**
 * \file
 * \brief DB utility functions for agents
 */

namespace fo
{
  /**
   * \class AgentDatabaseHandler
   * \brief Database handler for agents
   */
  class AgentDatabaseHandler
  {

  protected:
    DbManager dbManager;        ///< DbManager to use
  public:
    AgentDatabaseHandler(DbManager dbManager);
    AgentDatabaseHandler(AgentDatabaseHandler&& other);
    /**
     * Explicitly disallow copy constructor
     */
    AgentDatabaseHandler(const AgentDatabaseHandler&) = delete;
    virtual ~AgentDatabaseHandler();
    /**
     * Explicitly disallow copy constructor
     */
    AgentDatabaseHandler operator =(const AgentDatabaseHandler&) = delete;

    bool begin() const;
    bool commit() const;
    bool rollback() const;

    char* getPFileNameForFileId(unsigned long pfileId) const;
    std::string queryUploadTreeTableName(int uploadId);
    std::vector<unsigned long> queryFileIdsVectorForUpload(int uploadId, bool ignoreFilesWithMimeType) const;
    std::vector<unsigned long> queryFileIdsVectorForUpload(int uploadId,
      int agentId, bool ignoreFilesWithMimeType) const;
  };
}

#endif
