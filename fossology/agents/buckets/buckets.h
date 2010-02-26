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
#ifndef _BUCKETS_H
#define _BUCKETS_H 1
#include <stdio.h>
#include <stdlib.h>
#include <unistd.h>
#include <string.h>
#include <strings.h>
#include <ctype.h>
#include <regex.h>
#include <libgen.h>
#include <getopt.h>
#include <errno.h>
#include <signal.h>
#include <sys/types.h>
#include <sys/stat.h>

#include "libfossdb.h"
#include "libfossagent.h"
#include "libfossrepo.h"
#include "liccache.h"
#define FUNCTION

/* Bucket definition */
struct bucketdef_struct 
{
  int      bucket_pk;
  char    *bucket_name;
  int      bucket_type;
  char    *regex;           /* regex string */
  regex_t  compRegex;       /* compiled regex if type=3 */
  char    *execFilename;    /* name of file to exec if type=4.  Files are in DATADIR */
  int     *match_only;      /* array of rf_pk's if type=2 */
  int    **match_every;     /* array of arrays of rf_pk's if type=1 */
  char     stopon;          /* Y to stop procecessing if this bucket matches */
  int      nomos_agent_pk;  /* nomos agent_pk whose results this bucket analsis is using */
  int      bucket_agent_pk; /* bucket agent_pk */
};
typedef struct bucketdef_struct bucketdef_t, *pbucketdef_t;

/* Bucket pool */
struct bucketpool_struct
{
  int  bucketpool_pk;
  int  bucketpool_name;
  int  bucketpool_version;
  int  nomos_agent_pk;
  int  bucket_agent_pk;
  pbucketdef_t pbucketdef;
};
typedef struct bucketpool_struct bucketpool_t, *pbucketpool_t;


/* buckets.c */
int walkTree(PGconn *pgConn, pbucketdef_t bucketDefArray, int agent_pk, 
             int uploadtree_pk, int writeDB, int skipProcessedCheck);
int processLeaf(PGconn *pgConn, pbucketdef_t bucketDefArray, int pfile_pk, 
                int uploadtree_pk, int agent_pk, int writeDB);
int *getLeafBuckets(PGconn *pgConn, pbucketdef_t bucketDefArray, int pfile_pk);
int *getContainerBuckets(PGconn *pgConn, pbucketdef_t bucketDefArray, int uploadtree_pk);
int writeBuckets(PGconn *pgConn, int pfile_pk, int uploadtree_pk, 
                 int *bucketList, int agent_pk, int writeDB);
int processed(PGconn *pgConn, int agent_pk, int pfile_pk, int uploadtree_pk);

/* validate.c */
int arrayAinB        (int *arrayA, int *arrayB);
int intAinB          (int intA, int *arrayB);
int validate_pk      (PGconn *pgConn, char *sql);
void Usage           (char *Name);

/* inits.c */
pbucketdef_t initBuckets   (PGconn *pgConn, int bucketpool_pk, cacheroot_t *pcroot);
int *getMatchOnly    (PGconn *pgConn, int bucketpool_pk, char *filename, cacheroot_t *pcroot);
int **getMatchEvery  (PGconn *pgConn, int bucketpool_pk, char *filename, cacheroot_t *pcroot);
int getBucketpool_pk (PGconn *pgConn, char * bucketpool_name);
int licDataAvailable (PGconn *pgConn, int uploadtree_pk);
int *getLicsInStr    (PGconn *pgConn, char *nameStr, cacheroot_t *pcroot);
int childParent      (PGconn *pgConn, int uploadtree_pk);



#endif /* _BUCKETS_H */
