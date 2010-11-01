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
#include "buckets.h"

extern int debug;


/****************************************************
 getContainerBuckets

 given a container uploadtree_pk and bucketdef, determine what buckets 
 the container is in (based on the buckets of its children).
 
 This function is also called for artifacts to simplify the
 recursion in walkTree().

 Unlike licenses, where we can report a license hierarchy at runtime
 from a single select, buckets need to be evaluated in order.  Because
 of this extra processing, this agent computes and stores
 buckets for containers (this function).

 @param PGconn      *pgConn  postgresql connection
 @param pbucketdef_t bucketDefArray  
 @param int          uploadtree_pk

 @return array of bucket_pk's for this uploadtree_pk

 Note: You can't just pass in a list of child buckets from walkTree()
       since, due to pfile reuse, walkTree() may not have processed
       parts of the tree.
****************************************************/
FUNCTION int *getContainerBuckets(PGconn *pgConn, pbucketdef_t in_bucketDefArray,
                                  int uploadtree_pk)
{
  char *fcnName = "getContainerBuckets";
  char  sql[1024];
  int  *bucket_pk_list = 0;
  int  *bucket_pk_list_start = 0;
  int   numBucketDefs = 0;
  int  *children_bucket_pk_list = 0;
  int   childParent_pk;  /* uploadtree_pk */
  int   numLics;
  int   bucketNumb;
  int   match;
  PGresult *result;
  pbucketdef_t bucketDefArray;

  if (debug) printf("%s: for uploadtree_pk %d\n",fcnName,uploadtree_pk);

  /* Find the parent of this uploadtree_pk's children.  */
//  childParent_pk = childParent(pgConn, uploadtree_pk);
//printf("childParent_pk %d\n", childParent_pk);
  childParent_pk = uploadtree_pk;

  /* Get all the bucket_fk's from the immediate children  
     That is, what buckets are the children in */
  snprintf(sql, sizeof(sql), 
           "select distinct(bucket_fk) from uploadtree,bucket_container, bucket_def \
             where parent='%d' and bucket_container.uploadtree_fk=uploadtree_pk \
                   and bucket_fk=bucket_pk and agent_fk='%d' and bucketpool_fk='%d'\
            union\
            select distinct(bucket_fk) from uploadtree, bucket_file, bucket_def \
             where parent='%d' and bucket_file.pfile_fk=uploadtree.pfile_fk \
                   and bucket_fk=bucket_pk and agent_fk='%d' and bucketpool_fk='%d'",
           childParent_pk, in_bucketDefArray->bucket_agent_pk, 
           in_bucketDefArray->bucketpool_pk,
           childParent_pk, in_bucketDefArray->bucket_agent_pk, 
           in_bucketDefArray->bucketpool_pk);
  result = PQexec(pgConn, sql);
  if (checkPQresult(result, sql, fcnName, __LINE__)) return 0;
  numLics = PQntuples(result);

  /*** save the bucket list in a null terminated easy access int array ***/
  children_bucket_pk_list = calloc(numLics+1, sizeof(int));
  if (children_bucket_pk_list == 0)
  {
    printf("FATAL: out of memory allocating int array of %d ints\n", numLics+1);
    return 0;
  }
  for (bucketNumb=0; bucketNumb < numLics; bucketNumb++)
  {
    children_bucket_pk_list[bucketNumb] = atoi(PQgetvalue(result, bucketNumb, 0));
  }
  PQclear(result);

  /*** count how many elements are in in_bucketDefArray   ***/
  /* move this out when implement pbucketpool_t */
  for (bucketDefArray = in_bucketDefArray; bucketDefArray->bucket_pk; bucketDefArray++)
    numBucketDefs++;

  /* allocate return array to hold max number of bucket_pk's + 1 for null terminator */
  bucket_pk_list_start = calloc(numBucketDefs+1, sizeof(int));
  if (bucket_pk_list_start == 0)
  {
    printf("FATAL: out of memory allocating int array of %d ints\n", numBucketDefs+1);
    return 0;
  }
  bucket_pk_list = bucket_pk_list_start;

  if (debug) printf("debug found %d buckets under parent %d, childParent %d\n",numLics, uploadtree_pk, childParent_pk);

  /* loop through each bucket definition */
  bucketDefArray = in_bucketDefArray;
  match = 0;
  while (bucketDefArray->bucket_pk != 0)
  {
    /* if children_bucket_pk_list contains this bucket_pk 
       then this is a match */
    if (intAinB(bucketDefArray->bucket_pk, children_bucket_pk_list))
    {
      if (debug) printf(">>>   found bucket_pk: %d\n", bucketDefArray->bucket_pk);
      *bucket_pk_list = bucketDefArray->bucket_pk;
      bucket_pk_list++;
      match++;
      break;
    }

    if (match && bucketDefArray->stopon == 'Y') break;
    bucketDefArray++;
  }
  free(children_bucket_pk_list);
  return bucket_pk_list_start;
}
