/***************************************************************
 Copyright (C) 2013 Hewlett-Packard Development Company, L.P.
 Copyright (C) 2019 Siemens AG

 This program is free software; you can redistribute it and/or
 modify it under the terms of the GNU General Public License
 version 2 as published by the Free Software Foundation.

 This program is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 GNU General Public License for more details.

 You should have received a copy of the GNU General Public License along
 with this program; if not, write to the Free Software Foundation, Inc.,
 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.

***************************************************************/
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

#endif /* _MAINTAGENT_H */
