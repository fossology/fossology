/************************************************************
 checksum.h - Checksum computation header file

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
 ************************************************************/
#ifndef CHECKSUM_H
#define CHECKSUM_H

#include <stdint.h> /* for uint8_t */
#include <stdlib.h>
#include <stdio.h>
#include <unistd.h>
#include <string.h>
#include <errno.h>
#include <sys/mman.h>
#include <sys/types.h>
#include <sys/stat.h>
#include <fcntl.h>
#include <dirent.h>

#include "libfossology.h"

struct Cksum
  {
  uint8_t MD5digest[16];
  uint8_t SHA1digest[20];
  uint64_t DataLen;
  };
typedef struct Cksum Cksum;

struct CksumFile
  {
  int FileHandle;
  unsigned char *Mmap;
  uint64_t MmapSize;	/* size of mmap */
  uint64_t MmapOffset;	/* index into mmap */
  };
typedef struct CksumFile CksumFile;

CksumFile *	SumOpenFile	(char *Fname);
void	SumCloseFile	(CksumFile *CF);
int	CountDigits	(uint64_t Num);
Cksum *	SumComputeFile	(FILE *Fin);
Cksum *	SumComputeBuff	(CksumFile *CF);
char *	SumToString	(Cksum *Sum);
#endif
