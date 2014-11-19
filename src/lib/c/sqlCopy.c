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

/*!
 * \file sqlCopy.c
 * \brief sqlCopy buffers sql inserts and performs batch copy's
 *        to the database.  Why do this?  Because this method is 
 *        roughtly 15x faster than individual sql inserts for a
 *        typical fossology table insert.
 *
 * Note that data to be inserted is stored in memory (pCopy->DataBuf), 
 * not an external file.  So the caller should give some consideration 
 * to the number of records buffered.
 *
 *\code
 * How to use:
 * 1. Get an sqlCopy_struct pointer from fo_sqlCopyCreate().
 * 2. Add records you want inserted into a single table in the
 *    database with fo_sqlCopyAdd().  This will buffer the recs
 *    until no more data will fit into DataBuf.  At that point the
 *    records will be added to the database table in a single copy stmt.
 * 3. When the program is done, call fo_sqlCopyDestroy().  This will flush
 *    the remaining records out of the buffer and free memory.
 *
 * Two other functions may also come in handy:
 * 1. fo_sqlCopyExecute() will execute the copy to database immediately.
 * 2. fo_sqlCopyPrint() will print the sqlCopy structure. 
 *    It is good for debugging.
\endcode
 */

#include "sqlCopy.h"

/*!
 \brief Constructor for sqlCopy_struct.  

 \param PGconn  Database connection
 \param TableName
 \param BufSize  Size of the copy buffer in bytes.
                If BufSize is smaller than needed to hold any
                single row, then BufSize is automatically increased.
 \param NumColumns  number of column names passed in.
 \param ...     Variable char *ColumnNames

 \return sqlCopy_struct.
         On failure, ERROR to stdout, return 0
*/
psqlCopy_t fo_sqlCopyCreate(PGconn* PGconn, char* TableName, int BufSize, int NumColumns, ...)
{
  psqlCopy_t pCopy;
  va_list ColumnNameArg;
  int ColIdx;
  int ColStrLength;
  char* ColStr;

  va_start(ColumnNameArg, NumColumns);

  /* Allocate the structure */
  pCopy = malloc(sizeof(sqlCopy_t));
  if (!pCopy)
  ERROR_RETURN("sqlCopy malloc")

  /* Allocate storage for the data buffer */
  if (BufSize < 1) BufSize = 1;
  pCopy->DataBuf = calloc(BufSize, sizeof(char));

  /* Save TableName */
  pCopy->TableName = strdup(TableName);

  /* check for malloc failures */
  if ((!pCopy->DataBuf) || (!pCopy->TableName))
  {
    free(pCopy);
    ERROR_RETURN("sqlCopyCreate")
  }

  /* Save the DB connection */
  pCopy->PGconn = PGconn;

  /* Save the data buffer size  */
  pCopy->BufSize = BufSize;

  /* Data buffer is empty */
  pCopy->DataIdx = 0;

  /* Build the column name string pCopy->ColumnNames  */
  ColStrLength = 0;
  pCopy->ColumnNames[0] = 0;
  for (ColIdx = 0; ColIdx < NumColumns; ColIdx++)
  {
    ColStr = va_arg(ColumnNameArg, char *);
    ColStrLength += strlen(ColStr) + 1;  /* extra 1 for the comma */
    if (ColStrLength < sizeof(pCopy->ColumnNames))
    {
      if (ColIdx != 0) strncat(pCopy->ColumnNames, ",", 1);
      strncat(pCopy->ColumnNames, ColStr, ColStrLength);
    }
    else
    {
      fo_sqlCopyDestroy(pCopy, 0);
      ERROR_RETURN("pCopy->ColumnNames size too small")
    }
  }
  va_end(ColumnNameArg);
  return (pCopy);
}  /* End fo_sqlCopyCreate()  */


#ifdef DEBUG
int tmp_printhex(char * str)
{
  while(*str) printf("%02x", *str++);
  return(0);
}
#endif

/*!
 \brief Add a data row to an sqlCopy 
 Use '\N' to pass in a null

 \param pCopy Pointer to sqlCopy struct
 \param DataRow Row to insert

\verbatim
 The fields in DataRow needs to be tab delimited.  
 All strings should be escaped with PQescapeStringConn()

 For example, to insert a row with two character fields and an
 integer field, DataRow might look like:
   Mydata<tab>string  <tab> string number 2 <tab> 36
 This could be created by:
   snprintf(buf, sizeof(buf), "%s\t%s\t%d\n", str1, str2, val);
\endverbatim
 \return 0 if failure
*/
#define growby  128  //Grow DataBuf by this number of bytes.

