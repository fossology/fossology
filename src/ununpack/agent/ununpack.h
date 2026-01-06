/*
 Ununpack: The universal unpacker.

 SPDX-FileCopyrightText: © 2007-2011 Hewlett-Packard Development Company, L.P.

 SPDX-License-Identifier: GPL-2.0-only

 This time, it's rewritten in C for speed and multithreading.
*/
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

#include "checksum.h"
#include "ununpack-ar.h"
#include "ununpack-disk.h"
#include "ununpack-lzip.h"
#include "ununpack-iso.h"
#include "ununpack-zstd.h"

#ifdef STANDALONE
#include "standalone.h"
#else
#include "libfossology.h"
#endif

#define Last(x)	(x)[strlen(x)-1]
#define MAXCHILD        4096
#define MAXSQL  4096
#define PATH_MAX 4096

/**
 * \brief Classification of tools to use
 */
enum cmdtype
{
  CMD_NULL=0,       /** No command */
  CMD_PACK,	        /** Packed file (i.e., compressed) */
  CMD_RPM,	        /** RPM is a special case of CMD_PACK */
  CMD_ARC,	        /** Archive (contains many files) */
  CMD_AR,	          /** Ar archive (special case CMD_ARC) */
  CMD_PARTITION,    /** File system partition table (special case CMD_ARC) */
  CMD_ISO,	        /** ISO9660 */
  CMD_DISK,	        /** File system disk */
  CMD_DEB,	        /** Debian source package */
  CMD_ZSTD,         /** Zstandard compressed file */
  CMD_LZIP,         /** Lzip compressed file */
  CMD_DEFAULT	      /** Default action */
};

typedef enum cmdtype cmdtype;
/**
 * ParentInfo relates to the command being executed.
 * It is common information needed by Traverse() and stored in CommandInfo
 * and Queue structures.
 */
struct ParentInfo
{
    int Cmd;              /** Index into command table used to run this */
    time_t StartTime;     /** Time when command started */
    time_t EndTime;       /** Time when command ended */
    int ChildRecurseArtifact; /** Child is an artifact -- don't log to XML */
    long uploadtree_pk;   /** If DB is enabled, this is the parent */
};
typedef struct ParentInfo ParentInfo;

/**
 * \brief Queue for files to be unpacked
 */
struct unpackqueue
{
    int ChildPid;           /** Set to 0 if this record is not in use */
    char ChildRecurse[FILENAME_MAX+1]; /** File (or directory) to recurse on */
    int ChildStatus;        /** Return code from child */
    int ChildCorrupt;       /** Return status from child */
    int ChildEnd;           /** Flag: 0=recurse, 1=don't recurse */
    int ChildHasChild;      /** Is the child likely to have children? */
    struct stat ChildStat;  /** Stat structure of child */
    ParentInfo PI;          /** Parent info ptr */
};
typedef struct unpackqueue unpackqueue;

/**
 * \brief Directory linked list
 */
struct dirlist
{
    char *Name;             /** Name of current directory */
    struct dirlist *Next;   /** Link to next directory */
};
typedef struct dirlist dirlist;

/**
 * \brief Structure for storing
 * information about a particular file.
 */
struct ContainerInfo
{
    char Source[FILENAME_MAX];      /** Full source filename */
    char Partdir[FILENAME_MAX];     /** Directory name */
    char Partname[FILENAME_MAX];    /** Filename without directory */
    char PartnameNew[FILENAME_MAX]; /** New filename without directory */
    int TopContainer;               /** Flag: 1=yes (so Stat is meaningless), 0=no */
    int HasChild;                   /** Can this a container have children? (include directories) */
    int Pruned;                     /** No longer exists due to pruning */
    int Corrupt;                    /** Is this container/file known to be corrupted? */
    struct stat Stat;               /** Stat structure of the file */
    ParentInfo PI;                  /** Parent Info ptr */
    int Artifact;                   /** This container is an artifact -- don't log to XML */
    int IsDir;                      /** This container is a directory */
    int IsCompressed;               /** This container is compressed */
    long uploadtree_pk;             /** Uploadtree of this item */
    long pfile_pk;                  /** Pfile of this item */
    long ufile_mode;                /** Ufile_mode of this item */
};
typedef struct ContainerInfo ContainerInfo;

/**
 * \brief Command table's single row
 * \sa CMD
 * \note Use "%s" to mean "output name" -- only allow once "%s"
 * \note CMD get concatenated: cmd cmdpre sourcefile cmdpost
 */
struct cmdlist
{
   char * Magic;      /** Ptr to magic */
   char * Cmd;        /** Command to run */
   char * CmdPre;     /** Prefix for Cmd */
   char * CmdPost;    /** Postfix for Cmd */
   char * MetaCmd;    /** Used to extract meta info. Use '%s' for the filename. */
   cmdtype Type;      /** Type: 0=compressed 1=packed 2=iso9660 3=disk */
   int Status;        /** Status 0=unavailable */
   int ModeMaskDir;   /** ModeMask -- Stat(2) st_mode mask for directories */
   int ModeMaskReg;   /** ModeMask -- Stat(2) st_mode mask for regular files */
   long DBindex;      /** For correlating with the DB */
};
typedef struct cmdlist cmdlist;

/* utils.c */
int  IsInflatedFile(char *FileName, int InflateSize);
int  IsDebianSourceFile(char *Filename);
void SafeExit	(int rc);
void RemovePostfix(char *Name);
void InitCmd ();
int	 TaintString	(char *Dest, int DestLen, char *Src, int ProtectQuotes, char *Replace);
extern int  Prune (char *Fname, struct stat Stat);
extern int	MkDirs	(char *Fname);
extern int	MkDir	(char *Fname);
extern int	IsDir	(char *Fname);
extern int	RemoveDir	(char *dirpath);
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
int ShouldExclude(char *Filename, const char *ExcludePatterns);


/* traverse.c */
void TraverseStart (char *Filename, char *Label, char *NewDir, int Recurse, char *ExcludePatterns);
void TraverseChild (int Index, ContainerInfo *CI, char *NewDir);
int Traverse (char *Filename, char *Basename, char *Label, char *NewDir, int Recurse, ParentInfo *PI, char *ExcludePatterns);

#endif

