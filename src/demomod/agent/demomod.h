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
#include <sys/types.h>

#include <libfossology.h>
#define FUNCTION

#define myBUFSIZ	2048
#define DataSize    32

/* A structure to put the results of a single file scan */
struct FileResult_struct
{
  char Buf[DataSize];
  char HexStr[(DataSize * 2) + 1];
};
typedef struct FileResult_struct FileResult_t, *pFileResult_t;

void CheckTable     (char *AgentARSName);
void ExitNow        (int ExitVal);
void Usage          (char *Name);
void Char2Hex       (char *InBuf, int NumBytes, char *OutBuf);

int  ProcessUpload  (int upload_pk, int agent_fk);
int  ProcessFile    (char *FilePath, pFileResult_t FileResult);

#endif /* _DEMOMOD_H */
