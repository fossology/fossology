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

/* specify support for files > 2G */
#define __USE_LARGEFILE64
#define __USE_FILE_OFFSET64

#include <stdio.h>
#include <unistd.h>
#include <string.h>
#include <signal.h>
#include <ctype.h>
#include <errno.h>

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

#endif

