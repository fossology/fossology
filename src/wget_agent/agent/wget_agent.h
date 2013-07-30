/*********************************************************************
Copyright (C) 2011-2012 Hewlett-Packard Development Company, L.P.

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
 *********************************************************************/

/*
 * \file wget_agent.h
 */

#ifndef _WGET_AGENT_H
#define _WGET_AGENT_H 1

/* specify support for files > 2G */
#define __USE_LARGEFILE64

#include <stdio.h>
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
typedef struct stat stat_t;

#include "libfossology.h"

#include "../../ununpack/agent/checksum.h"

#define MAXCMD  2048

extern char SQL[MAXCMD];

/* for the DB */
extern PGconn *pgConn;
/* input for this system */
extern long GlobalUploadKey;
extern char GlobalTempFile[MAXCMD];
extern char GlobalURL[MAXCMD];
extern char GlobalParam[MAXCMD];
extern char GlobalType[MAXCMD];
extern int GlobalImportGold; /* set to 0 to not store file in gold repository */
extern gid_t ForceGroup;

/* for debugging */
extern int Debug;

/**
 * \brief Given a filename, is it a file?
 */
int IsFile(char *Fname, int Link);

/**
 * \brief Closes the connection to the server. Also frees memory used by the PGconn object;then exit.
 */
void SafeExit  (int rc);

/**
 * \brief Get the position (ending + 1) of http|https|ftp:// of one url
 */
int GetPosition(char *URL);

/**
 * \brief Insert a file into the database and repository.
 *        This mimicks the old webgoldimport.
 */
void DBLoadGold  ();

/**
 * \brief Given a URL string, taint-protect it.
 */
int     TaintURL(char *Sin, char *Sout, int SoutSize);

/**
 * \brief Do the wget.
 */
int GetURL(char *TempFile, char *URL, char *TempFileDir);

/**
 * \brief Convert input pairs into globals.
 *        This functions taints the parameters as needed.
 */
void SetEnv(char *S, char *TempFileDir);

/**
 * \brief Substitute Hostname for %H in path
 */
char *PathCheck (char *DirPath);

/**
 * \brief Here are some suggested options
 */
void  Usage (char *Name);

int Archivefs(char *Path, char *TempFile, char *TempFileDir, struct stat Status);

int GetVersionControl();

void GetProxy();

#endif /* _WGET_AGENT_H */

