/*
 SPDX-FileCopyrightText: © 2020 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

#ifndef _STANDALONE_H
#define _STANDALONE_H 1
#include <stdlib.h>
#include <stdbool.h>

typedef int PGconn;
typedef int PGresult;

#define PG_DIAG_SQLSTATE  0
#define PGRES_COMMAND_OK 1
#define PGRES_NONFATAL_ERROR 2

#ifndef FALSE
#define FALSE 0
#endif

#ifndef TRUE
#define TRUE 1
#endif

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

#define LOG_DEBUG(...) { \
            fprintf(stdout, "DEBUG %s.%d: ", __FILE__, __LINE__); \
            fprintf(stdout, __VA_ARGS__); \
            fprintf(stdout, "\n"); \
            fflush(stdout); }

extern void  fo_scheduler_heart(int i);
extern void  fo_scheduler_connect(int* argc, char** argv, PGconn** db_conn);
extern void  fo_scheduler_disconnect(int retcode);
extern char* fo_scheduler_next();
extern int   fo_scheduler_userID();
extern void  fo_scheduler_set_special(int option, int value);
extern int   fo_scheduler_get_special(int option);
extern char* fo_sysconfig(const char* sectionname, const char* variablename);
extern int   fo_GetAgentKey   (PGconn *pgConn,const char *agent_name, long unused, const char *cpunused, const char *agent_desc);
extern int   fo_WriteARS(PGconn *pgConn, int ars_pk, int upload_pk, int agent_pk,
                        const char *tableName, const char *ars_status, int ars_success);

extern int   fo_checkPQcommand(PGconn *pgConn, PGresult *result, char *sql, char *FileID, int LineNumb);
extern int   fo_checkPQresult(PGconn *pgConn, PGresult *result, char *sql, char *FileID, int LineNumb);
extern int   fo_tableExists(PGconn *pgConn, const char *tableName);
extern int   GetUploadPerm(PGconn *pgConn, long UploadPk, int user_pk);
extern int   fo_CreateARSTable(PGconn* pgConn, const char* tableName);

typedef struct {} fo_dbManager;

extern int   PQresultStatus(const PGresult *res);
extern char* PQresultErrorMessage(const PGresult *res);
extern char* PQresultErrorField(const PGresult *res, int fieldcode);
extern int   PQntuples(const PGresult *res);
extern PGresult *PQexec(PGconn *conn, const char *query);
extern void  PQclear(PGresult *res);
extern char* PQgetvalue(const PGresult *res, int tup_num, int field_num);
extern size_t PQescapeStringConn(PGconn *conn,
                   char *to, const char *from, size_t length,
                   int *error);
extern void  PQfinish(PGconn *conn);

extern char* fo_RepMkPath (char *Type, char *Filename);
extern int   fo_RepExist(char* Type, char* Filename);
extern int   fo_RepExist2(char* Type, char* Filename);
extern int   fo_RepImport(char* Source, char* Type, char* Filename, int HardLink);
#endif
