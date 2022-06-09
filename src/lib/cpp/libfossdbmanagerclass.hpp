/*
 Author: Johannes Najjar, Cedric Bodet, Andreas Wuerl, Daniele Fognini
 SPDX-FileCopyrightText: © 2014 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

#ifndef LIBFOSSDBMANAGERCLASS_HPP_
#define LIBFOSSDBMANAGERCLASS_HPP_

#include "libfossdbmanager.h"

#include <cstdarg>
#include <vector>
#include <string>


#include "libfossdbQueryResult.hpp"

/**
 * \file
 * \brief DB wrapper for agents
 */

namespace fo
{
  /**
   * \class DbManagerStructDeleter
   * \brief DB manager deleter (for shared pointer)
   */
  class DbManagerStructDeleter
  {
  public:
    /**
     * Called by shared pointer destructor
     * @param d DB manager to be deleted
     */
    void operator ()(fo_dbManager* d)
    {
      fo_dbManager_finish(d);
    }
  };

  /**
   * \class DbManager
   * \brief DB wrapper for agents
   */
  class DbManager
  {
  public :
    DbManager(int* argc, char** argv);
    DbManager(fo_dbManager* dbManager);

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

  private:
    unptr::shared_ptr <fo_dbManager> dbManager;    ///< Shared DB manager
  };
}

#endif /* LIBFOSSDBMANAGERCLASS_HPP_ */
