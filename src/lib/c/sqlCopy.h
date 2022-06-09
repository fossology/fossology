/*
 SPDX-FileCopyrightText: © 2011 Hewlett-Packard Development Company, L.P.

 SPDX-License-Identifier: LGPL-2.1-only
*/
#ifndef _SQLCOPY_H
#define _SQLCOPY_H

#include <stdlib.h>
#include <stdio.h>
#include <stdarg.h>
#include <errno.h>
#include <unistd.h>
#include <string.h>
#include <ctype.h>
#include <time.h>
#include <libpq-fe.h>
#include "libfossology.h"    /* for the libfossdb error checking functions */

#define ERROR_RETURN(Msg) {\
        printf("ERROR: %s:%d, %s\n   %s\n", __FILE__, __LINE__, Msg, strerror(errno)); \
        return(0);}

/** fo_sqlCopy for batch copy (mass inserts) */
struct sqlCopy_struct
{
  /** Database connection */
  PGconn* pGconn;
  /** Database table to copy (insert) into */
  char* TableName;
  /** Comma separated list of column names */
  char ColumnNames[1024];
  /** Number of bytes allocated to DataBuf */
  int BufSize;
  /** Index into DataBuf where the next data is added */
  int DataIdx;
  char* DataBuf;           /** Data to insert */
};
typedef struct sqlCopy_struct sqlCopy_t, * psqlCopy_t;

psqlCopy_t fo_sqlCopyCreate(PGconn* pGconn, char* TableName, int BufSize, int NumColumns, ...);
int fo_sqlCopyAdd(psqlCopy_t pCopy, char* DataRow);
int fo_sqlCopyExecute(psqlCopy_t pCopy);
void fo_sqlCopyDestroy(psqlCopy_t pCopy, int ExecuteFlag);
void fo_sqlCopyPrint(psqlCopy_t pCopy, int PrintRows);

#endif  /* _SQLCOPY_H */
