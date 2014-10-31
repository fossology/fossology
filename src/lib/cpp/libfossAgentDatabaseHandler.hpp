/*
Copyright (C) 2013-2014, Siemens AG

This program is free software; you can redistribute it and/or modify it under the terms of the GNU General Public License version 2 as published by the Free Software Foundation.

This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.

You should have received a copy of the GNU General Public License along with this program; if not, write to the Free Software Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
*/

#ifndef LIBFOSS_AGENT_DATABASE_HANDLER_HPP_
#define LIBFOSS_AGENT_DATABASE_HANDLER_HPP_

#include <vector>

#include "libfossdbmanagerclass.hpp"

namespace fo
{
  class AgentDatabaseHandler
  {

  protected:
    DbManager dbManager;
  public:
    AgentDatabaseHandler(DbManager dbManager);
    AgentDatabaseHandler(AgentDatabaseHandler&& other);
    AgentDatabaseHandler(const AgentDatabaseHandler&) = delete;
    virtual ~AgentDatabaseHandler();
    AgentDatabaseHandler operator =(const AgentDatabaseHandler&) = delete;

    bool begin() const;
    bool commit() const;
    bool rollback() const;

    char* getPFileNameForFileId(unsigned long pfileId) const;
    std::vector<unsigned long> queryFileIdsVectorForUpload(int uploadId) const;
  };
}

#endif