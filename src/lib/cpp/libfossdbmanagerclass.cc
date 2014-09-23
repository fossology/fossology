/*
 * libfossdbmanagerclass.cc
 *
 *  Created on: Sep 22, 2014
 *      Author: ”J. Najjar”
 */

#include "libfossdbmanagerclass.hpp"

extern "C" {
#include "libfossscheduler.h"
}

DbManager::DbManager(int* argc, char** argv){
  fo_scheduler_connect_dbMan(argc,argv,&_dbManager);
};

DbManager::DbManager(fo_dbManager* __dbManager): _dbManager(__dbManager){

};

DbManager::~DbManager(){
  fo_dbManager_finish(_dbManager);
};

PGconn* DbManager::getConnection(){
  return fo_dbManager_getWrappedConnection(_dbManager);
}


DbManager* DbManager::spawn(){
  return new DbManager(fo_dbManager_fork(_dbManager));
}


fo_dbManager* DbManager::getStruct_dbManager(){
  return _dbManager;
}
