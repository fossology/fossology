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
 \file init.c
 \brief Bucket agent init and validation functions

 */

#include "buckets.h"


/****************************************************
 validate_pk

 Verify a primary key exists and is active

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

/****************************************************
 getBucketPool

 Get a bucketpool_pk based on the bucketpool_name

 @param PGconn *pgConn  Database connection object
 @param char *bucketpool_name

 @return active bucketpool_pk or 0 if error
****************************************************/
FUNCTION int getBucketpool_pk(PGconn *pgConn, char *bucketpool_name)
{
  char *fcnName = "getBucketpool";
  int bucketpool_pk=0;
  char sqlbuf[128];
  PGresult *result;

  /* Skip file if it has already been processed for buckets. */
  sprintf(sqlbuf, "select bucketpool_pk from bucketpool where (bucketpool_name='%s') and (active='Y') order by version desc", 
          bucketpool_name);
  result = PQexec(pgConn, sqlbuf);
  if (checkPQresult(result, sqlbuf, fcnName, __LINE__)) return 0;
  if (PQntuples(result) > 0) bucketpool_pk = atoi(PQgetvalue(result, 0, 0));
  PQclear(result);
  return bucketpool_pk;
}


/****************************************************
 initBuckets

 Initialize the bucket definition list
 If an error occured, write the error to stdout

 @param PGconn *pgConn  Database connection object
 @param int bucketpool_pk

 @return the list of bucket definitions (in eval order)
 or 0 if error.
****************************************************/
FUNCTION pbucketdef_t *initBuckets(PGconn *pgConn, int bucketpool_pk)
{
  return 0;
}


/****************************************************
 checkPQresult

 check the result status of a postgres SELECT
 If an error occured, write the error to stdout

 @param PGresult *result
 @param char *sql the sql query
 @param char * FcnName the function name of the caller
 @param int LineNumb the line number of the caller

 @return 0 on OK, -1 on failure.
 On failure, result will be freed.

 NOTE: this function should be moved to a std library
****************************************************/
FUNCTION int checkPQresult(PGresult *result, char *sql, char *FcnName, int LineNumb)
{
   if (!result)
   {
     printf("Error: %s.%s(%d) - checkPQresult called with invalid parameter.\n",
             __FILE__, FcnName, __LINE__);
     return 0;
   }

   /* If no error, return */
   if (PQresultStatus(result) == PGRES_TUPLES_OK) return 0;

   printf("ERROR: %s.%s:%d, %s\nOn: %s\n", 
          __FILE__, FcnName, __LINE__, PQresultErrorMessage(result), sql);
   PQclear(result);
   return (-1);
} /* checkPQresult */

FUNCTION void Usage(char *Name) 
{
  printf("Usage: %s [options] [uploadtree_pk]\n", Name);
  printf("  -d   :: Write results to database instead of stdout.\n");
  printf("  -i   :: Initialize the database, then exit.\n");
  printf("  -n   :: bucketpool_pk of bucketpool to use.\n");
  printf("  -p   :: bucketpool name of bucketpool to use.\n");
  printf("  -t   :: uploadtree_pk, root of tree to scan.\n");
  printf("  -u   :: upload_pk, to scan entire upload.\n");
  printf("  -v   :: verbose (-vv = more verbose)\n"); 
  printf("  uploadtree_pk :: Find buckets in this tree\n");
  printf("  NOTE: -n and -p are mutually exclusive.  If both are specified\n");
  printf("         -n is used.  One of these is required.\n");
  printf("  NOTE: -t and -u are mutually exclusive.  If both are specified\n");
  printf("         -t is used.  One of these is required.\n");
} /* Usage() */
