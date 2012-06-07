/********************************************************
 Copyright (C) 2007-2011 Hewlett-Packard Development Company, L.P.

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

#include "libfossology.h"

extern int Verbose;
extern int Test;

/* for DB */
extern PGconn* db_conn;

#define MAXSQL  1024
#define MAXLINE 1024
#define myBUFSIZ 2048

void DeleteLicense(long UploadId);
void DeleteUpload(long UploadId);
void ListFoldersRecurse(long Parent, int Depth, int Row, int DelFlag);
void ListFolders();
void ListUploads();
void DeleteFolder(long FolderId);
int ReadParameter(char *Parm);
void Usage(char *Name);

#endif /* _DELAGENT_H */
