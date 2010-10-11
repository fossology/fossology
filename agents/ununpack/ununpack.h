/*******************************************************************
 Ununpack: The universal unpacker.
 
 Copyright (C) 2007 Hewlett-Packard Development Company, L.P.
 
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

 ******************
 This time, it's rewritten in C for speed and multithreading.
 *******************************************************************/
#ifndef UNUNPACK_H
#define UNUNPACK_H

#include <stdlib.h>
#include <libgen.h>

/* specify support for files > 2G */
#define __USE_LARGEFILE64
#define __USE_FILE_OFFSET64

#include <stdio.h>
#include <unistd.h>
#include <string.h>
#include <signal.h>
#include <ctype.h>
#include <errno.h>
#include "libfossrepo.h"
#include "libfossdb.h"
#include "libfossagent.h"

/* for open/close/stat */
#include <sys/types.h>
#define __USE_LARGEFILE64  /* needed for files > 2G */
#include <sys/stat.h>
#include <fcntl.h>
#ifndef __USE_LARGEFILE64
  #define lstat64(x,y) lstat(x,y)
  #define stat64(x,y) stat(x,y)
  typedef struct stat stat_t;
#else
  typedef struct stat64 stat_t;
#endif

/* for mmap (used by CopyFile) */
#include <sys/mman.h>
/* for magic file handling */
#include <magic.h>
/* for dirent */
#include <sys/types.h>
#include <dirent.h>
/* for wait */
#include <sys/types.h>
#include <sys/wait.h>
/* for time */
#include <time.h>

/* for checksums */
#include "checksum.h"
#include "md5.h"
#include "sha1.h"

extern int Verbose;
extern int Quiet;
extern int UnlinkSource;
extern int ForceContinue;
extern int PruneFiles;
extern FILE *ListOutFile;
extern char *Pfile;

/* add by larry for removing main function */
/* ParentInfo relates to the command being executed.
   It is common information needed by Traverse() and stored in CommandInfo
   and Queue structures. */
struct ParentInfo
  {
  int Cmd;      /* index into command table used to run this */
  time_t StartTime;     /* time when command started */
  time_t EndTime;       /* time when command ended */
  int ChildRecurseArtifact; /* child is an artifact -- don't log to XML */
  long uploadtree_pk;   /* if DB is enabled, this is the parent */
  };
typedef struct ParentInfo ParentInfo;

struct unpackqueue
  {
  int ChildPid; /* set to 0 if this record is not in use */
  char ChildRecurse[FILENAME_MAX+1]; /* file (or directory) to recurse on */
  int ChildStatus;      /* return code from child */
  int ChildCorrupt;     /* return status from child */
  int ChildEnd; /* flag: 0=recurse, 1=don't recurse */
  int ChildHasChild;    /* is the child likely to have children? */
  stat_t ChildStat;
  ParentInfo PI;
  };
typedef struct unpackqueue unpackqueue;
#define MAXSQL  4096

extern magic_t MagicCookie;
extern int ForceDuplicate;   /* when using db, should it process duplicates? */
extern int DebugHeartbeat; /* Enable heartbeat and print the time for each */
extern int UseRepository;
extern int MaxThread; /* value between 1 and MAXCHILD */
extern void *DB;  /* the DB repository */
extern void *DBTREE;      /* second DB repository for uploadtree */
extern char *Pfile_Pk; /* PK for *Pfile */
extern char *Upload_Pk; /* PK for upload table */
extern char REP_FILES[16];
extern int UnlinkAll;
extern char Version[];
extern int ReunpackSwitch;
extern char SQL[];
extern int Thread;
extern unpackqueue Queue[];

/*** Global Stats (for summaries) ***/
extern long TotalItems;      /* number of records inserted */
extern int TotalFiles;
extern int TotalCompressedFiles;
extern int TotalDirectories;
extern int TotalContainers;
extern int TotalArtifacts;

void    AlarmDisplay    (int Sig);
int ParentWait();
int     Traverse        (char *Filename, char *Basename,
                         char *Label, char *NewDir,
                         int Recurse, ParentInfo *PI);
void    TraverseStart   (char *Filename, char *Label, char *NewDir,
                         int Recurse);
void    InitCmd ();
void    Usage   (char *Name);
void    CheckCommands   (int Show);
void deleteTmpFiles(char *dir);
int     MyDBaccess      (void *VDB, char *SQL);

enum cmdtype
  {
  CMD_NULL=0,	/* no command */
  CMD_PACK,	/* packed file (i.e., compressed) */
  CMD_RPM,	/* RPM is a special case of CMD_PACK */
  CMD_ARC,	/* archive (contains many files) */
  CMD_AR,	/* ar archive (special case CMD_ARC) */
  CMD_PARTITION, /* File system partition table (special case CMD_ARC) */
  CMD_ISO,	/* ISO9660 */
  CMD_DISK,	/* File system disk */
  CMD_DEB,	/* Debian source package */
  CMD_DEFAULT	/* Default action */
  };
typedef enum cmdtype cmdtype;

#define Last(x)	(x)[strlen(x)-1]

void	SafeExit	(int rc);
int	TaintString	(char *Dest, int DestLen,
			 char *Src, int ProtectQuotes, char *Replace);
inline int	MkDirs	(char *Fname);
inline int	MkDir	(char *Fname);
inline int	IsDir	(char *Fname);
inline int	IsFile	(char *Fname, int Link);
int	IsExe	(char *Exe, int Quiet);
int	CopyFile	(char *Src, char *Dst);
int	ReadLine	(FILE *Fin, char *Line, int MaxLine);
inline int	RemoveDir	(char *dirpath);
#endif

