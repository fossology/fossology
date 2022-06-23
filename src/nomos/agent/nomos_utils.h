/*
 SPDX-FileCopyrightText: © 2006-2014 Hewlett-Packard Development Company, L.P.

 SPDX-License-Identifier: GPL-2.0-only
*/

#ifndef NOMOS_UTILS_H_
#define NOMOS_UTILS_H_

#ifndef _GNU_SOURCE
#define _GNU_SOURCE
#endif /* not defined _GNU_SOURCE */
#include "util.h"
#include "list.h"
#include "licenses.h"
#include "process.h"
#include "nomos.h"
#include "nomos_regex.h"
#include "_autodefs.h"

#define PG_ERRCODE_UNIQUE_VIOLATION "23505"
#define FOSSY_EXIT( XY , XZ) printf(" %s %s,%d", XY , __FILE__, __LINE__);  Bail( XZ );
#define LICENSE_REF_TABLE "ONLY license_ref"
#define SEM_DEFAULT_VALUE 4

extern sem_t* mutexJson;           ///< Mutex to handle JSON writes
extern gboolean* printcomma;       ///< True to print comma while printing JSON object


/** shortname cache very simple nonresizing hash table */
struct cachenode
{
    char *rf_shortname;     ///< License shortname
    long rf_pk;             ///< License id from database
};
typedef struct cachenode cachenode_t;

/** Root for chachenode_t nodes */
struct cacheroot
{
    int maxnodes;           ///< No. of nodes in the list
    cachenode_t *nodes;     ///< Array of nodes
};
typedef struct cacheroot cacheroot_t;

void freeAndClearScan(struct curScan *thisScan);
char *getFieldValue(char *inStr, char *field, int fieldMax, char *value, int valueMax, char separator);
void parseLicenseList();
void Usage(char *Name);
void Bail(int exitval);
int optionIsSet(int val);
void getFileLists(char *dirpath);
void processFile(char *fileToScan);
int recordScanToDB(cacheroot_t *pcroot, struct curScan *scanRecord);
long get_rfpk(cacheroot_t *pcroot, char *rf_shortname);
char convertIndexToHighlightType(int index);
long add2license_ref(char *licenseName);
long updateLicenseFile(long rfPk);
int updateLicenseHighlighting(cacheroot_t *pcroot);
int initLicRefCache(cacheroot_t *pcroot);
long lrcache_hash(cacheroot_t *pcroot, char *rf_shortname);
int lrcache_add(cacheroot_t *pcroot, long rf_pk, char *rf_shortname);
long lrcache_lookup(cacheroot_t *pcroot, char *rf_shortname);
void lrcache_free(cacheroot_t *pcroot);
void initializeCurScan(struct curScan* cur);
void addLicence(GArray* theMatches, char* licenceName );
void cleanLicenceBuffer();
bool clearLastElementOfLicenceBuffer();   //returns true to be used in if-statements
void cleanLicenceAndMatchPositions( LicenceAndMatchPositions* in );
MatchPositionAndType* getMatchfromHighlightInfo(GArray* in, int index);
LicenceAndMatchPositions* getLicenceAndMatchPositions(GArray* in,int  index);
void cleanTheMatches(GArray* in);

#endif /* NOMOS_UTILS_H_ */
