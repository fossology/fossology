/***************************************************************
 Copyright (C) 2010 Hewlett-Packard Development Company, L.P.

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
/*
 \file validate.c
 \brief Bucket agent validation, and usage functions
 */

#include "buckets.h"

/****************************************************
 arrayAinB

 Verify that all the values in array A are also in B

 @param int *arrayA   null terminated array of ints
 @param int *arrayB   null terminated array of ints

 @return true (!0) if all the elements in A are also in B
 else return false (0)
****************************************************/
FUNCTION int arrayAinB(int *arrayA, int *arrayB)
{
  int *arrayBHead;

  if (!arrayA || !arrayB) return 0;

  arrayBHead = arrayB;
  while(*arrayA)
  {
    arrayB = arrayBHead;
    while (*arrayB)
    {
      if (*arrayA == *arrayB) break;
      arrayB++;
    }
    if (!*arrayB) return 0;
    arrayA++;
  }
  return 1;
}

/****************************************************
 intAinB

 Verify that all the value A is a member of array B

 @param int  intA     int to match
 @param int *arrayB   null terminated array of ints

 @return true (!0) if intA is in B
 else return false (0)
****************************************************/
FUNCTION int intAinB(int intA, int *arrayB)
{

  if (!arrayB) return 0;

  while(*arrayB)
  {
    if (intA == *arrayB) return 1;
    arrayB++;
  }
  return 0;
}


/****************************************************
 validate_pk

 Verify a primary key exists.
 This works by running the sql (must be select) and
 returning the first column of the first row.
 The sql should make this the primary key.
 This could be used to simply return the first column 
 of the first result for any query.

 @param PGconn *pgConn  Database connection object
 @param char *sql   sql must select a single column, value in first row is returned.

 @return primary key, or 0 if it doesn't exist
 NOTE: This function writes error to stdout
****************************************************/
FUNCTION int validate_pk(PGconn *pgConn, char *sql)
{
  char *fcnName = "validate_pk";
  int pk = 0;
  PGresult *result;

  /* Skip file if it has already been processed for buckets. */
  result = PQexec(pgConn, sql);
  if (checkPQresult(result, sql, fcnName, __LINE__)) return 0;
  if (PQntuples(result) > 0) pk = atoi(PQgetvalue(result, 0, 0));
  PQclear(result);
  return pk;
}


FUNCTION void Usage(char *Name) 
{
  printf("Usage: %s [debug options]\n", Name);
  printf("  Debug options are:\n");
  printf("  -d   :: Debug. Results NOT written to database.\n");
  printf("  -i   :: Initialize the database, then exit.\n");
  printf("  -n   :: bucketpool name of bucketpool to use.\n");
  printf("  -p   :: bucketpool_pk of bucketpool to use.\n");
  printf("  -t   :: uploadtree_pk, root of tree to scan. Will turn on -d!\n");
  printf("  -u   :: upload_pk to scan.\n");
  printf("  -v   :: verbose (turns on debugging output)\n"); 
  printf("  NOTE: -n and -p are mutually exclusive.  If both are specified\n");
  printf("         -p is used.  One of these is required.\n");
  printf("  NOTE: -t and -u are mutually exclusive.  If both are specified\n");
  printf("         -u is used.  One of these is required.\n");
  printf("  NOTE: If none of -nptu are specified, the bucketpool_pk and upload_pk are read from stdin, one comma delimited pair per line.  For example, 'bppk=123, upk=987' where 123 is the bucketpool_pk and 987 is the upload_pk.  This is the normal execution from the scheduler.\n");
} /* Usage() */
