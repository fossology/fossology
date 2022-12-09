/*
 SPDX-FileCopyrightText: © 2011 Hewlett-Packard Development Company, L.P.

 SPDX-License-Identifier: GPL-2.0-only
*/


#include <stdlib.h>
#include <stdio.h>
#include <unistd.h>
#include <string.h>
#include <ctype.h>
#include <signal.h>
#include <magic.h>
#include <libgen.h>

#include "libfossology.h"

#define MAXCMD 1024
extern char SQL[MAXCMD];

extern PGresult *DBMime;
extern int  MaxDBMime;
extern PGconn *pgConn;
extern int Agent_pk;

extern FILE *FMimetype;

extern magic_t MagicCookie;

extern int Akey;
extern char A[MAXCMD];

void    SetEnv  (char *S);
void  DBCheckMime (char *Filename);
int ReadLine(FILE *Fin, char *Line, int MaxLine);
void  Usage (char *Name);
int DBFindMime (char *Mimetype);
