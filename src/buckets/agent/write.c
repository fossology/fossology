/***************************************************************
 Copyright (C) 2010-2012 Hewlett-Packard Development Company, L.P.

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
 * \brief Write bucket results to either db (bucket_file, bucket_container) or stdout.
 *
 * \param PGconn $pgConn  postgresql connection
 * \param int pfile_pk  
 * \param int uploadtree_pk  
 * \param int bucketList   null terminated array of bucket_pks 
 *                         that match this pfile
 * \param int agent_pk  
 * \param int bucketpool_pk - bucketpool id
 *
 * \return 0=success, -1 failure
 */
FUNCTION int writeBuckets(PGconn *pgConn, int pfile_pk, int uploadtree_pk, 
                          int *bucketList, int agent_pk, int nomosagent_pk, int bucketpool_pk)
{
  char     *fcnName = "writeBuckets";
  char      sql[1024];
  PGresult *result = 0;
  int rv = 0;
  //if (debug) printf("debug: %s:%s() pfile: %d, uploadtree_pk: %d\n", __FILE__, fcnName, pfile_pk, uploadtree_pk);
  if (debug) printf("debug: %s:%s() pfile: %d, uploadtree_pk: %d\n", __FILE__, fcnName, pfile_pk, uploadtree_pk);


  if (bucketList)
  {
    while(*bucketList)
    {
      fo_scheduler_heart(1);
      if (pfile_pk)
      {
        if (processed(pgConn, agent_pk, pfile_pk, uploadtree_pk, bucketpool_pk, *bucketList)) 
        {
          snprintf(sql, sizeof(sql), 
              "UPDATE bucket_file set bucket_fk = %d from bucket_def where pfile_fk = %d and  \
              bucket_fk= bucket_pk and bucket_def.bucketpool_fk = %d;",
              *bucketList, pfile_pk, bucketpool_pk);
        } 
        else
        {
          snprintf(sql, sizeof(sql), 
              "insert into bucket_file (bucket_fk, pfile_fk, agent_fk, nomosagent_fk) values(%d,%d,%d,%d)",
              *bucketList, pfile_pk, agent_pk, nomosagent_pk);
        }
        if (debug) 
          printf("%s(%d): %s\n", __FILE__, __LINE__, sql);
        result = PQexec(pgConn, sql);
        // ignore duplicate constraint failure (23505), report others
        if ((result==0) || ((PQresultStatus(result) != PGRES_COMMAND_OK) &&
            (strncmp("23505", PQresultErrorField(result, PG_DIAG_SQLSTATE),5))))
        {  
          printf("ERROR: %s.%s().%d:  Failed to add bucket to bucket_file.\n",
                  __FILE__,fcnName, __LINE__);
          fo_checkPQresult(pgConn, result, sql, __FILE__, __LINE__);
          PQclear(result);
          rv = -1;
          break;
        }
      }
      else
      {
        snprintf(sql, sizeof(sql), 
               "insert into bucket_container (bucket_fk, uploadtree_fk, agent_fk, nomosagent_fk) \
                values(%d,%d,%d,%d)", *bucketList, uploadtree_pk, agent_pk, nomosagent_pk);
        if (debug)
          printf("%s(%d): %s\n", __FILE__, __LINE__, sql);

        result = PQexec(pgConn, sql);
        if ((PQresultStatus(result) != PGRES_COMMAND_OK) &&
            (strncmp("23505", PQresultErrorField(result, PG_DIAG_SQLSTATE),5)))
        {
          // ignore duplicate constraint failure (23505)
          printf("ERROR: %s.%s().%d:  Failed to add bucket to bucket_file. %s\n: %s\n",
                  __FILE__,fcnName, __LINE__, 
                  PQresultErrorMessage(result), sql);
          PQclear(result);
          rv = -1;
          break;
        }
      }
      if (result) PQclear(result);
      bucketList++;
    }
  }

  if (debug) printf("%s:%s() returning rv=%d\n", __FILE__, fcnName, rv);
  return rv;
}
