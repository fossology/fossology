/*
 * Copyright (C) 2014, Siemens AG
 * Author: Daniele Fognini
 *
 * This program is free software; you can redistribute it and/or modify it under the terms of the GNU General Public License version 2 as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along with this program; if not, write to the Free Software Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 */

#ifndef DATABASE_HPP
#define DATABASE_HPP

#include <string>
#include <vector>

#include "libfossdbmanagerclass.hpp"
#include "cleanEntries.hpp"

#define MAX_TABLE_CREATION_RETRIES 5

class CopyrightDatabaseHandler {
public:
  CopyrightDatabaseHandler();
  //CopyrightDatabaseHandler(CopyrightDatabaseHandler&&);
  /*CopyrightDatabaseHandler(const CopyrightDatabaseHandler&) = delete;
  CopyrightDatabaseHandler operator=(const CopyrightDatabaseHandler&) = delete;*/
  ~CopyrightDatabaseHandler();

  bool createTables(const DbManager& dbManager);
  bool insertInDatabase(const DbManager& dbManager, DatabaseEntry& entry) const;
  bool insertNoResultInDatabase(const DbManager& dbManager, long agentId, long pFileId) const;

  std::vector<long> queryFileIdsForUpload(const DbManager& dbManager, int agentId, int uploadId) const;
private:
  typedef struct {
    const char* name;
    const char* type;
    const char* creationFlags;
  } ColumnDef;

  static const ColumnDef columns[];
  static const ColumnDef columnsDecision[];


  bool createTableAgentFindings(const DbManager& dbManager);
  bool createTableClearing(const DbManager& dbManager);

  std::string getColumnListString(const ColumnDef in[], size_t size) ;
  std::string getColumnCreationString( const ColumnDef in[], size_t size) ;
};

#endif // DATABASE_HPP
