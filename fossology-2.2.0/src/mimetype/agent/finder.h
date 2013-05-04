/***************************************************************
 Copyright (C) 2011 Hewlett-Packard Development Company, L.P.

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

/** for the DB */
extern PGresult *DBMime; /* contents of mimetype table */
extern int  MaxDBMime; /* how many rows in DBMime */
extern PGconn *pgConn;
extern int Agent_pk; /* agent identifier */

/** for /etc/mime.types */
extern FILE *FMimetype;

/* for Magic */
extern magic_t MagicCookie;

/** input for this system */
extern int Akey;
extern char A[MAXCMD];

/**
 * \brief Convert field=value pairs into variables: A and Akey.
 */
void    SetEnv  (char *S);

/**
 * \brief Given a file, check if it has a mime type
 * in the DB.  If it does not, then add it.
 */
void  DBCheckMime (char *Filename);

/**
 * \brief read a line each time from one file
 */
int ReadLine(FILE *Fin, char *Line, int MaxLine);

/**
 * \brief Here are some suggested options
 */
void  Usage (char *Name);

/**
 * \brief find a mime type in the DBMime table.
 */
int DBFindMime (char *Mimetype);

