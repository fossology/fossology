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
 \file validate.c
 \brief Bucket agent validation, and usage functions
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
