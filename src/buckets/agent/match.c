/*
 SPDX-FileCopyrightText: © 2010 Hewlett-Packard Development Company, L.P.

 SPDX-License-Identifier: GPL-2.0-only
*/

/**
 * \file match.c
 * Matching license names with pfile contents using regex
 */

#include "buckets.h"


/**
 * \brief Does this regex match any license name for this pfile?
 *
 * \param result    Results from select of lic names for this pfile
 * \param numLics   Number of lics in result
 * \param compRegex ptr to compiled regex to check
 *
 * \return 1=true, 0=false
 */
FUNCTION int matchAnyLic(PGresult *result, int numLics, regex_t *compRegex)
{
  int   licNumb;
  char *licName;

  for (licNumb=0; licNumb < numLics; licNumb++)
  {
    licName = PQgetvalue(result, licNumb, 0);
    if (0 == regexec(compRegex, licName, 0, 0, 0)) return 1;
  }
  return 0;
}
