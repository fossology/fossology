/************************************************************
 librep: A set of functions for accessing the file repository.

 Copyright (C) 2007 Hewlett-Packard Development Company, L.P.
 
 This library is free software; you can redistribute it and/or
 modify it under the terms of the GNU Lesser General Public
 License version 2.1 as published by the Free Software Foundation.

 This library is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU
 Lesser General Public License for more details.

 You should have received a copy of the GNU Lesser General Public License
 along with this library; if not, write to the Free Software Foundation, Inc.0
 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA

 **************************
 The repository assumes some directories are mounted.
 The layout looks like:

  /etc/ossdb/repository/
     RepPath.conf # contains path to the mounted directory
	# If it does not exist, then "." is used.
     Depth.conf	# contains a number for the current depth
     Hosts.conf	# list of hosts and hex ranges (used to find host with data)

  Use $REPCONF to select a different location (other than
  /etc/ossdb/repository).

  Under the RepPath.conf directory:
     host1/	# Each host has a directory. This can be mounted or local
     host1/type/	# Type describes the repository (e.g., gold, file, license)
     host1/type/00/	# Directories are lowercase octets (00 to ff)
     host1/type/00/00/	# Directories are lowercase octets (00 to ff)
     host1/type/00/00/sha1.md5.len	# Files are lowercase octets and digits
     host2/	# Each host has a directory. This can be mounted or local
     host2/type/	# Type describes the repository (e.g., gold, file, license)
     host2/type/00/	# Directories are lowercase octets (00 to ff)
     host2/type/00/00/	# Directories are lowercase octets (00 to ff)
  If "host" is not defined in Hosts.conf, then it is omitted.
 ************************************************************/

#ifndef LIBFOSSREPO_H
#define LIBFOSSREPO_H

#include <stdlib.h>
#include <stdint.h>

/* General Repository usage */
int	RepOpen	();	/* call before using any other function */
void	RepClose	();	/* call after using all other functions */

/* Get info -- caller must free() it. */
char *	RepGetRepPath	(); /* path to mounted repository */
char *	RepGetHost	(char *Type, char *Filename);
char *	RepMkPath	(char *Type, char *Filename);

/* Sanity checks */
int	RepExist	(char *Type, char *Filename);
int	RepHostExist	(char *Type, char *Host);

/* Removal */
int	RepRemove	(char *Type, char *Filename);

/* Replacements for fopen/fclose */
FILE *	RepFread	(char *Type, char *Filename);
FILE *	RepFwrite	(char *Type, char *Filename);
int	RepFclose	(FILE *F);
int	RepImport	(char *Source, char *Type, char *Filename, int HardLink);

/* Replacements for mmap */
struct RepMmapStruct
  {
  int FileHandle; /* handle from open() */
  unsigned char *Mmap; /* memory pointer from mmap */
  uint32_t MmapSize; /* size of file mmap */
  uint32_t _MmapSize; /* real size of mmap (set to page boundary) */
  };
typedef struct RepMmapStruct RepMmapStruct;
void	RepMunmap	(RepMmapStruct *M);
RepMmapStruct * RepMmap	(char *Type, char *Filename);
RepMmapStruct * RepMmapFile	(char *FullFilename);

#endif

