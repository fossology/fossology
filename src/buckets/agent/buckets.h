/*
 SPDX-FileCopyrightText: © 2010-2014 Hewlett-Packard Development Company, L.P.

 SPDX-License-Identifier: GPL-2.0-only
*/

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
#include <sys/wait.h>

#include <libfossology.h>
#include "liccache.h"
#define FUNCTION

#define myBUFSIZ       2048
#define MAXSQL         1024

#define IsContainer(mode)  ((mode & 1<<29) != 0)
#define IsArtifact(mode)   ((mode & 1<<28) != 0)


/**
 * struct regex_file_struct
 * \brief REGEX-FILE bucket type
 *
 * The ftypes tell you what data to apply the regex to.
 */
struct regex_file_struct
{
  int      ftype1;          /**< 1=filename, 2=license */
  char    *regex1;          /**< regex1 string */
  regex_t  compRegex1;
  int      op;              /**< 0=end of expression, 1=and, 2=or, 3=not  */
  int      ftype2;          /**< 1=filename, 2=license */
  char    *regex2;          /**< regex2 string */
  regex_t  compRegex2;
};
typedef struct regex_file_struct regex_file_t, *pregex_file_t;

/**
 * struct bucketdef_struct
 * Bucket definition
 */
struct bucketdef_struct
{
  int      bucket_pk;       /**< bucket id */
  char    *bucket_name;     /**< bucker name */
  int      bucket_type;     /**< 1=MATCH_EVERY, 2=MATCH_ONLY, 3=REGEX, 4=EXEC, 5=REGEX-FILE, 99=Not in any other bucket. */
  char    *regex;           /**< regex string */
  regex_t  compRegex;       /**< compiled regex if type=3 */
  char    *dataFilename;    /**< File in PROJECTSTATEDIR */
  int     *match_only;      /**< array of rf_pk's if type=2 */
  int    **match_every;     /**< list of arrays of rf_pk's if type=1 */
  regex_file_t *regex_row;  /**< array of regex_file_structs if type=5 */
  char     stopon;          /**< Y to stop procecessing if this bucket matches */
  char     applies_to;      /**< 'f'=every file, 'p'=packages only  */
  int      nomos_agent_pk;  /**< nomos agent_pk whose results this bucket scan is using */
  int      bucket_agent_pk; /**< bucket agent_pk */
  int      bucketpool_pk;
  char    *uploadtree_tablename;
};
typedef struct bucketdef_struct bucketdef_t, *pbucketdef_t;

/**
 * struct bucketpool_struct
 * Bucket pool
 *
 * NOTE: This struct is not currently used.  When it is, move
 * nomos_agent_pk, bucket_agent_pk to here and remove from bucketdef
*/
struct bucketpool_struct
{
  int  bucketpool_pk;
  int  bucketpool_name;
  int  bucketpool_version;
  int  nomos_agent_pk;
  int  bucket_agent_pk;
  pbucketdef_t pbucketdef;  /**< array of bucketdef's which define all the buckets for this pool  */
};
typedef struct bucketpool_struct bucketpool_t, *pbucketpool_t;

/**
 * struct uploadtree_struct
 * uploadtree record values
 */
struct uploadtree_struct
{
  int      uploadtree_pk;  /**< Upload tree id */
  char    *ufile_name;     /**< Upload name */
  int      upload_fk;      /**< Upload id */
  int      ufile_mode;     /**< Upload mode */
  int      pfile_fk;       /**< Pfile id */
  int      lft;            /**< Left child range */
  int      rgt;            /**< Right child range */
};
typedef struct uploadtree_struct uploadtree_t, *puploadtree_t;

/**
 * struct package_struct
 * package record values
 */
struct package_struct
{
  char pkgname[256];       /**< Package name */
  char pkgvers[256];       /**< Package version */
  char vendor[256];        /**< Vendor name */
  char srcpkgname[256];    /**< Source name */
};
typedef struct package_struct package_t, *ppackage_t;

/* walk.c */
int walkTree(PGconn *pgConn, pbucketdef_t bucketDefArray, int agent_pk,
             int uploadtree_pk, int skipProcessedCheck,
             int hasPrules);

int processFile(PGconn *pgConn, pbucketdef_t bucketDefArray,
                      puploadtree_t puploadtree, int agent_pk, int hasPrules);

/* leaf.c */
int processLeaf(PGconn *pgConn, pbucketdef_t bucketDefArray,
                puploadtree_t puploadtree, ppackage_t ppackage,
                int agent_pk, int hasPrules);

int *getLeafBuckets(PGconn *pgConn, pbucketdef_t bucketDefArray,
                    puploadtree_t puploadtree, ppackage_t ppackage,
                    int hasPrules);

/* container.c */
int *getContainerBuckets(PGconn *pgConn, pbucketdef_t bucketDefArray, int uploadtree_pk);

/* child.c */
int childInBucket(PGconn *pgConn, pbucketdef_t in_bucketDef, puploadtree_t puploadtree);

/* write.c */
int writeBuckets(PGconn *pgConn, int pfile_pk, int uploadtree_pk,
                 int *bucketList, int agent_pk, int nomosagent_pk, int bucketpool_pk);

/* match.c */
int matchAnyLic(PGresult *result, int numLics, regex_t *compRegex);


/* validate.c */
int arrayAinB        (int *arrayA, int *arrayB);
int intAinB          (int intA, int *arrayB);
int validate_pk      (PGconn *pgConn, char *sql);
void Usage           (char *Name);
int processed        (PGconn *pgConn, int agent_pk, int nomos_agent_pk, int pfile_pk, int uploadtree_pk, int bucketpool_pk, int bucket_pk);
int UploadProcessed  (PGconn *pgConn, int bucketagent_pk, int nomosagent_pk, int pfile_pk, int uploadtree_pk, int upload_pk, int bucketpool_pk);

/* inits.c */
pbucketdef_t initBuckets   (PGconn *pgConn, int bucketpool_pk, cacheroot_t *pcroot);
int *getMatchOnly    (PGconn *pgConn, int bucketpool_pk, char *filename, cacheroot_t *pcroot);
int **getMatchEvery  (PGconn *pgConn, int bucketpool_pk, char *filename, cacheroot_t *pcroot);
regex_file_t *getRegexFile  (PGconn *pgConn, int bucketpool_pk, char *filename, cacheroot_t *pcroot);
int getRegexFiletype (char *token, char *filepath);
int getBucketpool_pk (PGconn *pgConn, char * bucketpool_name);
int LatestNomosAgent(PGconn *pgConn, int upload_pk);
int *getLicsInStr    (PGconn *pgConn, char *nameStr, cacheroot_t *pcroot);
int childParent      (PGconn *pgConn, int uploadtree_pk);

#endif /* _BUCKETS_H */
