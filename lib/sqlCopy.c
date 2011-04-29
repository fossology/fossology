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

/**************************************************************
 * fo_sqlCopy functions
 * \file sqlCopy.c
 * \brief sqlCopy buffers sql inserts and performs batch copy's
 *        to the database.  Why do this?  Because this method is 
 *        roughtly 15x faster than individual sql inserts.
 *
 * Note that all data to be inserted is stored in memory (pCopy->DataRow), 
 * not an external file.  So the caller should give some consideration 
 * to the number of records buffered (UpdateInterval).
 *
 * How to use:
 * 1. Get an sqlCopy_struct pointer from fo_sqlCopyCreate().
 * 2. Add records you want inserted into a single table in the
 *    database with fo_sqlCopyAdd().  This will buffer the recs
 *    until the UpdateInterval is reached.  At that point the
 *    records will be added to the database table in a single copy stmt.
 * 3. When the program is done, call fo_sqlCopyDestroy().  This will flush
 *    the remaining records out of the buffer and free memory.
 *
 * Two other functions may also come in handy:
 * 1. fo_sqlCopyExecute() will execute the copy immediately.
 * 2. fo_sqlCopyPrint() will print the sqlCopy structure. 
 *    It is good for debugging.
 **************************************************************/

#include "sqlCopy.h"

/****************************************************
 fo_sqlCopyCreate()

 Constructor for sqlCopy_struct.  

 @param PGconn *PGconn  Database connection
 @param char *TableName
 @param int   UpdateInterval  Number of datarows buffered before writing
                   to the database.
 @param int   NumColumns  number of column names passed in.
 @param char *Fmt  printf type format string for the column data
                   conversion to a string.  All format specifiers should
                   be separated by tabs. 
                   For example, if your databse columns are a string type,
                   integer, and another string type, $Fmt would be "%s\t%d\t%s"
 @param ...   char *ColumnNames

 @return sqlCopy_struct
         On failure, ERROR to stdout, return 0
****************************************************/
psqlCopy_t fo_sqlCopyCreate(PGconn *PGconn, char *TableName, int UpdateInterval, int NumColumns, ...)
{
  psqlCopy_t pCopy;
  va_list    ColumnNameArg;
  int        ColIdx;
  int        ColStrLength;
  char *     ColStr;

  va_start(ColumnNameArg, NumColumns);

  /* Allocate the structure */
  pCopy = malloc(sizeof(sqlCopy_t));
  if (!pCopy) ERROR_RETURN("sqlCopy malloc")

  /* Allocate storage for all the data string pointers this one time */
  pCopy->DataRows = calloc(UpdateInterval, sizeof(char *));
  if (!pCopy->DataRows) 
  {
    free(pCopy);
    ERROR_RETURN("DataRows")
  }

  /* Allocate an array to keep track of the storage allocated to each DataRows[i] */
  pCopy->RowLengths = calloc(UpdateInterval, sizeof(int));
  if (!pCopy->RowLengths) 
  {
    fo_sqlCopyDestroy(pCopy, 0);
    ERROR_RETURN("RowLengths")
  }

  /* Save the DB connection */
  pCopy->PGconn = PGconn;

  /* Save TableName */
  strncpy(pCopy->TableName, TableName, sizeof(pCopy->TableName));

  /* Save UpdateInterval */
  pCopy->UpdateInterval = UpdateInterval;

  /* No data used yet */
  pCopy->LastRow = -1;

  /* Build the column name string pCopy->ColumnNames  */
  ColStrLength = 0;
  for (ColIdx = 0; ColIdx < NumColumns; ColIdx++)
  {
    ColStr = va_arg(ColumnNameArg, char *);
    ColStrLength += strlen(ColStr) + 1;  /* extra 1 for the comma */
    if (ColStrLength < sizeof(pCopy->ColumnNames))
    {
      if (ColIdx != 0) strncat(pCopy->ColumnNames, ",", sizeof(pCopy->ColumnNames));
      strncat(pCopy->ColumnNames, va_arg(ColumnNameArg, char *), sizeof(pCopy->ColumnNames));
    }
    else
    {
       fo_sqlCopyDestroy(pCopy, 0);
       ERROR_RETURN("pCopy->ColumnNames size too small")
    }
  }

  va_end(ColumnNameArg);
  return(pCopy);
}


