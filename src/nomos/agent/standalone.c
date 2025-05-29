/*
 SPDX-FileCopyrightText: © Fossology contributors

 SPDX-License-Identifier: GPL-2.0-only
*/
/**
 * \file
 * \brief Dummy implementations of library functions for stand-alone operations
 */
#include "standalone.h"

int result = 0;
int should_connect_to_db = 0;

void  fo_scheduler_heart(int i){}
void  fo_scheduler_connect(int* argc, char** argv, PGconn** db_conn){}
void  fo_scheduler_disconnect(int retcode){}
char* fo_scheduler_next(){return(0);}
char* fo_scheduler_current(){return(0);}
int   fo_scheduler_userID(){return(0);}
void  fo_scheduler_set_special(int option, int value){}
int   fo_scheduler_get_special(int option){return(0);}
char* fo_sysconfig(const char* sectionname, const char* variablename){return(0);}
int  fo_GetAgentKey   (PGconn *pgConn, const char *agent_name, long unused, const char *cpunused, const char *agent_desc){return(0);}
int fo_WriteARS(PGconn *pgConn, int ars_pk, int upload_pk, int agent_pk,
                         const char *tableName, const char *ars_status, int ars_success){return(0);}
PGconn *fo_dbconnect(char *DBConfFile, char **ErrorBuf){return(0);}
int     fo_checkPQcommand(PGconn *pgConn, PGresult *result, char *sql, char *FileID, int LineNumb){return(0);}
int     fo_checkPQresult(PGconn *pgConn, PGresult *result, char *sql, char *FileID, int LineNumb){return(0);}
int     fo_tableExists(PGconn *pgConn, const char *tableName){return(0);}
char * fo_RepMkPath (char *Type, char *Filename){return(0);}
int GetUploadPerm(PGconn *pgConn, long UploadPk, int user_pk){return(10);}

fo_dbManager* fo_dbManager_new(PGconn* dbConnection) {return NULL;}
void fo_dbManager_free(fo_dbManager* dbManager) {}
fo_dbManager_PreparedStatement* fo_dbManager_PrepareStamement_str(fo_dbManager* dbManager, const char* name, const char* query, const char* paramtypes) {return NULL;}
PGresult* fo_dbManager_ExecPrepared(fo_dbManager_PreparedStatement* preparedStatement, ...) {return NULL;}

PGresult* getSelectedPFiles(PGconn* pgConn, int uploadPk, int agentPk, bool ignoreFilesWithMimeType) {return NULL;}
PGresult* checkDuplicateReq(PGconn* pgConn, int uploadPk, int agentPk) {return NULL;}

//ExecStatusType PQresultStatus(const PGresult *res);
int PQresultStatus(const PGresult *res){ return(PGRES_COMMAND_OK);}
char *PQresultErrorMessage(const PGresult *res){return(0);}
char *PQresultErrorField(const PGresult *res, int fieldcode){return(0);}
int  PQntuples(const PGresult *res){return(1);}
PGresult *PQexec(PGconn *conn, const char *query){return(0);}
void PQclear(PGresult *res){}
char *PQgetvalue(const PGresult *res, int tup_num, int field_num){return("1");}
size_t PQescapeStringConn(PGconn *conn, 
                   char *to, const char *from, size_t length, int *error){*error=0;return(0);}
void PQfinish(PGconn *conn){}

