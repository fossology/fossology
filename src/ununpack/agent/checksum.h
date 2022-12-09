/*
 checksum.h - Checksum computation header file

 SPDX-FileCopyrightText: © 2007-2011 Hewlett-Packard Development Company, L.P.

 SPDX-License-Identifier: GPL-2.0-only
*/
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

#ifdef STANDALONE
#include "standalone.h"
#else
#include "libfossology.h"
#endif

/**
 * \brief Store check sum of a file
 */
struct Cksum
{
  uint8_t MD5digest[16];    ///< MD5 digest of the file
  uint8_t SHA1digest[20];   ///< SHA1 digest of the file
  uint64_t DataLen;         ///< Size of the file
};
typedef struct Cksum Cksum;

/**
 * \brief Store file handler and mmap of a file
 */
struct CksumFile
{
  int FileHandle;           ///< File handler for the file
  unsigned char *Mmap;      ///< Mmap of the file
  uint64_t MmapSize;	      ///< Size of mmap
  uint64_t MmapOffset;  	  ///< Index into mmap
};
typedef struct CksumFile CksumFile;

CksumFile *	SumOpenFile	(char *Fname);
void	SumCloseFile	(CksumFile *CF);
int	CountDigits	(uint64_t Num);
Cksum *	SumComputeFile	(FILE *Fin);
Cksum *	SumComputeBuff	(CksumFile *CF);
char *	SumToString	(Cksum *Sum);
int calc_sha256sum(char* filename, char* dst);
#endif
