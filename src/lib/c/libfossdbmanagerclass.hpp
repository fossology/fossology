/*
 * libfossdbmanagerclass.hpp
 *
 *  Created on: Sep 22, 2014
 *      Author: ”J. Najjar”
 */

#ifndef LIBFOSSDBMANAGERCLASS_HPP_
#define LIBFOSSDBMANAGERCLASS_HPP_

extern "C" {
#include "libfossdbmanager.h"
}

class  DbManager{
public :
DbManager(int* argc, char** argv);
DbManager(fo_dbManager* __dbManager);
  ~DbManager();

  PGconn* getConnection();
  DbManager* spawn();

  fo_dbManager* getStruct_dbManager();

private:
  fo_dbManager* _dbManager;


};






#endif /* LIBFOSSDBMANAGERCLASS_HPP_ */
