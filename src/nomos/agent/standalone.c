#include "standalone.h"

int result = 0;

void  fo_scheduler_heart(int i){}
void  fo_scheduler_connect(int* argc, char** argv, PGconn** db_conn){}
void  fo_scheduler_disconnect(int retcode){}
char* fo_scheduler_next(){}
char* fo_scheduler_current(){}
int   fo_scheduler_userID(){}
void  fo_scheduler_set_special(int option, int value){}
int   fo_scheduler_get_special(int option){}
char* fo_sysconfig(char* sectionname, char* variablename){}
int  fo_GetAgentKey   (PGconn *pgConn, char *agent_name, long unused, char *cpunused, char *agent_desc){};
int fo_WriteARS(PGconn *pgConn, int ars_pk, int upload_pk, int agent_pk,
                         char *tableName, char *ars_status, int ars_success){};
PGconn *fo_dbconnect(char *DBConfFile, char **ErrorBuf){};
int     fo_checkPQcommand(PGconn *pgConn, PGresult *result, char *sql, char *FileID, int LineNumb){};
int     fo_checkPQresult(PGconn *pgConn, PGresult *result, char *sql, char *FileID, int LineNumb){};
int     fo_tableExists(PGconn *pgConn, char *tableName){};
char * fo_RepMkPath (char *Type, char *Filename){};
int GetUploadPerm(PGconn *pgConn, long UploadPk, int user_pk){return(10);};


//ExecStatusType PQresultStatus(const PGresult *res);
int PQresultStatus(const PGresult *res){ return(PGRES_COMMAND_OK);}
char *PQresultErrorMessage(const PGresult *res){}
char *PQresultErrorField(const PGresult *res, int fieldcode){}
int  PQntuples(const PGresult *res){return(1);}
PGresult *PQexec(PGconn *conn, const char *query){}
void PQclear(PGresult *res){}
char *PQgetvalue(const PGresult *res, int tup_num, int field_num){return("1");}
size_t PQescapeStringConn(PGconn *conn, 
                   char *to, const char *from, size_t length, int *error){*error=0;}
void PQfinish(PGconn *conn){}

