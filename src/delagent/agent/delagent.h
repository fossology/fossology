/********************************************************
 Copyright (C) 2007-2012 Hewlett-Packard Development Company, L.P.
 Copyright (C) 2015-2016 Siemens AG

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
#include <openssl/sha.h>

#include "libfossology.h"

extern int Verbose;
extern int Test;

/* for DB */
extern PGconn* db_conn;

#define MAXSQL  1024
#define MAXLINE 1024
#define myBUFSIZ 2048

/* authentication and permission checking */
int authentication(char *user, char * password, int *user_id, int *user_perm);

int check_permission_upload(int wantedPermissions, long upload_id, int user_id, int user_perm);
int check_read_permission_upload(long upload_id, int user_id, int user_perm);
int check_write_permission_upload(long upload_id, int user_id, int user_perm);
int check_permission_folder(long folder_id, int user_id, int user_perm);
int check_permission_license(long license_id, int user_perm);

/* functions that list things */
int listFolders(int user_id, int user_perm);
int listUploads(int user_id, int user_perm);

/* function that delete actual things */
int deleteLicense(long UploadId, int user_perm);
int deleteUpload(long UploadId, int user_id, int user_perm);
int deleteFolder(long FolderId, int user_id, int user_perm);

/* for usage from scheduler */
void doSchedulerTasks();

/* misc */
void usage(char *Name);

#endif /* _DELAGENT_H */
