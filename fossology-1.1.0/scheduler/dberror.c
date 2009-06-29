/*******************************************************
 dberror: Functions for storing errors into the DB.

 Copyright (C) 2007 Hewlett-Packard Development Company, L.P.
 
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
 *******************************************************************/

#include <stdlib.h>
#include <stdio.h>
#include <string.h>
#include <ctype.h>

#include "libfossdb.h"
#include "scheduler.h"
#include "spawn.h"
#include "dberror.h"
#include "dbq.h"
#include "dbstatus.h"
#include "logging.h"

/***********************************************************
 DBErrorInit(): Prepare the error table.
 ***********************************************************/
void	DBErrorInit	()
{
} /* DBErrorInit() */

/***********************************************************
 DBErrorClose(): Prepare the error table.
 ***********************************************************/
void	DBErrorClose	()
{
} /* DBErrorClose() */

/***********************************************************
 DBErrorWrite(): Save an error to the table.
 Type values:
   - "FATAL" Technical and detailed errors.
   - "ERROR" Human readable errors.
   - "WARNING" Human readable warning.
   - "LOG" Machine readable warning.
   - "DEBUG" Debugging message.
 Message is in the format:
   logtype what where message
 e.g.,
   WARNING pfile 12345 This is a warning\n
 ***********************************************************/
void	DBErrorWrite	(int Thread, char *Type, char *Message)
{
  /* Until we rebuild the log table, log errors. */
  LogPrint("%s: In thread %d: %s\n",Type,Thread,Message);
} /* DBErrorWrite() */

