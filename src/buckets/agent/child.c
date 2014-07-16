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
 * \brief given a container uploadtree_pk and bucketdef, determine 
 * if any child is in this bucket.
 *
 * \param PGconn $pgConn postgresql connection
 * \param pbucketdef_t $bucketDef
 * \param puploadtree_t $puploadtree
 *
 * \return 1 if child is in this bucket \n
 *        0 not in bucket \n
 *       -1 error \n
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
            where upload_fk=%d and uploadtree.lft BETWEEN %d and %d limit 1",
           bucketDef->uploadtree_tablename,
           bucketDef->bucket_pk, bucketDef->bucket_agent_pk, 
           bucketDef->nomos_agent_pk, upload_pk, lft, rgt);
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
                on uploadtree.pfile_fk=bucket_file.pfile_fk and bucket_fk=%d \
                   and agent_fk=%d and nomosagent_fk=%d \
            where upload_fk=%d and uploadtree.lft BETWEEN %d and %d limit 1",
           bucketDef->uploadtree_tablename,
           bucketDef->bucket_pk, bucketDef->bucket_agent_pk, 
           bucketDef->nomos_agent_pk, upload_pk, lft, rgt);
//  if (debug) printf("===%s:%d:\n%s\n===\n", __FILE__, __LINE__, sql);
  result = PQexec(pgConn, sql);
  if (fo_checkPQresult(pgConn, result, sql, fcnName, __LINE__)) return -1;
  rv = PQntuples(result);
  PQclear(result);
  if (rv) return 1;

  return 0;
}
