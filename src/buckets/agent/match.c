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


/**
 * \brief Does this regex match any license name for this pfile?
 *
 * \param PGresult $result    results from select of lic names for this pfile
 * \param int      $numLics   number of lics in result
 * \param regex_t $compRegex ptr to compiled regex to check
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
