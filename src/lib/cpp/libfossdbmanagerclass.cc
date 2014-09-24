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

bool DbManager::tableExists(const char* tableName) {
  return fo_dbManager_tableExists(_dbManager, tableName);
}

PGresult* DbManager::queryPrintf(const char* queryStringFormat, ...) {
  va_list args;
  va_start(args, queryStringFormat);
  char* queryString = g_strdup_vprintf(queryStringFormat, args);
  va_end(args);

  if (queryString) {
    PGresult* result = fo_dbManager_Exec_printf(_dbManager, queryString);

    g_free(queryString);

    return result;
  } else {
    return NULL;
  }
}
