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
 \file buckets.c
 \brief Bucket agent

 The bucket agent uses user rules (see bucket table) to classify
 files into user categories
 */

#include "buckets.h"

/****************************************************
 walkTree

 This function does a recursive depth first walk through a file tree (uploadtree).
 
 @param PGconn pgConn   The database connection object.
 @param int  agent_pk   The agent_pk
 @param long uploadtree_pk

 @return 0 on OK, -1 on failure.
 Errors are written to stdout.
****************************************************/
FUNCTION int walkTree(PGconn *pgConn, pbucketdef_t * bucketDefList, int agent_pk, long uploadtree_pk)
{
  char *fcnName = "walkTree";
  char sqlbuf[128];
  PGresult *result;
  long  lft, rgt, pfile_pk, ufile_mode;
  long  child_uploadtree_pk, child_lft, child_rgt, child_pfile_pk, child_ufile_mode;
  int   numChildren, childIdx;
  int   rv = 0;
  long  *bucketList;  // null terminated list of bucket_pk's

  /* get uploadtree rec for uploadtree_pk */
  sprintf(sqlbuf, "select pfile_fk, lft, rgt, ufile_mode from uploadtree where uploadtree_pk=%ld", uploadtree_pk);
  result = PQexec(pgConn, sqlbuf);
  if (checkPQresult(result, sqlbuf, fcnName, __LINE__)) return -1;
  if (PQntuples(result) == 0) 
  {
    printf("FATAL: %s.%s missing uploadtree_pk %ld\n", __FILE__, fcnName, uploadtree_pk);
    return -1;
  }
  pfile_pk = atol(PQgetvalue(result, 0, 0));
  lft = atol(PQgetvalue(result, 0, 1));
  rgt = atol(PQgetvalue(result, 0, 2));
  ufile_mode = atol(PQgetvalue(result, 0, 3));
  PQclear(result);

  /* Skip file if it has already been processed for buckets. */
  if (processed(pgConn, agent_pk, pfile_pk)) return rv;

  /* If this is a leaf node process it */
  if (rgt == (lft+1))
  {
    if (((ufile_mode & 1<<28) == 0) && (pfile_pk > 0))
      return  processLeaf(pgConn, bucketDefList, pfile_pk, agent_pk);
    else
      return 0;  /* case of empty directory or artifact */
  }

  /* Since uploadtree_pk isn't a leaf, find its children and process (if child is leaf) 
     or recurse */
  sprintf(sqlbuf, "select uploadtree_pk,pfile_fk, lft, rgt, ufile_mode from uploadtree where parent=%ld", 
          uploadtree_pk);
  result = PQexec(pgConn, sqlbuf);
  if (checkPQresult(result, sqlbuf, fcnName, __LINE__)) return -1;
  numChildren = PQntuples(result);
  if (numChildren == 0) 
  {
    printf("FATAL: %s.%s: Inconsistent uploadtree. uploadtree_pk %ld should have children based on lft and rgt\n", 
           __FILE__, fcnName, uploadtree_pk);
    return -1;
  }

  /* process (find buckets for) each child */
  for (childIdx = 0; childIdx < numChildren; childIdx++)
  {
    child_uploadtree_pk = atol(PQgetvalue(result, childIdx, 0));
    child_pfile_pk = atol(PQgetvalue(result, childIdx, 1));
    if (processed(pgConn, agent_pk, child_pfile_pk)) continue;

    child_lft = atol(PQgetvalue(result, childIdx, 2));
    child_rgt = atol(PQgetvalue(result, childIdx, 3));
    child_ufile_mode = atol(PQgetvalue(result, childIdx, 4));

    /* if child is a leaf, just process rather than recurse 
    */
    if (child_rgt == (child_lft+1)) 
    {
      if (((child_ufile_mode & 1<<28) == 0) && (child_pfile_pk > 0))
        processLeaf(pgConn, bucketDefList, child_pfile_pk, agent_pk);
      continue;
    }

    /* not a leaf so recurse */
    rv = walkTree(pgConn, bucketDefList, agent_pk, child_uploadtree_pk);
  }

  /* done processing children, now processes (find buckets) for the container
     ignoring artifacts
   */
  if (((ufile_mode & 1<<28) == 0) && (pfile_pk > 0))
  {
    bucketList = getContainerBuckets(pgConn, bucketDefList, pfile_pk);
    rv = writeBuckets(pgConn, pfile_pk, bucketList, agent_pk);
  }

  return rv;
} /* walkTree */


/****************************************************
 processLeaf

 determine which bucket(s) a leaf node is in and write results

 @param PGconn *pgConn  postgresql connection
 @param long pfile_pk  

 @return 0=success, else error
****************************************************/
FUNCTION int processLeaf(PGconn *pgConn, pbucketdef_t *bdeflist, long pfile_pk, int agent_pk)
{
  int rv = 0;
  long *bucketList;

  bucketList = getLeafBuckets(pgConn, bdeflist, pfile_pk);
  rv = writeBuckets(pgConn, pfile_pk, bucketList, agent_pk);
  return rv;
}


/****************************************************
 getLeafBuckets

 given a pfile and bucketdef, determine what buckets the pfile is in

 @param PGconn *pgConn  postgresql connection
 @param long uploadtree_pk  

 @return array of bucket_pk's
****************************************************/
FUNCTION long *getLeafBuckets(PGconn *pgConn, pbucketdef_t *bdeflist, long pfile_pk)
{
  long *bucket_pk_list = 0;

printf("getting buckets for leaf %ld\n",pfile_pk);
  return bucket_pk_list;
}


