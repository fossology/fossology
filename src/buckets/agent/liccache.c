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
/**
 * \file liccache.c
 * \brief license_ref table cache functions
 *
 * This cache is an in memory hash table of the license_ref license
 * names and primary keys.
 */

#include "buckets.h"

/**
 * \brief calculate the hash of an rf_shortname
 * rf_shortname is the key
 * 
 * \param cacheroot_t $pcroot 
 * \param $rf_shortname
 *
 * \return hash value
 */
FUNCTION long lrcache_hash(cacheroot_t *pcroot, char *rf_shortname)
{
  long hashval = 0;
  int len, i;

  /* use the first sizeof(long) bytes for the hash value */
  len = (strlen(rf_shortname) < sizeof(long)) ? strlen(rf_shortname) : sizeof(long);
  for (i=0; i<len;i++) hashval += rf_shortname[i] << 8*i;
  hashval = hashval % pcroot->maxnodes;
  return hashval;
}
 
/**
 *
 * \brief Print the contents of the hash table
 *
 * \param cacheroot_t $pcroot
 *
 * \return none
 */
FUNCTION void lrcache_print(cacheroot_t *pcroot)
{
  cachenode_t *pcnode;
  long hashval = 0;
  int i;

  pcnode = pcroot->nodes;
  for (i=0; i<pcroot->maxnodes; i++)
  {
    if (pcnode->rf_pk != 0L) 
    {
      hashval = lrcache_hash(pcroot, pcnode->rf_shortname);
      // printf("%ld, %ld, %s\n", hashval, pcnode->rf_pk, pcnode->rf_shortname);
    }
    pcnode++;
  }
} 

/**
 *
 * \brief free the hash table
 * 
 * \param cacheroot_t $pcroot
 * 
 * \return none
 */
FUNCTION void lrcache_free(cacheroot_t *pcroot)
{
  cachenode_t *pcnode;
  int i;

  pcnode = pcroot->nodes;
  for (i=0; i<pcroot->maxnodes; i++)
  {
    if (pcnode->rf_pk != 0L) 
    {
      free(pcnode->rf_shortname);
    }
    pcnode++;
  }
  free(pcroot->nodes);
}

/**
 * \brief add a rf_shortname, rf_pk to the license_ref cache 
 * rf_shortname is the key
 *
 * \param cacheroot_t $pcroot 
 * \param $rf_pk
 * \param $rf_shortname
 *
 * \return -1 for failure, 0 for success
 */
FUNCTION int lrcache_add(cacheroot_t *pcroot, long rf_pk, char *rf_shortname)
{
  cachenode_t *pcnode;
  long hashval = 0;
  int i;
  int noden;

  hashval = lrcache_hash(pcroot, rf_shortname);

  noden = hashval;
  for (i=0; i<pcroot->maxnodes; i++)
  {
    noden = (hashval +i) & (pcroot->maxnodes -1);

    pcnode = pcroot->nodes + noden;
    if (!pcnode->rf_pk)
    {
      pcnode->rf_shortname = strdup(rf_shortname);
      pcnode->rf_pk = rf_pk;
      break;
    }
  }
  if (i < pcroot->maxnodes) return 0;

  return -1;  /* no space */
}

/**
 * \brief lookup rf_pk in the license_ref cache 
 * rf_shortname is the key
 *
 * \param cacheroot_t $pcroot
 * \param $rf_shortname
 *
 * \return rf_pk, 0 if the shortname is not in the cache
 */
FUNCTION long lrcache_lookup(cacheroot_t *pcroot, char *rf_shortname)
{
  cachenode_t *pcnode;
  long hashval = 0;
  int i;
  int noden;

  hashval = lrcache_hash(pcroot, rf_shortname);

  noden = hashval;
  for (i=0; i<pcroot->maxnodes; i++)
  {
    noden = (hashval +i) & (pcroot->maxnodes -1);

    pcnode = pcroot->nodes + noden;
    if (!pcnode->rf_pk) return 0;
    if (strcmp(pcnode->rf_shortname, rf_shortname) == 0) 
    {
      return pcnode->rf_pk;
    }
  }

  return 0;  /* not found */
}

/**
 *
 * \brief build a cache the license ref db table.
 *
 * \param PGconn $pgConn database connection
 * \param cacheroot_t $pcroot
 *
 * lrcache_init builds a cache using the rf_shortname as the key
 * and the rf_pk as the value.  This is an optimization. The cache is used for 
 * reference license lookups instead of querying the db.
 *
 * \return 0 for failure, 1 for success
 */

