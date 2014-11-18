/*
Author: Johannes Najjar, Cedric Bodet, Andreas Wuerl, Daniele Fognini
Copyright (C) 2014, Siemens AG

This program is free software; you can redistribute it and/or modify it under the terms of the GNU General Public License version 2 as published by the Free Software Foundation.

This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.

You should have received a copy of the GNU General Public License along with this program; if not, write to the Free Software Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
*/

#ifndef LIBFOSSDBMANAGERCLASS_HPP_
#define LIBFOSSDBMANAGERCLASS_HPP_

extern "C" {
#include "libfossdbmanager.h"
}

#include <cstdarg>
#include <vector>
#include <string>


#include "libfossdbQueryResult.hpp"

namespace fo
{
  class DbManagerStructDeleter
  {
  public:
    void operator ()(fo_dbManager* d)
    {
      fo_dbManager_finish(d);
    }
  };

  class DbManager
  {
  public :
    DbManager(int* argc, char** argv);
    DbManager(fo_dbManager* dbManager);
    DbManager(DbManager&&);
    DbManager(const DbManager&);
    ~DbManager();

    PGconn* getConnection() const;
    DbManager spawn() const;

    fo_dbManager* getStruct_dbManager() const;
    bool tableExists(const char* tableName) const;
    bool sequenceExists(const char* name) const;
    bool begin() const;
    bool commit() const;
    bool rollback() const;
    void ignoreWarnings(bool) const;

    QueryResult queryPrintf(const char* queryFormat, ...) const;
    QueryResult execPrepared(fo_dbManager_PreparedStatement* stmt, ...) const;

    std::string queryUploadTreeTableName(int uploadId);

  private:
    unptr::shared_ptr <fo_dbManager> dbManager;
  };
}

#endif /* LIBFOSSDBMANAGERCLASS_HPP_ */
