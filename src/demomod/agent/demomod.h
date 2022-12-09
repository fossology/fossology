/*
 SPDX-FileCopyrightText: © 2013 Hewlett-Packard Development Company, L.P.

 SPDX-License-Identifier: GPL-2.0-only
*/

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

/** A structure to put the results of a single file scan */
struct FileResult_struct
{
  char Buf[DataSize];               ///< Buffer
  char HexStr[(DataSize * 2) + 1];  ///< Hexadecimal string
};
typedef struct FileResult_struct FileResult_t, *pFileResult_t;

/* File utils.c */
void CheckTable     (char *AgentARSName);
void ExitNow        (int ExitVal);
void Char2Hex       (char *InBuf, int NumBytes, char *OutBuf);

/* File usage.c */
void Usage          (char *Name);

/* File process.c */
int  ProcessUpload  (int upload_pk, int agent_fk);
int  ProcessFile    (char *FilePath, pFileResult_t FileResult);

#endif /* _DEMOMOD_H */