int fo_sqlCopyAdd(psqlCopy_t pCopy, char* DataRow)
{
  int NewRowLen;
  int rncount = 0;
  char* dptr = DataRow;
  char* NewRow = 0, * nptr;

  /* As of Postgresql 8.4, COPY will not accept embedded literal carriage returns
   * or line feeds.  Use "\r" and "\n" instead.
   * Count how many we need to get rid of (and make space for).
   */
  while (*dptr)
  {
    if (((*dptr == '\n') || (*dptr == '\r')) && (*(dptr + 1))) rncount++;
    dptr++;
  }

  /* Substitute any literal '\n' or '\r' for string "\n", "\r" 
   * (except for trailing \n which is required)
   */
  if (rncount)
  {
    NewRowLen = strlen(DataRow) + rncount;
    NewRow = malloc(NewRowLen + 1);  // plus 1 for potential required \n at end
    if (!NewRow)
    ERROR_RETURN("fo_sqlCopyAdd: out of memory");
    nptr = NewRow;
    dptr = DataRow;
    while (*dptr && *(dptr + 1))
    {
      if (*dptr == '\n')
      {
        *nptr++ = '\\';
        *nptr = 'n';
      }
      else if (*dptr == '\r')
      {
        *nptr++ = '\\';
        *nptr = 'r';
      }
      else
        *nptr = *dptr;
      ++dptr;
      ++nptr;
    }
    *nptr = 0;  // null terminator
    DataRow = NewRow;
  }

  /* Does new record fit in DataBuf?  */
  NewRowLen = strlen(DataRow);
  if ((pCopy->BufSize - pCopy->DataIdx) < (NewRowLen + 1))
  {
    /* if DataIdx is zero, then DataBuf isn't big enough to hold
     * this record.  In this case make DataBuf larger.
     */
    if (pCopy->DataIdx == 0)
    {
      pCopy->DataBuf = realloc(pCopy->DataBuf, NewRowLen + growby);
      if (!pCopy->DataBuf)
      ERROR_RETURN("fo_sqlCopyAdd: Realloc for DataBuf failed");
      pCopy->BufSize = NewRowLen + growby;
    }
    else
    {
      /* Execute a copy to make room in DataBuf */
      fo_sqlCopyExecute(pCopy);
    }
  }

  /* copy in DataRow */
  strcpy(pCopy->DataBuf + pCopy->DataIdx, DataRow);
  pCopy->DataIdx += NewRowLen;

  /* If the DataRow was missing a terminating newline, add one */
  if (DataRow[NewRowLen - 1] != '\n')
  {
    pCopy->DataBuf[pCopy->DataIdx++] = '\n';
    pCopy->DataBuf[pCopy->DataIdx] = 0;  // new null terminator
  }

  if (NewRow) free(NewRow);
  return (1);
}

/*!
 \brief Execute the copy (ie insert the buffered records into the
 database.
 Then reset pCopy (effectively empty it).

 \param pCopy  Pointer to sqlCopy struct

 \return 0 on Failure (with msg), 1 on success.
*/
int fo_sqlCopyExecute(psqlCopy_t pCopy)
{
  char copystmt[2048];
  PGresult* result;

  /* check pCopy */
  if (!pCopy)
  ERROR_RETURN("Null pCopy");
  if (pCopy->DataIdx == 0) return (1);  /* nothing to copy */

  /* Start the Copy command */
  sprintf(copystmt, "COPY %s(%s) from stdin",
    pCopy->TableName,
    pCopy->ColumnNames);
  result = PQexec(pCopy->PGconn, copystmt);
  if (PGRES_COPY_IN == PQresultStatus(result))
  {
    PQclear(result);
    if (PQputCopyData(pCopy->PGconn, pCopy->DataBuf, pCopy->DataIdx) != 1)
    ERROR_RETURN(PQresultErrorMessage(result))
  }
  else if (fo_checkPQresult(pCopy->PGconn, result, copystmt, __FILE__, __LINE__)) return 0;


  /* End copy  */
  if (PQputCopyEnd(pCopy->PGconn, NULL) == 1)
  {
    result = PQgetResult(pCopy->PGconn);
    if (fo_checkPQcommand(pCopy->PGconn, result, "copy end", __FILE__, __LINE__)) return 0;
  }
  PQclear(result);

  /* reset DataBuf */
  pCopy->DataIdx = 0;

  return (1);
}


/*!
 \brief Destructor for sqlCopy_struct.  This will execute CopyExecute
 if the ExecuteFlag is true and there are records that need
 to be written.

 \param pCopy Pointer to sqlCopy struct
 \param ExecuteFlag  0 if DataRows should not be written,
                           1 if DataRows should be written

 \return void
*/
void fo_sqlCopyDestroy(psqlCopy_t pCopy, int ExecuteFlag)
{
  if (!pCopy) return;
  if (ExecuteFlag) fo_sqlCopyExecute(pCopy);
  if (pCopy->TableName) free(pCopy->TableName);
  if (pCopy->DataBuf) free(pCopy->DataBuf);
  free(pCopy);
}


/*!
 \brief Print the sqlCopy_struct.  
 This is used for debugging.

 \param pCopy Pointer to sqlCopy struct
 \param PrintBytes   Number of DataBuf bytes to print.
                         If zero, print the whole buffer.
 \return void
*/
void fo_sqlCopyPrint(psqlCopy_t pCopy, int PrintBytes)
{
  int idx;

  printf("========== fo_sqlCopyPrint  Start  ================\n");
  printf("pCopy: %lx, TableName: %s, BufSize: %d, DataIdx: %d\n",
    (long) pCopy, pCopy->TableName, pCopy->BufSize, pCopy->DataIdx);
  printf("       ColumnNames: %s\n", pCopy->ColumnNames);

  if (PrintBytes == 0) PrintBytes = pCopy->DataIdx;
  for (idx = 0; idx < PrintBytes; idx++) putchar(pCopy->DataBuf[idx]);

  printf("========== fo_sqlCopyPrint  End  ================");
}
