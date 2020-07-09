/********************************************************
 Copyright (C) 2007-2012 Hewlett-Packard Development Company, L.P.
 Copyright (C) 2015-2019 Siemens AG

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

 ********************************************************/
/**
 * \headerfile ""
 * Contains all the functions supported by delagent
 */
#ifndef _DELAGENT_H
#define _DELAGENT_H 1

#include <stdlib.h>
#include <stdio.h>
#include <unistd.h>
#include <stdint.h>
#include <ctype.h>
#include <string.h>
#include <dirent.h>
#include <time.h>
#include <signal.h>
#include <libgen.h>
#include <getopt.h>
#include <gcrypt.h>

#include "libfossology.h"

extern int Verbose;
/**
 * \var int Test
 * Set if working in test mode else 0
 */
extern int Test;

/* for DB */
extern PGconn* pgConn;

/**
 * \def MAXSQL
 * Maximum length of SQL commands
 */
#define MAXSQL  2048
/**
 * \def MAXSQLFolder
 * Maximum length of folder address
 */
#define MAXSQLFolder 1024
/**
 * \def MAXLINE
 * Maximum length of a line
 */
#define MAXLINE 1024
/**
 * \def myBUFSIZ
 * Maximum buffer size
 */
#define myBUFSIZ 2048

/* authentication and permission checking */
int authentication(char *user, char * password, int *userId, int *userPerm);

int check_permission_upload(int wantedPermissions, long uploadId, int userId, int userPerm);
int check_read_permission_upload(long upload_id, int userId, int userPerm);
int check_write_permission_upload(long upload_id, int userId, int userPerm);
int check_permission_folder(long folder_id, int userId, int userPerm);
int check_permission_license(long license_id, int userPerm);

/* functions that list things */
int listFolders(int userId, int userPerm);
int listUploads(int userId, int userPerm);
int listFoldersRecurse(long Parent, int Depth, long Row, int DelFlag, int userId, int userPerm);

/* function that delete actual things */
int deleteUpload(long uploadId, int userId, int userPerm);
int deleteFolder(long cFolder, long pFolder, int userId, int userPerm);
int unlinkContent(long child, long parent, int mode, int userId, int userPerm);

/* for usage from scheduler */
void doSchedulerTasks();

/* misc */
void usage(char *Name);
void exitNow(int exitVal);
#endif /* _DELAGENT_H */
