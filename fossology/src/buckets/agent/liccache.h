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

/* shortname cache very simple nonresizing hash table */
struct cachenode
{
  char *rf_shortname;
  long  rf_pk;
};
typedef struct cachenode cachenode_t;

struct cacheroot
{
  int maxnodes;
  cachenode_t *nodes;
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
