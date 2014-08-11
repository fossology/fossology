#ifndef _STANDALONE_H
#define _STANDALONE_H 1
#include <stdlib.h>

typedef int PGconn;
typedef enum
{
    PGRES_EMPTY_QUERY = 0,      /* empty query string was executed */
    PGRES_COMMAND_OK,           /* a query command that doesn't return
                                 * anything was executed properly by the
                                 * backend */
    PGRES_TUPLES_OK,            /* a query command that returns tuples was
                                 * executed properly by the backend, PGresult
                                 * contains the result tuples */
    PGRES_COPY_OUT,             /* Copy Out data transfer in progress */
    PGRES_COPY_IN,              /* Copy In data transfer in progress */
    PGRES_BAD_RESPONSE,         /* an unexpected response was recv'd from the
                                 * backend */
    PGRES_NONFATAL_ERROR,       /* notice or warning message */
    PGRES_FATAL_ERROR           /* query failed */
} ExecStatusType;
#define PG_DIAG_SQLSTATE  0


#define FALSE 0
#define TRUE 1
typedef int PGresult;

#define PERM_WRITE 2
#define LOG_NOTICE(...) { \
            fprintf(stdout, "FATAL %s.%d: ", __FILE__, __LINE__); \
            fprintf(stdout, __VA_ARGS__); \
            fprintf(stdout, "\n"); \
            fflush(stdout); }
#define LOG_FATAL(...) { \
            fprintf(stdout, "FATAL %s.%d: ", __FILE__, __LINE__); \
            fprintf(stdout, __VA_ARGS__); \
            fprintf(stdout, "\n"); \
            fflush(stdout); }
#define LOG_ERROR(...) { \
            fprintf(stdout, "ERROR %s.%d: ", __FILE__, __LINE__); \
            fprintf(stdout, __VA_ARGS__); \
            fprintf(stdout, "\n"); \
            fflush(stdout); }
#define LOG_WARNING(...) { \
            fprintf(stdout, "WARNING %s.%d: ", __FILE__, __LINE__); \
            fprintf(stdout, __VA_ARGS__); \
            fprintf(stdout, "\n"); \
            fflush(stdout); }

extern void  fo_scheduler_heart(int i);
extern void  fo_scheduler_connect(int* argc, char** argv, PGconn** db_conn);
extern void  fo_scheduler_disconnect(int retcode);
extern char* fo_scheduler_next();
extern char* fo_scheduler_current();
extern int   fo_scheduler_userID();
extern void  fo_scheduler_set_special(int option, int value);
extern int   fo_scheduler_get_special(int option);
extern char* fo_sysconfig(char* sectionname, char* variablename);
extern int  fo_GetAgentKey   (PGconn *pgConn, char *agent_name, long unused, char *cpunused, char *agent_desc);
extern int fo_WriteARS(PGconn *pgConn, int ars_pk, int upload_pk, int agent_pk,
                         char *tableName, char *ars_status, int ars_success);
extern PGconn *fo_dbconnect(char *DBConfFile, char **ErrorBuf);
extern int     fo_checkPQcommand(PGconn *pgConn, PGresult *result, char *sql, char *FileID, int LineNumb);
extern int     fo_checkPQresult(PGconn *pgConn, PGresult *result, char *sql, char *FileID, int LineNumb);
extern int     fo_tableExists(PGconn *pgConn, char *tableName);
extern int GetUploadPerm(PGconn *pgConn, long UploadPk, int user_pk);
extern char * fo_RepMkPath (char *Type, char *Filename);


typedef struct {} fo_dbManager;
typedef struct {} fo_dbManager_PreparedStatement;

fo_dbManager* fo_dbManager_new(PGconn* dbConnection);
void fo_dbManager_free(fo_dbManager* dbManager);
fo_dbManager_PreparedStatement* fo_dbManager_PrepareStamement_str(fo_dbManager* dbManager, const char* name, const char* query, const char* paramtypes);
PGresult* fo_dbManager_ExecPrepared(fo_dbManager_PreparedStatement* preparedStatement, ...);

//ExecStatusType PQresultStatus(const PGresult *res);
extern int PQresultStatus(const PGresult *res);
extern char *PQresultErrorMessage(const PGresult *res);
extern char *PQresultErrorField(const PGresult *res, int fieldcode);
extern int  PQntuples(const PGresult *res);
extern PGresult *PQexec(PGconn *conn, const char *query);
extern void PQclear(PGresult *res);
extern char *PQgetvalue(const PGresult *res, int tup_num, int field_num);
extern size_t PQescapeStringConn(PGconn *conn,
                   char *to, const char *from, size_t length,
                   int *error);
extern void PQfinish(PGconn *conn);

#endif
