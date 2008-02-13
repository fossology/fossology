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

void	*DBErrorKey = NULL;	/* key table */
int	DBErrorRows = 0;	/* rows in table */

struct logtype
  {
  char *Name;
  int Value;
  };
struct logtype LogType[] = {
	{"DEBUG",0} ,
	{"LOG",0} ,
	{"WARNING",1} ,
	{"ERROR",2} ,
	{"FATAL",3} ,
	{NULL,0}
	};

/***********************************************************
 DBErrorInit(): Prepare the error table.
 ***********************************************************/
void	DBErrorInit	()
{
  /***********************************************
   SELECT key_pk, key_name from key;
   ***********************************************/
  DBLockAccess(DB,"SELECT table_enum, table_name from table_enum;");
  DBErrorKey = DBmove(DB);
  DBErrorRows = DBdatasize(DBErrorKey);
  if (DBErrorRows <= 0)
	{
	fprintf(stderr,"WARNING: In the database, table_enum is empty.  Errors will not be logged.\n");
	}
} /* DBErrorInit() */

/***********************************************************
 DBErrorClose(): Prepare the error table.
 ***********************************************************/
void	DBErrorClose	()
{
  if (!DBErrorKey) return;
  DBclose(DBErrorKey);
  DBErrorKey=NULL;
  DBErrorRows=0;
} /* DBErrorClose() */

/***********************************************************
 DBErrorWrite(): Save an error to the table.
 Type values:
   0 = human readable warnings (non-fatal)
   1 = human readable error (fatal)
   2 = non-human readable debug code
 Message is in the format:
   logtype what where message
 e.g.,
   WARNING pfile 12345 This is a warning\n
 ***********************************************************/
void	DBErrorWrite	(int Thread, int GenericFlag, char *Message)
{
  int i;
  char *Label=NULL;
  int Index=-1;
  int LabelLen;
  char SQL[65536];
  int Type=0;
  int Where=0;

  if (!DBErrorKey) return;
  if (DBErrorRows <= 0) return;	/* table_enum is empty! */

  /* Find the log type */
  for(Type=0; LogType[Type].Name != NULL; Type++)
	{
	if (!strncasecmp(LogType[Type].Name,Message,strlen(LogType[Type].Name))) break;
	}
  Type = LogType[Type].Value;  /* Null == 0 so default is debug */
  /* skip the type */
  Message = strchr(Message,' ');
  if (!Message) return;
  Message++; /* skip the space */

  /* Find the message type */
  if (GenericFlag)
    {
    Index = -1;
    }
  else
    {
    i=-1;
    do
	{
	i++;
	Label = DBgetvalue(DBErrorKey,i,1); /* get key_name */
	LabelLen = strlen(Label);
	} while( strncasecmp(Message,Label,LabelLen) && (i<DBErrorRows-1) );
    if (i >= DBErrorRows) return;	/* type not found */
    Index = atoi(DBgetvalue(DBErrorKey,i,0)); /* get key_pk */

    /* skip the what */
    Message = strchr(Message,' ');
    if (!Message) return;
    Message++;

    /* Get the where value */
    Where = atoi(Message);
    /* skip the where */
    Message = strchr(Message,' ');
    if (!Message) return;
    Message++;
    }

  /* Insert the message into the DB */
  memset(SQL,'\0',sizeof(SQL));
  snprintf(SQL,sizeof(SQL)-4,"INSERT INTO log (log_jq_fk,log_table_enum,log_rec_fk,log_type,log_message) values ('%d','%d','%d','%d','",
  	GenericFlag ? -1 : CM[Thread].DBJobKey,
	Index,Where,Type);
  DBstrcatTaint(Message,SQL,sizeof(SQL)-4);
  strcat(SQL,"');");
  DBLockAccess(DB,SQL);
} /* DBErrorWrite() */