/****************************************************
 fo_sqlCopyAdd()

 Add a data row to an sqlCopy 
 Use '\N' to pass in a null

 @param psqlCopy_t Pointer to sqlCopy struct
 @param char *DataRow 

 The DataRow pointer is not saved.  This function allocates
 new storage for it.
 DataRow is tab delimited.  Any strings that include
 a tab need to replace it with '\t'
 For example, to insert a row with two character fields and an
 integer field, DataRow might look like:
   Mytab\tstring  <tab> string number 2 <tab> 36
 This could be created by:
   snprintf(buf, sizeof(buf), "%s\t%s\t%d", str1, str2, val);
 @return 0 if failure
****************************************************/
int fo_sqlCopyAdd(psqlCopy_t pCopy, char *DataRow)
{
  int NewRowLength;
  int LastRow;

  pCopy->LastRow++;
  LastRow = pCopy->LastRow;

  NewRowLength = strlen(DataRow);

  /* if DataRows[LastRow] is too short to hold the new DataRow,
   * free in and reallocate
   */
  if (NewRowLength > pCopy->RowLengths[LastRow])
  {
    /* buffer too small, free it */
    if (pCopy->DataRows[LastRow]) free(pCopy->DataRows[LastRow]);

    /* Reallocate the DataRow
     * RowLengths do not include the null terminator */
    pCopy->DataRows[LastRow] = calloc(NewRowLength+1, sizeof(char)); 
    pCopy->RowLengths[LastRow] = NewRowLength;
    if (!pCopy->DataRows[LastRow]) ERROR_RETURN("Malloc failed for DataRows")
  }

  /* copy in DataRow */
  strcpy(pCopy->DataRows[LastRow], DataRow);

  /* Update the DB if we have enough (UpdateInterval) rows */
  if (LastRow >= pCopy->UpdateInterval) fo_sqlCopyExecute(pCopy);
  return(1);
}


/****************************************************
 fo_sqlCopyExecute()

 Execute the copy (ie insert the buffered records into the
 database.  This may be called anytime, not just when UpdateInterval 
 rows have been saved.
 Then reset pCopy (effectively empty it).
 Note that DataRow memory is reused, instead of being
 freed in this function.

 @param psqlCopy_t Pointer to sqlCopy struct

 @return 0 on Failure (with msg), 1 on success.
****************************************************/
int fo_sqlCopyExecute(psqlCopy_t pCopy)
{
  int   RowIdx;
  char *Row;
  char  copystmt[2048];
  PGresult *result;

  /* check pCopy */
  if (!pCopy) ERROR_RETURN("Null pCopy");
  if (!pCopy->DataRows) ERROR_RETURN("Empty DataRows");

  /* Start the Copy command */
    sprintf(copystmt, "COPY %s(%s) from stdin", 
            pCopy->TableName,
            pCopy->ColumnNames);
    result = PQexec(pCopy->PGconn, copystmt);
    if (PGRES_COPY_IN != PQresultStatus(result)) 
      ERROR_RETURN(PQresultErrorMessage(result))

  /* Write each data row */
  for (RowIdx = 0; RowIdx < pCopy->UpdateInterval && (pCopy->DataRows[RowIdx]); RowIdx++) 
  {
    Row = pCopy->DataRows[RowIdx];
    if (PQputCopyData(pCopy->PGconn, Row, strlen(Row)) != 1)
      ERROR_RETURN(PQresultErrorMessage(result))
  }

  /* End copy  */
  if (PQputCopyEnd(pCopy->PGconn, NULL) != 1) ERROR_RETURN("sqlCopyEnd Failure")
  if (PQerrorMessage(pCopy->PGconn)) ERROR_RETURN(PQerrorMessage(pCopy->PGconn))

  /* empty but do not deallocate DataRows strings */
  for (RowIdx = 0; RowIdx < pCopy->UpdateInterval && (pCopy->DataRows[RowIdx]); RowIdx++) 
  {
    pCopy->DataRows[RowIdx] = 0;
  }

  /* Reset index to last data record */
  pCopy->LastRow = -1;

  return(1);
}


/****************************************************
 fo_sqlCopyDestroy()

 Destructor for sqlCopy_struct.  This will execute CopyExecute
 if the ExecuteFlag is true and there are records that need
 to be written.

 @param psqlCopy_t Pointer to sqlCopy struct
 @param int   ExecuteFlag  0 if DataRows should not be written,
                           1 if DataRows should be written

 @return Always returns (psqlCopy_t)0
****************************************************/
psqlCopy_t fo_sqlCopyDestroy(psqlCopy_t pCopy, int ExecuteFlag)
{
  int RowIdx;

  if (!pCopy) return(0);
  if (pCopy->DataRows)
  {
    for (RowIdx = 0; RowIdx < pCopy->UpdateInterval; RowIdx++) 
      if(pCopy->DataRows[RowIdx]) free(pCopy->DataRows[RowIdx]);
    free(pCopy->DataRows);
  }
  if (pCopy->RowLengths) free(pCopy->RowLengths);
  free(pCopy);
  return((psqlCopy_t)0);
}


/****************************************************
 fo_sqlCopyPrint()

 Print the sqlCopy_struct.  
 This is used for debugging.

 @param psqlCopy_t pCopy Pointer to sqlCopy struct
 @param int PrintRows    Number of DataRows to print

 @return void
****************************************************/
void fo_sqlCopyPrint(psqlCopy_t pCopy, int PrintRows)
{
  int   Rows2Print;
  int   RowIdx;

  printf("pCopy: %lx, TableName: %s, UpdateInterval: %d\n", 
        (long)pCopy, pCopy->TableName, pCopy->UpdateInterval);

  if (pCopy->UpdateInterval <  PrintRows)
    Rows2Print = pCopy->UpdateInterval;
  else
    Rows2Print = PrintRows; 

  for (RowIdx = 0; RowIdx < Rows2Print && (pCopy->DataRows[RowIdx]); RowIdx++) 
    printf("%s\n", pCopy->DataRows[RowIdx]);
}
