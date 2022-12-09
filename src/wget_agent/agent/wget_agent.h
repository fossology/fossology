/*
 SPDX-FileCopyrightText: © 2011-2014 Hewlett-Packard Development Company, L.P.

 SPDX-License-Identifier: GPL-2.0-only
*/

/*
 * \file wget_agent.h
 */

#ifndef _WGET_AGENT_H
#define _WGET_AGENT_H 1

/* specify support for files > 2G */
#define __USE_LARGEFILE64

#include <stdio.h>
#include <stdlib.h>
#include <unistd.h>
#include <sys/types.h>
#include <sys/stat.h>
#include <sys/wait.h>
#include <string.h>
#include <ctype.h>
#include <signal.h>
#include <grp.h>
#include <libgen.h>

#define lstat64(x,y) lstat(x,y)
#define stat64(x,y) stat(x,y)
#define systemError(line, error, cmd)  LOG_FATAL("[%s:%d] Error exit: %d, CMD:%s\n", __FILE__, line, WEXITSTATUS(error), cmd);

typedef struct stat stat_t;

#include "libfossology.h"

#include "../../ununpack/agent/checksum.h"

#define URLMAX   3072
#define STRMAX   2048
#define FILEPATH 2048

extern char SQL[STRMAX];

/* for the DB */
extern PGconn *pgConn;
/* input for this system */
extern long GlobalUploadKey;
extern char GlobalTempFile[STRMAX];
extern char GlobalURL[URLMAX];
extern char GlobalParam[STRMAX];
extern char GlobalType[STRMAX];
extern int GlobalImportGold; /* set to 0 to not store file in gold repository */
extern gid_t ForceGroup;

/* for debugging */
extern int Debug;

int IsFile(char *Fname, int Link);
void SafeExit  (int rc);
int GetPosition(char *URL);
void DBLoadGold  ();
int     TaintURL(char *Sin, char *Sout, int SoutSize);
int GetURL(char *TempFile, char *URL, char *TempFileDir);
void SetEnv(char *S, char *TempFileDir);
char *PathCheck (char *DirPath);

void  Usage (char *Name);

int Archivefs(char *Path, char *TempFile, char *TempFileDir, struct stat Status);

int GetVersionControl();

void GetProxy();

void replace_url_with_auth();

void MaskPassword();

char* GetVersionControlCommand(int withPassword);

#endif /* _WGET_AGENT_H */

