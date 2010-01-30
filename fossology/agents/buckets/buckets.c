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

#ifdef SVN_REV
char BuildVersion[]="Build version: " SVN_REV ".\n";
#endif /* SVN_REV */


/****************************************************
 walkTree

 This function does a recursive depth first walk through a file tree (uploadtree).
 
 @param PGconn pgConn   The database connection object.
 @param int  agent_pk   The agent_pk
 @param int  uploadtree_pk

 @return 0 on OK, -1 on failure.
 Errors are written to stdout.
****************************************************/
FUNCTION int walkTree(PGconn *pgConn, pbucketdef_t bucketDefArray, int agent_pk, int  uploadtree_pk)
{
  char *fcnName = "walkTree";
  char sqlbuf[128];
  PGresult *result;
  int  lft, rgt, pfile_pk, ufile_mode;
  int  child_uploadtree_pk, child_lft, child_rgt, child_pfile_pk, child_ufile_mode;
  int   numChildren, childIdx;
  int   rv = 0;
  int  *bucketList;  // null terminated list of bucket_pk's

  /* get uploadtree rec for uploadtree_pk */
  sprintf(sqlbuf, "select pfile_fk, lft, rgt, ufile_mode from uploadtree where uploadtree_pk=%d", uploadtree_pk);
  result = PQexec(pgConn, sqlbuf);
  if (checkPQresult(result, sqlbuf, fcnName, __LINE__)) return -1;
  if (PQntuples(result) == 0) 
  {
    printf("FATAL: %s.%s missing uploadtree_pk %d\n", __FILE__, fcnName, uploadtree_pk);
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
      return  processLeaf(pgConn, bucketDefArray, pfile_pk, agent_pk);
    else
      return 0;  /* case of empty directory or artifact */
  }

  /* Since uploadtree_pk isn't a leaf, find its children and process (if child is leaf) 
     or recurse */
  sprintf(sqlbuf, "select uploadtree_pk,pfile_fk, lft, rgt, ufile_mode from uploadtree where parent=%d", 
          uploadtree_pk);
  result = PQexec(pgConn, sqlbuf);
  if (checkPQresult(result, sqlbuf, fcnName, __LINE__)) return -1;
  numChildren = PQntuples(result);
  if (numChildren == 0) 
  {
    printf("FATAL: %s.%s: Inconsistent uploadtree. uploadtree_pk %d should have children based on lft and rgt\n", 
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
        processLeaf(pgConn, bucketDefArray, child_pfile_pk, agent_pk);
      continue;
    }

    /* not a leaf so recurse */
    rv = walkTree(pgConn, bucketDefArray, agent_pk, child_uploadtree_pk);
  }

  /* done processing children, now processes (find buckets) for the container
     ignoring artifacts
   */
  if (((ufile_mode & 1<<28) == 0) && (pfile_pk > 0))
  {
    bucketList = getContainerBuckets(pgConn, bucketDefArray, pfile_pk);
    rv = writeBuckets(pgConn, pfile_pk, bucketList, agent_pk);
  }

  return rv;
} /* walkTree */


/****************************************************
 processLeaf

 determine which bucket(s) a leaf node is in and write results

 @param PGconn *pgConn  postgresql connection
 @param int pfile_pk  

 @return 0=success, else error
****************************************************/
FUNCTION int processLeaf(PGconn *pgConn, pbucketdef_t bucketDefArray, int pfile_pk, int agent_pk)
{
  int rv = 0;
  int *bucketList;

  bucketList = getLeafBuckets(pgConn, bucketDefArray, pfile_pk);
  rv = writeBuckets(pgConn, pfile_pk, bucketList, agent_pk);
  return rv;
}