/****************************************************
 getContainerBuckets

 given a container pfile and bucketdef, determine what buckets the pfile is in

 @param PGconn *pgConn  postgresql connection
 @param long uploadtree_pk  

 @return array of bucket_pk's
****************************************************/
FUNCTION long *getContainerBuckets(PGconn *pgConn, pbucketdef_t *bdeflist, long pfile_pk)
{
  long *bucket_pk_list = 0;

printf("getting container buckets for %ld\n",pfile_pk);
  return bucket_pk_list;
}


/****************************************************
 writeBuckets

 Write bucket results to either db or stdout

 @param PGconn *pgConn  postgresql connection
 @param long pfile_pk  

 @return 0=success, else error
****************************************************/
FUNCTION int writeBuckets(PGconn *pgConn, long pfile_pk, long *bucketList, int agent_pk)
{
  int rv = 0;

printf("write buckets %ld\n", pfile_pk);
  return rv;
}


/****************************************************
 processed

 Has this pfile already been bucket processed?
 This only works if the bucket has been recorded in table bucket_file.

 @param PGconn *pgConn  postgresql connection
 @param int *agent_pk   agent ID
 @param long pfile_pk  

 @return 1=yes, 0=no
****************************************************/
FUNCTION int processed(PGconn *pgConn, int agent_pk, long pfile_pk)
{
  char *fcnName = "processed";
  int numRecs;
  char sqlbuf[128];
  PGresult *result;

  /* Skip file if it has already been processed for buckets. */
  if (pfile_pk)
  {
    sprintf(sqlbuf, "select bf_pk from bucket_file where pfile_fk=%ld and agent_fk=%d limit 1", 
            pfile_pk, agent_pk);
    result = PQexec(pgConn, sqlbuf);
    if (checkPQresult(result, sqlbuf, fcnName, __LINE__)) return -1;
    numRecs = PQntuples(result);
    PQclear(result);
    if (numRecs > 0) return 1;
  }
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
     printf("Error: %s.%s(%d) - checkPQresult called with invalid parameter",
             __FILE__, FcnName, __LINE__);
     return 0;
   }

   /* If no error, return */
   if (PQresultStatus(result) == PGRES_TUPLES_OK) return 0;

   printf("ERROR: %s.%s:%d, %s\nOn: %s", 
          __FILE__, FcnName, __LINE__, PQresultErrorMessage(result), sql);
   PQclear(result);
   return (-1);
} /* checkPQresult */

FUNCTION void Usage(char *Name) 
{
  printf("Usage: %s [options] [uploadtree_pk]\n", Name);
  printf("  -i   :: Initialize the database, then exit.\n");
  printf("  -v   :: verbose (-vv = more verbose)\n"); 
  printf("  -d   :: Write results to database instead of stdout.\n");
  printf("  uploadtree_pk :: Find buckets in this tree\n");
} /* Usage() */


/****************************************************/
int main(int argc, char **argv) 
{
  char *agentDesc = "Bucket agent";
  int cmdopt;
  int verbose = 0;
  int writeDB = 0;
  long head_uploadtree_pk = 0;
  void *DB;   // DB object from agent
  PGconn *pgConn;
  PGresult *result;
  char sqlbuf[128];
  int agent_pk = 0;
  long pfile_pk = 0;
  pbucketdef_t *bucketDefList = 0;

  extern int AlarmSecs;
//  extern long HBItemsProcessed;

  /* Connect to the database */
  DB = DBopen();
  if (!DB) 
  {
    printf("FATAL: Bucket agent unable to connect to database, exiting...\n");
    exit(-1);
  }
  pgConn = DBgetconn(DB);

  /* command line options */
  while ((cmdopt = getopt(argc, argv, "ivd")) != -1) 
  {
    switch (cmdopt) 
    {
      case 'i': /* "Initialize" */
            DBclose(DB); /* DB was opened above, now close it and exit */
            exit(0);
      case 'v': /* verbose output for debugging  */
            verbose++;
            break;
      case 'd': /* write results to db instead of stdout  */
            writeDB = 1;
            break;
      default:
            Usage(argv[0]);
            DBclose(DB);
            exit(-1);
    }
  }
  head_uploadtree_pk = atol(argv[argc-1]);

  /* get agent pk 
   * Note, if GetAgentKey fails, this process will exit.
   */
  agent_pk = GetAgentKey(DB, basename(argv[0]), 0, SVN_REV, agentDesc);

  /* get the pfile for head_uploadtree_pk 
     we need this to check if its already been processed */
  sprintf(sqlbuf, "select pfile_fk from uploadtree where uploadtree_pk=%ld", head_uploadtree_pk);
  result = PQexec(pgConn, sqlbuf);
  if (checkPQresult(result, sqlbuf, agentDesc, __LINE__)) return -1;
  if (PQntuples(result) == 0) 
  {
    printf("FATAL: %s.%s missing root uploadtree_pk %ld\n", 
           __FILE__, agentDesc, head_uploadtree_pk);
    return -1;
  }
  pfile_pk = atol(PQgetvalue(result, 0, 0));
  PQclear(result);

  /* check if this has already been processed */
  if (processed(pgConn, agent_pk, pfile_pk)) return 0;

  if (writeDB)
  {
    signal(SIGALRM, ShowHeartbeat);
    alarm(AlarmSecs);
    printf("OK\n");
    fflush(stdout);
  }

  // Heartbeat(++HBItemsProcessed);
  // printf("OK\n"); /* tell scheduler ready for more data */
  // fflush(stdout);

  /* process the tree for buckets */
  walkTree(pgConn, bucketDefList, agent_pk, head_uploadtree_pk);

  return (0);
}
