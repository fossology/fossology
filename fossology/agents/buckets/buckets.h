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

#define FUNCTION

/* Bucket definition */
struct bucketdef_struct 
{
  int      bucket_type;
  regex_t  compRegex;     /* compiled regex if type=3 */
  char    *execFilename;  /* name of file to exec if type=4.  Files are in DATADIR */
  int     *match_only;    /* array of rf_pk's if type=2 */
  int    **match_every;   /* array of arrays of rf_pk's if type=1 */
};
typedef struct bucketdef_struct *pbucketdef_t;

/* in nomos.c */
int walkTree(PGconn *pgConn, pbucketdef_t *bdeflist, int agent_pk, long uploadtree_pk);
int processLeaf(PGconn *pgConn, pbucketdef_t *bdeflist, long pfile_pk, int agent_pk);
long *getLeafBuckets(PGconn *pgConn, pbucketdef_t *bdeflist, long pfile_pk);
long *getContainerBuckets(PGconn *pgConn, pbucketdef_t *bdeflist, long pfile_pk);
int writeBuckets(PGconn *pgConn, long pfile_pk, long *bucketList, int agent_pk);
int processed(PGconn *pgConn, int agent_pk, long pfile_pk);
int processRootNode(PGconn *pgConn, pbucketdef_t *bucketDefList, int agent_pk, long uploadtree_pk);

/* in validate.c */
int checkPQresult(PGresult *result, char *sql, char *FcnName, int LineNumb);
pbucketdef_t *initBuckets(PGconn *pgConn, int bucketpool_pk);
int getBucketpool_pk(PGconn *pgConn, char * bucketpool_name);
int validate_pk(PGconn *pgConn, char *sql);
void Usage(char *Name);

#endif /* _BUCKETS_H */