FUNCTION int lrcache_init(PGconn *pgConn, cacheroot_t *pcroot) 
{
    PGresult *result;
    char query[128];
    int row;
    int numLics;

    if (!pcroot) return 0;

    snprintf(query, sizeof(query),
            "SELECT rf_pk, rf_shortname FROM license_ref where rf_detector_type=2;");
    result = PQexec(pgConn, query);
    if (fo_checkPQresult(pgConn, result, query, "lrcache_init", __LINE__)) return 0;

    numLics = PQntuples(result);
    /* populate the cache  */
    for (row = 0; row < numLics; row++) 
    {
      lrcache_add(pcroot, atol(PQgetvalue(result, row, 0)), PQgetvalue(result, row, 1));
    }

    PQclear(result);

    return (1);
} /* lrcache_init */

/**
 * \brief Get the rf_pk for rf_shortname
 *
 * Checks the cache to get the rf_pk for this shortname.
 * If it doesn't exist, add it to both license_ref and the 
 * license_ref cache (the hash table).
 *
 * \param PGConn $pgConn database connection
 * \param cacheroot_t $pcroot
 * \param char $rf_shortname
 *
 * \return rf_pk of the matched license or 0
 */
FUNCTION long get_rfpk(PGconn *pgConn, cacheroot_t *pcroot, char *rf_shortname) 
{
  long  rf_pk;
  size_t len;

  if ((len = strlen(rf_shortname)) == 0) 
  {
    printf("ERROR! %s.%d get_rfpk() passed empty name", __FILE__, __LINE__);
    return (0);
  }

  /* is this in the cache? */
  rf_pk = lrcache_lookup(pcroot, rf_shortname);
  if (rf_pk) return rf_pk;

  /* shortname was not found, so add it */
  /* add to the license_ref table */
  rf_pk = add2license_ref(pgConn, rf_shortname);

  /* add to the cache */
  lrcache_add(pcroot, rf_pk, rf_shortname);

  return (rf_pk);
} /* get_rfpk */

/**
 * \brief Add a new license to license_ref table
 *
 * Adds a license to license_ref table.
 *
 * \param  char $licenseName
 *
 * \return rf_pk for success, 0 for failure
 */
FUNCTION long add2license_ref(PGconn *pgConn, char *licenseName) 
{
    PGresult *result;
    char  query[256];
    char  insert[256];
    char  escLicName[256];
    char *specialLicenseText;
    long rf_pk;

    int len;
    int error;
    int numRows;

    // escape the name
    len = strlen(licenseName);
    PQescapeStringConn(pgConn, escLicName, licenseName, len, &error);
    if (error)
      printf("WARNING: %s(%d): Does license name have multibyte encoding?", __FILE__, __LINE__);

    /* verify the license is not already in the table */
    sprintf(query, "SELECT rf_pk FROM license_ref where rf_shortname='%s' and rf_detector_type=2", escLicName);
    result = PQexec(pgConn, query);
    if (fo_checkPQresult(pgConn, result, query, "add2license_ref", __LINE__)) return 0;
    numRows = PQntuples(result);
    if (numRows)
    {
      rf_pk = atol(PQgetvalue(result, 0, 0));
      return rf_pk;
    }

    /* Insert the new license */
    specialLicenseText = "License by Nomos.";

    sprintf( insert,
            "insert into license_ref(rf_shortname, rf_text, rf_detector_type) values('%s', '%s', 2)",
            escLicName, specialLicenseText);
    result = PQexec(pgConn, insert);
    if (fo_checkPQcommand(pgConn, result, insert, __FILE__, __LINE__)) return 0;
    PQclear(result);

    /* retrieve the new rf_pk */
    result = PQexec(pgConn, query);
    if (fo_checkPQresult(pgConn, result, query, "add2license_ref", __LINE__)) return 0;
    numRows = PQntuples(result);
    if (numRows)
      rf_pk = atol(PQgetvalue(result, 0, 0));
    else
    {
      printf("ERROR: %s:%s:%d Just inserted value is missing. On: %s", __FILE__, "add2license_ref()", __LINE__, query);
      return(0);
    }
    PQclear(result);

    return (rf_pk);
}
