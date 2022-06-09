/*
 SPDX-FileCopyrightText: © 2010 Hewlett-Packard Development Company, L.P.

 SPDX-License-Identifier: GPL-2.0-only
*/

#ifndef _LICCACHE_H
#define _LICCACHE_H 1
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

#define FUNCTION

/**
 * struct cachenode
 * Shortname cache node
 */
struct cachenode
{
  char *rf_shortname;    /**< License short name */
  long  rf_pk;           /**< License id */
};
typedef struct cachenode cachenode_t;

/**
 * struct cacheroot
 * Shortname cache, very simple non-resizing hash table
 */
struct cacheroot
{
  int maxnodes;          /**< Max nodes in table */
  cachenode_t *nodes;    /**< Nodes in table */
};
typedef struct cacheroot cacheroot_t;

/* liccache.c */
long lrcache_hash(cacheroot_t *pcroot, char *rf_shortname);
void lrcache_print(cacheroot_t *pcroot);
void lrcache_free(cacheroot_t *pcroot);
int  lrcache_add(cacheroot_t *pcroot, long rf_pk, char *rf_shortname);
long lrcache_lookup(cacheroot_t *pcroot, char *rf_shortname);
int  lrcache_init(PGconn *pgConn, cacheroot_t *pcroot);
long get_rfpk(PGconn *pgConn, cacheroot_t *pcroot, char *rf_shortname);
long add2license_ref(PGconn *pgConn, char *licenseName);

#endif /* _LICCACHE_H */
