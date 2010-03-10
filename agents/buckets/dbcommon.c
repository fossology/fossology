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
 \file dbcommon.c
 \brief common database functions
 */

#include "dbcommon.h"


/****************************************************
 checkPQresult

 check the result status of a postgres SELECT
 If an error occured, write the error to stdout

 @param PGresult *result
 @param char *sql the sql query
 @param char * FcnName the function name of the caller
 @param int LineNumb the line number of the caller

 @return 0 on OK, -1 on failure.
 On failure, result will be freed.

 NOTE: this function should be moved to a std library
****************************************************/
FUNCTION int checkPQresult(PGresult *result, char *sql, char *FcnName, int LineNumb)
{
   if (!result)
   {
     printf("Error: %s:%d - checkPQresult called with invalid parameter.\n",
             FcnName, LineNumb);
     return 0;
   }

   /* If no error, return */
   if (PQresultStatus(result) == PGRES_TUPLES_OK) return 0;

   printf("ERROR: %s:%d, %s\nOn: %s\n", 
          FcnName, LineNumb, PQresultErrorMessage(result), sql);
   PQclear(result);
   return (-1);
} /* checkPQresult */


/****************************************************
 checkPQcommand

 check the result status of a postgres commands (not select)
 If an error occured, write the error to stdout

 @param PGresult *result
 @param char *sql the sql query
 @param char * FcnName the function name of the caller
 @param int LineNumb the line number of the caller

 @return 0 on OK, -1 on failure.
 On failure, result will be freed.

 NOTE: this function should be moved to a std library
****************************************************/
FUNCTION int checkPQcommand(PGresult *result, char *sql, char *FcnName, int LineNumb)
{
   if (!result)
   {
     printf("Error: %s:%d - checkPQcommand called with invalid parameter.\n",
             FcnName, LineNumb);
     return 0;
   }

   /* If no error, return */
   if (PQresultStatus(result) == PGRES_COMMAND_OK) return 0;

   printf("ERROR: %s:%d, %s\nOn: %s\n", 
          FcnName, LineNumb, PQresultErrorMessage(result), sql);
   PQclear(result);
   return (-1);
} /* checkPQcommand */