/****************************************************
 getLeafBuckets

 given a pfile and bucketdef, determine what buckets the pfile is in

 @param PGconn *pgConn  postgresql connection
 @param pbucketdef_t bucketDefArray
 @param int uploadtree_pk  

 @return array of bucket_pk's, or 0 if error
****************************************************/
FUNCTION int *getLeafBuckets(PGconn *pgConn, pbucketdef_t in_bucketDefArray, int pfile_pk)
{
  char *fcnName = "getLeafBuckets";
  int  *bucket_pk_list = 0;
  char  sql[256];
  PGresult *result;
  int   numLics, licNumb;
  int   numBucketDefs = 0;
  int   rv;
  char *licName;
  pbucketdef_t bucketDefArray;

  /*** count how many elements are in in_bucketDefArray   ***/
  for (bucketDefArray = in_bucketDefArray; bucketDefArray->bucket_pk; bucketDefArray++)
    numBucketDefs++;

  /* allocate return array to hold max number of bucket_pk's */
  bucket_pk_list = calloc(numBucketDefs+1, sizeof(int));
  if (bucket_pk_list == 0)
  {
    printf("FATAL: out of memory allocating int array of %d elements\n", numBucketDefs+1);
    return 0;
  }
  
  /*** select all the licenses for pfile_pk and agent_pk ***/
  bucketDefArray = in_bucketDefArray;
  snprintf(sql, sizeof(sql), 
           "select rf_shortname from license_file, license_ref where agent_fk=%d and pfile_fk=%d and rf_fk=rf_pk",
           bucketDefArray->nomos_agent_pk, pfile_pk);
  result = PQexec(pgConn, sql);
  if (checkPQresult(result, sql, fcnName, __LINE__)) return 0;
  numLics = PQntuples(result);
printf("found %d licenses for pfile_pk: %d\n", numLics, pfile_pk);
  
  while (bucketDefArray->bucket_pk != 0)
  {
    switch (bucketDefArray->bucket_type)
    {
      case 1:  /* match every */
        break;
      case 2:  /* match only */
        break;
      case 3:  /* match this regex against each license names for this pfile */
        for (licNumb=0; licNumb < numLics; licNumb++)
        {
          licName = PQgetvalue(result, licNumb, 0);
printf("checking license: %s, against regex: %s\n", licName, bucketDefArray->regex);
          rv = regexec(&bucketDefArray->compRegex, licName, 0, 0, 0);
          if (rv == 0)
          {
            /* regex matched!  */
printf("pfile: %d, license: %s matched bucket: %s\n", pfile_pk, licName, bucketDefArray->bucket_name);
            *bucket_pk_list = bucketDefArray->bucket_pk;
            bucket_pk_list++;
            continue;
          }
        }
        break;
      case 4:  /* exec   */
        break;
      case 99:  /* match every */
        break;
      default:  /* unknown bucket type */
        break;
    }
    bucketDefArray++;
  }

  PQclear(result);
  return bucket_pk_list;
}


/****************************************************
 getContainerBuckets

 given a container pfile and bucketdef, determine what buckets the pfile is in

 @param PGconn *pgConn  postgresql connection
 @param int uploadtree_pk  

 @return array of bucket_pk's
****************************************************/
FUNCTION int *getContainerBuckets(PGconn *pgConn, pbucketdef_t bucketDefArray, int pfile_pk)
{
  int *bucket_pk_list = 0;

printf("getting container buckets for %d\n",pfile_pk);
  return bucket_pk_list;
}


/****************************************************
 writeBuckets

 Write bucket results to either db or stdout

 @param PGconn *pgConn  postgresql connection
 @param int pfile_pk  

 @return 0=success, else error
****************************************************/
FUNCTION int writeBuckets(PGconn *pgConn, int pfile_pk, int *bucketList, int agent_pk)
{
  int rv = 0;

printf("write buckets %d\n", pfile_pk);
  return rv;
}


/****************************************************
 processed

 Has this pfile already been bucket processed?
 This only works if the bucket has been recorded in table bucket_file.

 @param PGconn *pgConn  postgresql connection
 @param int *agent_pk   agent ID
 @param int pfile_pk  

 @return 1=yes, 0=no
****************************************************/
FUNCTION int processed(PGconn *pgConn, int agent_pk, int pfile_pk)
{
  char *fcnName = "processed";
  int numRecs;
  char sqlbuf[128];
  PGresult *result;

  /* Skip file if it has already been processed for buckets. */
  if (pfile_pk)
  {
    sprintf(sqlbuf, "select bf_pk from bucket_file where pfile_fk=%d and bucket_agent_fk=%d limit 1", 
            pfile_pk, agent_pk);
    result = PQexec(pgConn, sqlbuf);
    if (checkPQresult(result, sqlbuf, fcnName, __LINE__)) return -1;
    numRecs = PQntuples(result);
    PQclear(result);
    if (numRecs > 0) return 1;
  }
  return 0;
}


