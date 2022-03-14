/*****************************************************************************
 * SPDX-License-Identifier: GPL-2.0
 * SPDX-FileCopyrightText: 2021 Sarita Singh <saritasingh.0425@gmail.com>
 * 
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * version 2 as published by the Free Software Foundation.
 * 
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 * 
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 ****************************************************************************/

#ifndef SCANCODE_AGENT_DATABASE_HANDLER_HPP
#define SCANCODE_AGENT_DATABASE_HANDLER_HPP

#include <algorithm>
#include <iostream>
#include <string>
#include <unordered_map>
#include <vector>

#include "libfossAgentDatabaseHandler.hpp"
#include "libfossUtils.hpp"
#include "libfossdbmanagerclass.hpp"
#include "match.hpp"

extern "C" {
#include "libfossology.h"
}

using namespace std;

/**
 * @brief Utility functions for file handling
 */
using namespace fo;

#define RETURN_IF_FALSE(query) \
  do {\
    if (!(query)) {\
      return false;\
    }\
  } while(0)


#define MAX_TABLE_CREATION_RETRIES 5

/**
 * \class DatabaseEntry
 * \brief Maps agent data to database schema
 */
class DatabaseEntry
{
public:
  DatabaseEntry();
  DatabaseEntry(Match match,unsigned long agentId, unsigned long pfileId);
  unsigned long agent_fk;                    /**< Id of agent performed the scan */
  unsigned long pfile_fk;                    /**< Id of pfile on which the scan was performed */
  std::string content;              /**< Statement found during the scan */
  std::string hash;                 /**< MD5 hash of the statement */
  /**
   * \brief Type of statement found.
   *
   * Can be
   *   - copyright for Copyright
   *   - author for Author
   *   - url for URL
   *   - email for email
   */
  std::string type;
  unsigned copy_startbyte;               /**< Statement start offset from start of pfile content */
  unsigned copy_endbyte;                 /**< Statement end offset from start of pfile content */
};

class ScancodeDatabaseHandler : public fo::AgentDatabaseHandler
{
public:
  ScancodeDatabaseHandler(fo::DbManager dbManager);
  ScancodeDatabaseHandler(ScancodeDatabaseHandler&& other) : fo::AgentDatabaseHandler(std::move(other)) {};
  ScancodeDatabaseHandler spawn() const;
  long saveLicenseMatch(int agentId, long pFileId, long licenseId, int percentMatch);
  bool insertNoResultInDatabase(int agentId, long pFileId, long licenseId);

  bool saveHighlightInfo(long licenseFileId, unsigned start, unsigned length);
  void insertOrCacheLicenseIdForName(std::string const& rfShortName, std::string const& rfFullname, std::string const& rfTexturl);
  unsigned long getCachedLicenseIdForName(std::string const& rfShortName) const;
  bool insertInDatabase(DatabaseEntry& entry) const;
  std::vector<unsigned long> queryFileIdsForUpload(int uploadId, bool ignoreFilesWithMimeType);
  bool createTables() const;
private:
  unsigned long selectOrInsertLicenseIdForName(std::string rfShortname, std::string rfFullname, std::string rfTexturl);
  std::unordered_map<std::string,long> licenseRefCache;
  /**
   * \struct ColumnDef
   * \brief Holds the column related data for table creation
   */
  struct ColumnDef
  {
    const char* name;               /**< Name of the table column */
    const char* type;               /**< Data type of the table column */
    const char* creationFlags;      /**< Special flags of the table column */
  };

  static const ColumnDef columns_author[];
  static const ColumnDef columns_copyright[];
  bool createTableAgentEvents(string tablename) const;
  
  static const ColumnDef columns_author_event[];
  static const ColumnDef columns_copyright_event[];
  bool createTableAgentFindings(string tablename) const;
  std::string getColumnCreationString(const ColumnDef in[], size_t size) const;
};

#endif