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
/**
 * \file
 * \brief pkgagent header
 */
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

/**
 * \struct rpmpkginfo
 * \brief Holds meta info of rpm packages
 */
struct rpmpkginfo
{
  char pkgName[256];        ///< RPM package name
  char pkgAlias[256];       ///< Package alias
  char pkgArch[64];         ///< Package architecture
  char version[64];         ///< Package version
  char rpmFilename[256];    ///< RPM file name
  char license[512];        ///< RPM licenses
  char group[128];          ///< Package group
  char packager[1024];      ///< Packager
  char release[64];         ///< Package release
  char buildDate[128];      ///< Package build date
  char vendor[128];         ///< Package vendor
  char url[256];            ///< Package link
  char sourceRPM[256];      ///< Package source
  char summary[MAXCMD];     ///< Package summary
  char description[MAXCMD]; ///< Package description
  long pFileFk;             ///< Package pfile in FOSSology
  char pFile[MAXCMD];       ///< Package pfile hash
  char **requires;          ///< Package dependency list
  int req_size;             ///< Package dependency list size
};

/**
 * \struct debpkginfo
 * \brief Holds meta info of Debian packages
 */
struct debpkginfo
{
  char pkgName[MAXCMD];           ///< Package name
  char source[MAXCMD];            ///< Package source
  char version[MAXCMD];           ///< Package version
  char section[MAXCMD];           ///< Package section
  char priority[MAXCMD];          ///< Package priority
  char pkgArch[MAXCMD];           ///< Package architecture
  int installedSize;              ///< Size of package after install
  char maintainer[MAXCMD];        ///< Package maintainer
  char homepage[MAXCMD];          ///< Package link
  char summary[MAXCMD];           ///< Package summary
  char description[MAXCMD];       ///< Package description
  long pFileFk;                   ///< Package pfile in FOSSology
  char pFile[MAXCMD];             ///< Package pfile hash
  char **depends;                 ///< Package dependency list
  int dep_size;                   ///< Package dependency list size
  char uploaders[MAXCMD];         ///< Package contributors
  char format[MAXCMD];            ///< Package format
  char standardsVersion[MAXCMD];  ///< Package standards version
};

extern int Verbose;             ///< Verbose level
extern PGconn* db_conn;         ///< the connection to Database

int ProcessUpload(long upload_pk);

int GetMetadata(char *pkg, struct rpmpkginfo *pi);

int RecordMetadataRPM(struct rpmpkginfo *pi);

int GetMetadataDebBinary(long upload_pk, struct debpkginfo *pi);

int RecordMetadataDEB(struct debpkginfo *pi);

int GetMetadataDebSource(char *repFile, struct debpkginfo *pi);

void Usage(char *Name);

char *GetFieldValue(char *Sin, char *Field, int FieldMax,char *Value, int ValueMax, char Separator);
#endif /*  _PKGAGENT_H */
