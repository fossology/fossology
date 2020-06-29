/*
 * Copyright (C) 2014, Siemens AG
 * Author: Daniele Fognini
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License version 2
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 * See the GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software Foundation,
 * Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 */

#ifndef DATABASE_HPP
#define DATABASE_HPP

#include <string>
#include <vector>

#include "libfossdbmanagerclass.hpp"
#include "libfossAgentDatabaseHandler.hpp"
#include "cleanEntries.hpp"

#define MAX_TABLE_CREATION_RETRIES 5

/**
 * \class DatabaseEntry
 * \brief Maps agent data to database schema
 */
class DatabaseEntry
{
public:
  DatabaseEntry();

  long agent_fk;                    /**< Id of agent performed the scan */
  long pfile_fk;                    /**< Id of pfile on which the scan was performed */
  std::string content;              /**< Statement found during the scan */
  std::string hash;                 /**< MD5 hash of the statement */
  /**
   * \brief Type of statement found.
   *
   * Can be
   *   - statement for Copyright
   *   - author for Author
   *   - url for URL
   *   - email for email
   *   - ecc for ECC
   */
  std::string type;
  int copy_startbyte;               /**< Statement start offset from start of pfile content */
  int copy_endbyte;                 /**< Statement end offset from start of pfile content */
};

/**
 * \class CopyrightDatabaseHandler
 * \brief Manages database related requests for agent
 */
class CopyrightDatabaseHandler : public fo::AgentDatabaseHandler
{
public:
  CopyrightDatabaseHandler(fo::DbManager manager);
  CopyrightDatabaseHandler(const CopyrightDatabaseHandler&) = delete;
  CopyrightDatabaseHandler(CopyrightDatabaseHandler&& other) : fo::AgentDatabaseHandler(std::move(other)) {}; // = default
  CopyrightDatabaseHandler spawn() const;

  bool createTables() const;
  bool insertInDatabase(DatabaseEntry& entry) const;
  bool insertNoResultInDatabase(long agentId, long pFileId) const;
  std::vector<unsigned long> queryFileIdsForUpload(int agentId, int uploadId, bool ignoreFilesWithMimeType);

private:
  /**
   * \struct ColumnDef
   * \brief Holds the column related data for table creation
   * \see CopyrightDatabaseHandler::columns
   */
  typedef struct
  {
    const char* name;               /**< Name of the table column */
    const char* type;               /**< Data type of the table column */
    const char* creationFlags;      /**< Special flags of the table column */
  } ColumnDef;

  static const ColumnDef columns[];
  static const ColumnDef columnsDecision[];

  bool createTableAgentFindings() const;
  bool createTableClearing() const;
  std::string getColumnListString(const ColumnDef in[], size_t size) const;
  std::string getColumnCreationString(const ColumnDef in[], size_t size) const;
};

#endif // DATABASE_HPP
