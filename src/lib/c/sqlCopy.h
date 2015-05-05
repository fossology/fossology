/**************************************************************
Copyright (C) 2011 Hewlett-Packard Development Company, L.P.

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
**************************************************************/
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

/* fo_sqlCopy for batch copy (mass inserts) */
struct sqlCopy_struct
{
  PGconn* PGconn;
  /* Database connection */
  char* TableName;
  /* Database table to copy (insert) into */
  char ColumnNames[1024];
  /* Comma separated list of column names */
  int BufSize;
  /* Number of bytes allocated to DataBuf */
  int DataIdx;
  /* Index into DataBuf where the next data is added */
  char* DataBuf;           /* Data to insert */
};
typedef struct sqlCopy_struct sqlCopy_t, * psqlCopy_t;

psqlCopy_t fo_sqlCopyCreate(PGconn* PGconn, char* TableName, int BufSize, int NumColumns, ...);
int fo_sqlCopyAdd(psqlCopy_t pCopy, char* DataRow);
int fo_sqlCopyExecute(psqlCopy_t pCopy);
void fo_sqlCopyDestroy(psqlCopy_t pCopy, int ExecuteFlag);
void fo_sqlCopyPrint(psqlCopy_t pCopy, int PrintRows);

#endif  /* _SQLCOPY_H */