/****************************************************/
int main(int argc, char **argv) 
{
  char *agentDesc = "Bucket agent";
  int cmdopt;
  int verbose = 0;
  int writeDB = 0;
  int head_uploadtree_pk = 0;
  void *DB;   // DB object from agent
  PGconn *pgConn;
  PGresult *result;
  char sqlbuf[128];
  int agent_pk = 0;
  int nomos_agent_pk = 0;
  int bucketpool_pk = 0;
  int upload_pk = 0;
  char *bucketpool_name;
  int pfile_pk = 0;
  pbucketdef_t bucketDefArray = 0;

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
  while ((cmdopt = getopt(argc, argv, "din:p:t:u:v")) != -1) 
  {
    switch (cmdopt) 
    {
      case 'd': /* write results to db instead of stdout  */
            writeDB = 1;
            break;
      case 'i': /* "Initialize" */
            DBclose(DB); /* DB was opened above, now close it and exit */
            exit(0);
      case 'n': /* bucketpool_name  */
            bucketpool_name = optarg;
            /* find the highest rev active bucketpool_pk */
            if (!bucketpool_pk)
            {
              bucketpool_pk = getBucketpool_pk(pgConn, bucketpool_name);
              if (!bucketpool_pk)
                printf("%s is not an active bucketpool name.\n", bucketpool_name);
            }
            break;
      case 'p': /* bucketpool_pk */
            bucketpool_pk = atoi(optarg);
            /* validate bucketpool_pk */
            sprintf(sqlbuf, "select bucketpool_pk from bucketpool where bucketpool_pk=%d and active='Y'", bucketpool_pk);
            bucketpool_pk = validate_pk(pgConn, sqlbuf);
            if (!bucketpool_pk)
              printf("%d is not an active bucketpool_pk.\n", atoi(optarg));
            break;
      case 't': /* uploadtree_pk */
            head_uploadtree_pk = atoi(optarg);
            /* validate bucketpool_pk */
            sprintf(sqlbuf, "select uploadtree_pk from uploadtree where uploadtree_pk=%d", head_uploadtree_pk);
            head_uploadtree_pk = validate_pk(pgConn, sqlbuf);
            if (!head_uploadtree_pk)
              printf("%d is not an active uploadtree_pk.\n", atoi(optarg));
            break;
      case 'u': /* upload_pk */
            if (!head_uploadtree_pk)
            {
              upload_pk = atoi(optarg);
              /* validate upload_pk  and get uploadtree_pk  */
              sprintf(sqlbuf, "select upload_pk from upload where upload_pk=%d", upload_pk);
              upload_pk = validate_pk(pgConn, sqlbuf);
              if (!upload_pk)
                printf("%d is not an valid upload_pk.\n", atoi(optarg));
              else
              {
                sprintf(sqlbuf, "select uploadtree_pk from uploadtree where upload_fk=%d and parent is null", upload_pk);
                head_uploadtree_pk = validate_pk(pgConn, sqlbuf);
              }
            }
            break;
      case 'v': /* verbose output for debugging  */
            verbose++;
            break;
      default:
            Usage(argv[0]);
            DBclose(DB);
            exit(-1);
    }
  }

  /*** validate command line ***/
  if (!bucketpool_pk)
  {
    printf("You must specify an active bucketpool.\n");
    Usage(argv[0]);
    exit(-1);
  }
  if (!head_uploadtree_pk)
  {
    printf("You must specify a valid uploadtree_pk or upload_pk.\n");
    Usage(argv[0]);
    exit(-1);
  }

  /* get agent pk 
   * Note, if GetAgentKey fails, this process will exit.
   */
  agent_pk = GetAgentKey(DB, basename(argv[0]), 0, SVN_REV, agentDesc);

  /*** Get the pfile for head_uploadtree_pk so we can
     check if its already been processed ***/
  sprintf(sqlbuf, "select pfile_fk from uploadtree where uploadtree_pk=%d", head_uploadtree_pk);
  result = PQexec(pgConn, sqlbuf);
  if (checkPQresult(result, sqlbuf, agentDesc, __LINE__)) return -1;
  if (PQntuples(result) == 0) 
  {
    printf("FATAL: %s.%s missing root uploadtree_pk %d\n", 
           __FILE__, agentDesc, head_uploadtree_pk);
    return -1;
  }
  pfile_pk = atol(PQgetvalue(result, 0, 0));
  PQclear(result);

  /* Has it already been processed?  If so, we are done */
  if (processed(pgConn, agent_pk, pfile_pk)) return 0;

  /*** Make sure there is  license data available from the latest nomos agent ***/
  nomos_agent_pk = licDataAvailable(pgConn, head_uploadtree_pk);
  if (nomos_agent_pk == 0)
  {
    printf("WARNING: Bucket agent called on treeitem (%d), but the latest nomos agent hasn't created any license data for this tree.\n",
          head_uploadtree_pk);
    return -1;
  }

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

  /* Initialize the Bucket Definition List bucketDefArray  */
  bucketDefArray = initBuckets(pgConn, bucketpool_pk);
  if (bucketDefArray == 0)
  {
    printf("FATAL: %s.%d Bucket definition for pool %d could not be initialized.\n",
           __FILE__, __LINE__, bucketpool_pk);
    return -1;
  }
  bucketDefArray->nomos_agent_pk = nomos_agent_pk;
  bucketDefArray->bucket_agent_pk = agent_pk;

  /* process the tree for buckets */
  walkTree(pgConn, bucketDefArray, agent_pk, head_uploadtree_pk);

  return (0);
}
