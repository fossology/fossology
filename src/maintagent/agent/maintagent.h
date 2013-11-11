/***************************************************************
 Copyright (C) 2013 Hewlett-Packard Development Company, L.P.

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
#ifndef _DEMOMOD_H
#define _DEMOMOD_H 1
#include <stdio.h>
#include <stdlib.h>
#include <libgen.h>
#include <unistd.h>
#include <string.h>
#include <strings.h>
#include <ctype.h>
#include <getopt.h>
#include <errno.h>
#include <time.h>
#include <sys/types.h>

#include <libfossology.h>
#define FUNCTION

#define myBUFSIZ	2048

/* File utils.c */
void ExitNow        (int ExitVal);

/* File usage.c */
void Usage          (char *Name);

/* File process.c */
void VacAnalyze();
void ValidateFolders();
void VerifyFilePerms(int fix);
void RemoveUploads();
void RemoveTemps();
void ProcessExpired();
void RemoveOrphanedFiles();

#endif /* _DEMOMOD_H */
