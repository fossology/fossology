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
 writeBuckets

 Write bucket results to either db (bucket_file, bucket_container) or stdout.

 @param PGconn *pgConn  postgresql connection
 @param int pfile_pk  
 @param int uploadtree_pk  
 @param int *bucketList   null terminated array of bucket_pks 
                          that match this pfile
 @param int agent_pk  

 @return 0=success, -1 failure
****************************************************/
FUNCTION int writeBuckets(PGconn *pgConn, int pfile_pk, int uploadtree_pk, 
                          int *bucketList, int agent_pk, int writeDB, int nomosagent_pk)
{
  extern long HBItemsProcessed;
  char     *fcnName = "writeBuckets";
  char      sql[1024];
  PGresult *result;
  int rv = 0;
  if (debug) printf("debug: %s pfile: %d, uploadtree_pk: %d\n", fcnName, pfile_pk, uploadtree_pk);

  if (!writeDB) printf("NOTE: writeDB is FALSE, write buckets for pfile=%d, uploadtree_pk=%d: ", pfile_pk, uploadtree_pk);

  if (bucketList)
  {
    while(*bucketList)
    {
      if (writeDB)
      {
        Heartbeat(++HBItemsProcessed);
        if (pfile_pk)
        {
          snprintf(sql, sizeof(sql), 
                 "insert into bucket_file (bucket_fk, pfile_fk, agent_fk, nomosagent_fk) values(%d,%d,%d,%d)", *bucketList, pfile_pk, agent_pk, nomosagent_pk);
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
          if (debug) printf("%s sql: %s\n",fcnName, sql);
        }
        else
        {
          snprintf(sql, sizeof(sql), 
                 "insert into bucket_container (bucket_fk, uploadtree_fk, agent_fk, nomosagent_fk) \
                  values(%d,%d,%d,%d)", *bucketList, uploadtree_pk, agent_pk, nomosagent_pk);
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
          if (debug) printf("%s sql: %s\n",fcnName, sql);
        }
        PQclear(result);
      }
      else
        printf(" %d", *bucketList);
      bucketList++;
    }
  }

  if (!writeDB) printf("\n");
  return rv;
}
