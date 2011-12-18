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
 * \file  sqlCopyTest.c
 * \brief This is a test program for sqlCopy functions.
 **************************************************************/

#include "libfossology.h"

/* Return the string to use for the text column data
 */
char *GetTextCol(int NumTextBytes)
{
  char  *col_text;
  int    i;

  col_text = calloc(NumTextBytes+1, sizeof(char));
  if (!col_text)
  {
    ERROR_RETURN("Allocating test text data failed.")
    exit(-2);
  }
  for (i = 0; i < NumTextBytes; i++) col_text[i] = 'a';
  return(col_text);
}

/****************  main  *******************/
int main(int argc, char **argv)
{
  PGconn     *pgConn;
  PGresult   *result;
  psqlCopy_t  pCopy;
  char       *TestTable = "TestsqlCopy";
  char        col_vc[40] = "This is \n\r column vc[40] 1234567890";
  char       *col_text;
  char       *DataBuf;
  int         datasize;
  char        sql[2048];
  int         NumColumns = 3;
  int         CopyBufSize;
  int         RowsToTest;
  int         NumTextBytes;
  int         RowNum;
  int         rv;  /* return status value */
  clock_t     StartTime, EndTime;
  char       *DBConfFile = NULL;  /* use default Db.conf */
  char       *ErrorBuf;

  if (argc != 4)
  {
    printf("Usage: %s RowsToTest NumTextBytes CopyDataBufferSize\n", argv[0]);
    exit(-1);
  }

  /* first argument is the number of rows to test, 
   * the second is the number of bytes to use for col_text
   * third is the Copy data buffer size
   */
  RowsToTest = atoi(argv[1]);
  NumTextBytes = atoi(argv[2]);
  CopyBufSize = atoi(argv[3]);

  /* Populate test data */
  col_text = GetTextCol(NumTextBytes);
  datasize = NumTextBytes + 8 + 40 + 1;
  DataBuf = calloc(datasize, sizeof(char));
  if (!DataBuf)
  {
    ERROR_RETURN("Allocating test data buffer failed.")
    exit(-2);
  }

  pgConn = fo_dbconnect(DBConfFile, &ErrorBuf);

  /* Create a test table to populate */
  snprintf(sql, sizeof(sql), "create table %s (col_int integer, col_text text, col_vc varchar(40))", TestTable);
  result = PQexec(pgConn, sql);
  fo_checkPQcommand(pgConn, result, sql, __FILE__, __LINE__);
 
  /* Start timer */
  StartTime = clock();

  /* Create the pCopy */
  pCopy = fo_sqlCopyCreate(pgConn, TestTable, CopyBufSize, NumColumns,
                           "col_int", "col_text", "col_vc");
  if (!pCopy) exit(1);  /* CopyCreate prints errors to stdout */

  /* Add data */
  for(RowNum = 0; RowNum < RowsToTest; RowNum++)
  {
    snprintf(DataBuf, datasize, "%d\t%s\t%s\n", RowNum, col_text, col_vc);
    rv = fo_sqlCopyAdd(pCopy, DataBuf);
  }

  /* Destroy - flushes remaining data and frees */
  fo_sqlCopyDestroy(pCopy, 1);
 
  /* Print run time for the load (whole Create/Add/Destroy cycle). */
  EndTime = clock();
  printf("%.6f Seconds to load.\n", ((double) (EndTime - StartTime)) / CLOCKS_PER_SEC);

  /* Verify that the right number of records were loaded */
  snprintf(sql, sizeof(sql), "select count(*) from %s", TestTable);
  result = PQexec(pgConn, sql);
  fo_checkPQresult(pgConn, result, sql, __FILE__, __LINE__);
  printf("%d records inserted, %d expected\n", 
         atoi(PQgetvalue(result, 0, 0)),
         RowsToTest);
  PQclear(result);

  /* Remove the test table */
/*
  snprintf(sql, sizeof(sql), "drop table %s", TestTable);
  result = PQexec(pgConn, sql);
  fo_checkPQcommand(pgConn, result, sql, __FILE__, __LINE__);
*/

  PQfinish(pgConn);
  return(0);
}
