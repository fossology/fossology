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
 \file inits.c
 \brief Bucket agent initialization and lookup functions

 */

#include "buckets.h"


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

 @return an array of bucket definitions (in eval order)
 or 0 if error.
****************************************************/
FUNCTION pbucketdef_t initBuckets(PGconn *pgConn, int bucketpool_pk)
{
  char *fcnName = "initBuckets";
  char sqlbuf[256];
  PGresult *result;
  pbucketdef_t bucketDefList = 0;
  int  numRows, rowNum;
  int  rv, numErrors=0;
  int *prv, **pprv;

  /* reasonable input validation  */
  if ((!pgConn) || (!bucketpool_pk)) 
  {
    printf("ERROR: %s.%s.%d Invalid input pgConn: %d, bucketpool_pk: %d.\n",
            __FILE__, fcnName, __LINE__, (int)pgConn, bucketpool_pk);
    return 0;
  }

  /* get bucket defs from db */
  sprintf(sqlbuf, "select bucket_pk, bucket_type, bucket_regex, bucket_filename, stopon, bucket_name from bucket where bucketpool_fk=%d order by bucket_evalorder asc", bucketpool_pk);
  result = PQexec(pgConn, sqlbuf);
  if (checkPQresult(result, sqlbuf, fcnName, __LINE__)) return 0;
  numRows = PQntuples(result);
  if (numRows == 0) /* no bucket recs for pool?  return error */
  {
    printf("ERROR: %s.%s.%d No bucket defs for pool %d.\n",
            __FILE__, fcnName, __LINE__, bucketpool_pk);
    PQclear(result);
    return 0;
  }

  bucketDefList = calloc(numRows+1, sizeof(bucketdef_t));
  if (bucketDefList == 0)
  {
    printf("ERROR: %s.%s.%d No memory to allocate %d bucket defs.\n",
            __FILE__, fcnName, __LINE__, numRows);
    return 0;
  }

  /* put each db bucket def into bucketDefList in eval order */
  for (rowNum=0; rowNum<numRows; rowNum++)
  {
    bucketDefList[rowNum].bucket_pk = atoi(PQgetvalue(result, rowNum, 0));
    bucketDefList[rowNum].bucket_type = atoi(PQgetvalue(result, rowNum, 1));

    rv = regcomp(&bucketDefList[rowNum].compRegex, PQgetvalue(result, rowNum, 2), 
                 REG_NOSUB | REG_ICASE);
    if (rv != 0)
    {
      printf("ERROR: %s.%s.%d Invalid regular expression for bucketpool_pk: %d, bucket: %s\n",
             __FILE__, fcnName, __LINE__, bucketpool_pk, PQgetvalue(result, rowNum, 5));
      numErrors++;
    }
    bucketDefList[rowNum].regex = strdup(PQgetvalue(result, rowNum, 2));

    bucketDefList[rowNum].execFilename = strdup(PQgetvalue(result, rowNum, 3));

    if (bucketDefList[rowNum].bucket_type == 1)
      pprv = getMatchEvery(pgConn, bucketpool_pk, bucketDefList[rowNum].execFilename);

    if (bucketDefList[rowNum].bucket_type == 2)
      prv = getMatchOnly(pgConn, bucketpool_pk, bucketDefList[rowNum].execFilename);

    bucketDefList[rowNum].stopon = *PQgetvalue(result, rowNum, 4);
    bucketDefList[rowNum].bucket_name = strdup(PQgetvalue(result, rowNum, 5));
  }
  PQclear(result);
  if (numErrors) return 0;

#ifdef DEBUG
  for (rowNum=0; rowNum<numRows; rowNum++)
  {
    printf("\nbucket_pk[%d] = %d\n", rowNum, bucketDefList[rowNum].bucket_pk);
    printf("bucket_name[%d] = %s\n", rowNum, bucketDefList[rowNum].bucket_name);
    printf("bucket_type[%d] = %d\n", rowNum, bucketDefList[rowNum].bucket_type);
    printf("execFilename[%d] = %s\n", rowNum, bucketDefList[rowNum].execFilename);
    printf("stopon[%d] = %c\n", rowNum, bucketDefList[rowNum].stopon);
    printf("nomos_agent_pk[%d] = %d\n", rowNum, bucketDefList[rowNum].nomos_agent_pk);
    printf("bucket_agent_pk[%d] = %d\n", rowNum, bucketDefList[rowNum].bucket_agent_pk);
    printf("regex[%d] = %s\n", rowNum, bucketDefList[rowNum].regex);
  }
#endif

  return bucketDefList;
}


/****************************************************
 getMatchOnly

 Read the match only file (bucket type 2)

 @param PGconn *pgConn  Database connection object
 @param int bucketpool_pk
 @param char *filename

 @return an array of rf_pk's that match the licenses
 in filename.
 or 0 if error.
****************************************************/
FUNCTION int *getMatchOnly(PGconn *pgConn, int bucketpool_pk, char *filename )
{
  char filepath[256];

  snprintf(filepath, sizeof(filepath), "%s/bucketpools/%d/%s", 
           DATADIR, bucketpool_pk, filename);

printf("MATCH_ONLY: filepath: %s\n", filepath);
printf("wait for Glen's response about parsing this file\n");
return 0;
}


/****************************************************
 getMatchEvery

 Read the match every file (bucket type 1)

 @param PGconn *pgConn  Database connection object
 @param int bucketpool_pk
 @param char *filename

 @return an array of rf_pk's that match the licenses
 in filename.
 or 0 if error.
****************************************************/
FUNCTION int **getMatchEvery(PGconn *pgConn, int bucketpool_pk, char *filename )
{
  char filepath[256];

  snprintf(filepath, sizeof(filepath), "%s/bucketpools/%d/%s", 
           DATADIR, bucketpool_pk, filename);

printf("MATCH_EVERY: filepath: %s\n", filepath);
printf("wait for Glen's response about parsing this file\n");
return 0;
}


/****************************************************
 licDataAvailable

 Get the latest nomos agent_pk, and verify that there is
 data from it for this uploadtree.

 @param PGconn *pgConn  Database connection object
 @param int    *uploadtree_pk  

 @return nomos_agent_pk, or 0 if there is no license data from
         the latest version of the nomos agent.
 NOTE: This function writes error to stdout
****************************************************/
FUNCTION int licDataAvailable(PGconn *pgConn, int uploadtree_pk)
{
  char *fcnName = "licDataAvailable";
  char sql[256];
  PGresult *result;
  int  nomos_agent_pk = 0;

  /*** Find the latest enabled nomos agent_pk ***/
  snprintf(sql, sizeof(sql),
           "select agent_pk from agent where agent_name='nomos' order by agent_ts desc limit 1");
  result = PQexec(pgConn, sql);
  if (checkPQresult(result, sql, fcnName, __LINE__)) return 0;
  if (PQntuples(result) == 0)
  {
    /* agent isn't in agent table */
    printf("FATAL: %s.%s.%d agent nomos doesn't exist in agent table.\n",
           __FILE__, fcnName, __LINE__);
    PQclear(result);
    return(0);
  }
  nomos_agent_pk = atoi(PQgetvalue(result,0,0));
  PQclear(result);

  /*** Make sure there is available license data from this nomos agent ***/
  snprintf(sql, sizeof(sql),
           "select fl_pk from license_file where agent_fk=%d limit 1",
           nomos_agent_pk);
  if (checkPQresult(result, sql, fcnName, __LINE__)) return 0;
  if (PQntuples(result) == 0)
  {
    PQclear(result);
    return 0;
  }
  return nomos_agent_pk;
}
