/*
 SPDX-FileCopyrightText: © 2013 Hewlett-Packard Development Company, L.P.
 SPDX-FileCopyrightText: © 2019 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

#ifndef _MAINTAGENT_H
#define _MAINTAGENT_H 1
#include <stdio.h>
#include <stdlib.h>
#include <libgen.h>
#include <unistd.h>
#include <string.h>
#include <strings.h>
#include <ctype.h>
#include <getopt.h>
#include <errno.h>
#include <time.h>
#include <sys/types.h>
#include <sys/stat.h>

#include <libfossology.h>
#define FUNCTION

/* for DB */
extern PGconn* pgConn;
extern fo_dbManager* dbManager;

/**
 * Maximum buffer to use
 */
#define myBUFSIZ 2048

/**
 * Maximum length of SQL commands
 */
#define MAXSQL 1024

/* File utils.c */
void exitNow(int exitVal);
void recurseDir(const char* type, char* path, int level);
void checkPFileExists(char* sha1, char* md5, long fsize, const char* type);
void deleteRepoFile(char* sha1, char* md5, long fsize, const char* type);

/* File usage.c */
void usage(char *name);

/* File process.c */
void vacAnalyze();
void validateFolders();
void verifyFilePerms(int fix);
void removeUploads();
void removeTemps();
void processExpired();
void removeOrphanedFiles();
void deleteOrphanGold();
void normalizeUploadPriorities();
void reIndexAllTables();
void removeOrphanedRows();
void removeOrphanedLogFiles();
void removeExpiredTokens();
void deleteOldGold(char* date);
void removeOldLogFiles(const char* olderThan);

/* timing helpers: gated extra output when agent_verbose >= 3 */
double now_monotonic_seconds(void);
void log_action_start(const char* action);
void log_action_end(const char* action, double start);

#endif /* _MAINTAGENT_H */
