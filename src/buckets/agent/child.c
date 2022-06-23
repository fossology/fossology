/*
 SPDX-FileCopyrightText: © 2010-2011 Hewlett-Packard Development Company, L.P.

 SPDX-License-Identifier: GPL-2.0-only
*/

/**
 * \file child.c
 */
#include "buckets.h"

extern int debug;

/**
 * \brief Given a container uploadtree_pk and bucketdef, determine
 * if any child is in this bucket.
 *
 * \param pgConn      postgresql connection
 * \param bucketDef   Bucket
 * \param puploadtree Upload tree element to parse
 *
 * \return  1 if child is in this bucket,
 *          0 not in bucket,
 *         -1 error
 */
FUNCTION int childInBucket(PGconn *pgConn, pbucketdef_t bucketDef, puploadtree_t puploadtree)
{
  char *fcnName = "childInBucket";
  char  sql[1024];
  int   lft, rgt, upload_pk, rv;
  PGresult *result;

  lft = puploadtree->lft;
  rgt = puploadtree->rgt;
  upload_pk = puploadtree->upload_fk;

  /* Are any children in this bucket?
     First check bucket_container.
     If none found, then look in bucket_file.
  */
  snprintf(sql, sizeof(sql),
           "select uploadtree_pk from %s \
              inner join bucket_container \
                on uploadtree_fk=uploadtree_pk and bucket_fk=%d \
                   and agent_fk=%d and nomosagent_fk=%d \
            where upload_fk=%d and %s.lft BETWEEN %d and %d limit 1",
           bucketDef->uploadtree_tablename,
           bucketDef->bucket_pk, bucketDef->bucket_agent_pk,
           bucketDef->nomos_agent_pk, upload_pk,
           bucketDef->uploadtree_tablename,
           lft, rgt);
//  if (debug) printf("===%s:%d:\n%s\n===\n", __FILE__, __LINE__, sql);
  result = PQexec(pgConn, sql);
  if (fo_checkPQresult(pgConn, result, sql, fcnName, __LINE__)) return -1;
  rv = PQntuples(result);
  PQclear(result);
  if (rv) return 1;

  /* none found so look in bucket_file for any child in this bucket */
  snprintf(sql, sizeof(sql),
           "select uploadtree_pk from %s \
              inner join bucket_file \
                on %s.pfile_fk=bucket_file.pfile_fk and bucket_fk=%d \
                   and agent_fk=%d and nomosagent_fk=%d \
            where upload_fk=%d and %s.lft BETWEEN %d and %d limit 1",
           bucketDef->uploadtree_tablename,
           bucketDef->uploadtree_tablename,
           bucketDef->bucket_pk, bucketDef->bucket_agent_pk,
           bucketDef->nomos_agent_pk, upload_pk,
           bucketDef->uploadtree_tablename,
           lft, rgt);
//  if (debug) printf("===%s:%d:\n%s\n===\n", __FILE__, __LINE__, sql);
  result = PQexec(pgConn, sql);
  if (fo_checkPQresult(pgConn, result, sql, fcnName, __LINE__)) return -1;
  rv = PQntuples(result);
  PQclear(result);
  if (rv) return 1;

  return 0;
}
