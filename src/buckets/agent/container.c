/***************************************************************
 Copyright (C) 2010-2011 Hewlett-Packard Development Company, L.P.

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


/**
 * \brief given a container uploadtree_pk and bucketdef, determine what buckets 
 * the container is in.
 *
 * A container is in all the buckets of its children (recursive).
 *
 * This function is also called for no-pfile artifacts to simplify the
 * recursion in walkTree().
 *
 * \param PGconn $pgConn  postgresql connection
 * \param pbucketdef_t $bucketDefArray  
 * \param int $uploadtree_pk
 *
 * \return zero terminated array of bucket_pk's for this uploadtree_pk (may contain 
 *        no elements).  This must be free'd by the caller.
 *
 * Note: It's tempting to just have walkTree() remember all the child buckets.
 *      but, due to pfile reuse, some of the tree might have been
 *     processed before walkTree() was called.
 */
FUNCTION int *getContainerBuckets(PGconn *pgConn, pbucketdef_t bucketDefArray,
                                  int uploadtree_pk)
{
  char *fcnName = "getContainerBuckets";
  char  sql[1024];
  int  *bucket_pk_list = 0;
  int   numBucketDefs = 0;
  int   numLics;
  int   upload_pk, lft, rgt;
  int   bucketNumb;
  PGresult *result;
  pbucketdef_t pbucketDefArray;

  if (debug) printf("%s: for uploadtree_pk %d\n",fcnName,uploadtree_pk);

  /*** Create the return array ***/
  /* count how many elements are in in_bucketDefArray.
     This won't be needed after implementing pbucketpool_t 
   */
  for (pbucketDefArray = bucketDefArray; pbucketDefArray->bucket_pk; pbucketDefArray++)
    numBucketDefs++;

  /* Create a null terminated int array, to hold the bucket_pk list  */
  bucket_pk_list = calloc(numBucketDefs+1, sizeof(int));
  if (bucket_pk_list == 0)
  {
    printf("FATAL: %s(%d) out of memory allocating int array of %d ints\n", 
           fcnName, __LINE__, numBucketDefs+1);
    return 0;
  }
  /*** END: Create the return array ***/

  /*** Find lft and rgt bounds for uploadtree_pk  ***/
  snprintf(sql, sizeof(sql),
    "SELECT lft,rgt,upload_fk FROM uploadtree WHERE uploadtree_pk ='%d'",
    uploadtree_pk);
  result = PQexec(pgConn, sql);
  if (fo_checkPQresult(pgConn, result, sql, fcnName, __LINE__)) return 0;
  numLics = PQntuples(result);
  if (numLics == 0) 
  {
    if (debug) printf("%s(%d): uploadtree_pk %d %s returned no recs.\n",__FILE__, __LINE__,uploadtree_pk, sql);
    PQclear(result);
    return bucket_pk_list;
  }
  lft = atoi(PQgetvalue(result, 0, 0));
  rgt = atoi(PQgetvalue(result, 0, 1));
  upload_pk = atoi(PQgetvalue(result, 0, 2));
  PQclear(result);
  /*** END: Find lft and rgt bounds for uploadtree_pk  ***/


  /*** Select all the unique buckets in this tree ***/
  snprintf(sql, sizeof(sql),
    "SELECT distinct(bucket_fk) as bucket_pk\
     from bucket_file, bucket_def,\
          (SELECT distinct(pfile_fk) as PF from uploadtree \
             where upload_fk=%d\
               and ((ufile_mode & (1<<28))=0)\
               and uploadtree.lft BETWEEN %d and %d) as SS\
     where PF=pfile_fk and agent_fk=%d\
       and bucket_file.nomosagent_fk=%d\
       and bucket_pk=bucket_fk\
       and bucketpool_fk=%d",
         upload_pk, lft, rgt, bucketDefArray->bucket_agent_pk,
         bucketDefArray->nomos_agent_pk, bucketDefArray->bucketpool_pk);
  if (debug) printf("%s(%d): Find buckets in container for uploadtree_pk %d\n%s\n",__FILE__, __LINE__,uploadtree_pk, sql);
  result = PQexec(pgConn, sql);
  if (fo_checkPQresult(pgConn, result, sql, fcnName, __LINE__)) return 0;
  numLics = PQntuples(result);
  if (numLics == 0) 
  {
    PQclear(result);
    return bucket_pk_list;
  }
  /*** END: Select all the unique buckets in this tree ***/

  /*** Populate the return array with the bucket_pk's  ***/
  for (bucketNumb=0; bucketNumb < numLics; bucketNumb++)
  {
    bucket_pk_list[bucketNumb] = atoi(PQgetvalue(result, bucketNumb, 0));
  }
  PQclear(result);

  if (debug)
  {
    printf("getContainerBuckets returning: ");
    for (bucketNumb=0; bucketNumb < numLics; bucketNumb++)
    {
      printf("%d  " ,bucket_pk_list[bucketNumb]);
    }
    printf("\n");
  }

  return bucket_pk_list;
}
