/**************************************************************
 Copyright (C) 2010-2011 Hewlett-Packard Development Company, L.P.

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
#ifndef _PKGAGENT_H
#define _PKGAGENT_H 1

#include <stdlib.h>
#include <stdio.h>
#include <string.h>
#include <ctype.h>
#include <signal.h>
#include <libgen.h>
#include <time.h>

#include <sys/wait.h>

#include "rpmlib.h"
#include "rpmts.h"
#include "rpmlog.h"
#include "rpmmacro.h"

#include <libfossology.h>

#define MAXCMD 5000
#define MAXLENGTH 256

struct rpmpkginfo 
{
  char pkgName[256];
  char pkgAlias[256];
  char pkgArch[64];
  char version[64];
  char rpmFilename[256];
  char license[512];
  char group[128];
  char packager[1024];
  char release[64];
  char buildDate[128];
  char vendor[128];
  char url[256];
  char sourceRPM[256];
  char summary[MAXCMD];
  char description[MAXCMD];
  long pFileFk;
  char pFile[MAXCMD];
  char **requires;
  int req_size;
};

struct debpkginfo 
{
  char pkgName[MAXCMD];
  char source[MAXCMD];
  char version[MAXCMD];
  char section[MAXCMD];
  char priority[MAXCMD];
  char pkgArch[MAXCMD];
  int installedSize;
  char maintainer[MAXCMD];
  char homepage[MAXCMD];
  char summary[MAXCMD];
  char description[MAXCMD];
  long pFileFk;
  char pFile[MAXCMD];
  char **depends;
  int dep_size;
  char uploaders[MAXCMD];
  char format[MAXCMD];
  char standardsVersion[MAXCMD];
};

extern int Verbose;
extern PGconn* db_conn;        // the connection to Database

int ProcessUpload(long upload_pk);

int GetMetadata(char *pkg, struct rpmpkginfo *pi);

int RecordMetadataRPM(struct rpmpkginfo *pi);

int GetMetadataDebBinary(long upload_pk, struct debpkginfo *pi);

int RecordMetadataDEB(struct debpkginfo *pi);

int GetMetadataDebSource(char *repFile, struct debpkginfo *pi);

void Usage(char *Name);

char *trim(char *str);

char *GetFieldValue(char *Sin, char *Field, int FieldMax,char *Value, int ValueMax, char Separator);
#endif /*  _PKGAGENT_H */
