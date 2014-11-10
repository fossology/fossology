/*******************************************************************
 Ununpack: The universal unpacker.
 
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

 ******************
 This time, it's rewritten in C for speed and multithreading.
 *******************************************************************/
#ifndef UNUNPACK_H
#define UNUNPACK_H


#include <ctype.h>
#include <dirent.h>
#include <errno.h>
#include <fcntl.h>
#include <magic.h>
#include <signal.h>
#include <stdio.h>
#include <stdlib.h>
#include <string.h>
#include <time.h>
#include <unistd.h>
#include <libgen.h>

#include <sys/mman.h>
#include <sys/stat.h>
#include <sys/time.h>
//#include <sys/timeb.h>
#include <sys/types.h>
#include <sys/wait.h>

#include "libfossology.h"
#include "checksum.h"
#include "md5.h"
#include "sha1.h"
#include "ununpack-ar.h"
#include "ununpack-disk.h"
#include "ununpack-iso.h"

#define Last(x)	(x)[strlen(x)-1]
#define MAXCHILD        4096
#define MAXSQL  4096
#define PATH_MAX 4096

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
/* ParentInfo relates to the command being executed.
   It is common information needed by Traverse() and stored in CommandInfo
   and Queue structures. */
struct ParentInfo
{
    int Cmd;      /* index into command table used to run this */
    time_t StartTime;     /* time when command started */
    time_t EndTime;       /* time when command ended */
    int ChildRecurseArtifact; /* child is an artifact -- don't log to XML */
    long uploadtree_pk; /* if DB is enabled, this is the parent */
};
typedef struct ParentInfo ParentInfo;

struct unpackqueue
{
    int ChildPid; /* set to 0 if this record is not in use */
    char ChildRecurse[FILENAME_MAX+1]; /* file (or directory) to recurse on */
    int ChildStatus;  /* return code from child */
    int ChildCorrupt; /* return status from child */
    int ChildEnd; /* flag: 0=recurse, 1=don't recurse */
    int ChildHasChild;  /* is the child likely to have children? */
    struct stat ChildStat;
    ParentInfo PI;
};
typedef struct unpackqueue unpackqueue;

struct dirlist
{
    char *Name;
    struct dirlist *Next;
};
typedef struct dirlist dirlist;

/************************************
 ContainerInfo: stucture for storing
 information about a particular file.
 ************************************/
struct ContainerInfo
{
    char Source[FILENAME_MAX];  /* Full source filename */
    char Partdir[FILENAME_MAX];  /* directory name */
    char Partname[FILENAME_MAX];  /* filename without directory */
    char PartnameNew[FILENAME_MAX];  /* new filename without directory */
    int TopContainer; /* flag: 1=yes (so Stat is meaningless), 0=no */
    int HasChild; /* Can this a container have children? (include directories) */
    int Pruned; /* no longer exists due to pruning */
    int Corrupt;  /* is this container/file known to be corrupted? */
    struct stat Stat;
    ParentInfo PI;
    int Artifact; /* this container is an artifact -- don't log to XML */
    int IsDir; /* this container is a directory */
    int IsCompressed; /* this container is compressed */
    long uploadtree_pk; /* uploadtree of this item */
    long pfile_pk;  /* pfile of this item */
    long ufile_mode;  /* ufile_mode of this item */
};
typedef struct ContainerInfo ContainerInfo;

struct cmdlist
{
   char * Magic;
/* use "%s" to mean "output name" -- only allow once "%s" */
/** CMD get concatenated: cmd cmdpre sourcefile cmdpost **/
   char * Cmd;
   char * CmdPre;
   char * CmdPost;
/* MetaCmd is used to extract meta info.  Use '%s' for the filename. */
   char * MetaCmd;
/* Type: 0=compressed 1=packed 2=iso9660 3=disk */
   cmdtype Type;
/* Status 0=unavailable */
   int Status;
/* ModeMask -- Stat(2) st_mode mask for directories and regular files */
   int ModeMaskDir;
   int ModeMaskReg;
/* For correlating with the DB */
   long DBindex;
};
typedef struct cmdlist cmdlist;

/* utils.c */
int  IsInflatedFile(char *FileName, int InflateSize);
int  IsDebianSourceFile(char *Filename);
void SafeExit	(int rc);
void RemovePostfix(char *Name);
void InitCmd ();
int	 TaintString	(char *Dest, int DestLen, char *Src, int ProtectQuotes, char *Replace);
inline int  Prune (char *Fname, struct stat Stat);
inline int	MkDirs	(char *Fname);
inline int	MkDir	(char *Fname);
inline int	IsDir	(char *Fname);
inline int	RemoveDir	(char *dirpath);
int	 IsFile	(char *Fname, int Link);
int	 ReadLine	(FILE *Fin, char *Line, int MaxLine);
int	 IsExe	(char *Exe, int Quiet);
int	 CopyFile	(char *Src, char *Dst);
int  ParentWait();
void CheckCommands (int Show);
int  RunCommand  (char *Cmd, char *CmdPre, char *File, char *CmdPost, char *Out, char *Where);
int  InitMagic();
int  FindCmd (char *Filename);
void FreeDirList (dirlist *DL);
dirlist * MakeDirList (char *Fullname);
void SetDir  (char *Dest, int DestLen, char *Smain, char *Sfile);
void DebugContainerInfo  (ContainerInfo *CI);
int  DBInsertPfile (ContainerInfo *CI, char *Fuid);
int  DBInsertUploadTree  (ContainerInfo *CI, int Mask);
int  AddToRepository (ContainerInfo *CI, char *Fuid, int Mask);
int  DisplayContainerInfo  (ContainerInfo *CI, int Cmd);
char *PathCheck(char *DirPath);
void Usage (char *Name, char *Version);
void deleteTmpFiles(char *dir);
void SQLNoticeProcessor(void *arg, const char *message);

/* traverse.c */
void TraverseStart (char *Filename, char *Label, char *NewDir, int Recurse);
void TraverseChild (int Index, ContainerInfo *CI, char *NewDir);
int  Traverse (char *Filename, char *Basename, char *Label, char *NewDir,
              int Recurse, ParentInfo *PI);

#endif

