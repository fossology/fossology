/***************************************************************
 libfossagent: Set of generic functions handy for agent development.

 Copyright (C) 2009 Hewlett-Packard Development Company, L.P.
 
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
#include <stdlib.h>

/* specify support for files > 2G */
#define __USE_LARGEFILE64
#define __USE_FILE_OFFSET64

#include <stdio.h>
#include <unistd.h>
#include <sys/types.h>
#include <sys/stat.h>
#include <sys/wait.h>
#include <string.h>
#include <ctype.h>
#include <signal.h>
#include <grp.h>

#define lstat64(x,y) lstat(x,y)
#define stat64(x,y) stat(x,y)
typedef struct stat stat_t;

#include "libfossrepo.h"
#include "libfossdb.h"
#include "libfossagent.h"

/* for debugging */
extern int Debug;

#define FUNCTION 

/*  For heartbeat  */
long    HeartbeatValue=-1;
long    LastHeartbeatValue=-1;
long    HBItemsProcessed=0;
int     AlarmSecs = 60;

/**************************************************
 *  NewHeartbeat(): Send heartbeat on next alarm
 *    **************************************************/
FUNCTION void InitHeartbeat   ()
{
    HeartbeatValue = -1;
    LastHeartbeatValue = -1;
    HBItemsProcessed = 0;
}

/**************************************************
 *  Heartbeat(): Update heartbeat counter and items processed
 *    **************************************************/
FUNCTION void Heartbeat (long NewItemsProcessed)
{
    HeartbeatValue++;
    HBItemsProcessed = NewItemsProcessed;
}

/**************************************************
 *  ShowHeartbeat(): Given an alarm signal, display a
 *   heartbeat.
 * **************************************************/
FUNCTION void ShowHeartbeat   (int Sig)
{

  /* IF we are tracking hearbeat values AND it has not changed,
     THEN don't display a heartbeat message.
     This can happen if I/O is hung, but alarms are still being processed.
   */
   if ((HeartbeatValue == -1) || (HeartbeatValue != LastHeartbeatValue))
   {
     if (HBItemsProcessed > 0) printf("ItemsProcessed %ld\n", HBItemsProcessed);
     LastHeartbeatValue = HeartbeatValue;
     printf("Heartbeat\n");
     fflush(stdout);
   }

   /* re-schedule itself */
   alarm(AlarmSecs);
} /* ShowHeartbeat() */

/**********************************************
 ReadLine(): Read a command from a stream.
 If the line is empty, then try again.
 Returns line length, or -1 of EOF.
 **********************************************/
FUNCTION int     ReadLine (FILE *Fin, char *Line, int MaxLine)
{
  int C;
  int i;

  if (!Fin) return(-1);
  if (feof(Fin)) return(-1);
  memset(Line,'\0',MaxLine);
  i=0;
  C=fgetc(Fin);
  if (C<0) return(-1);
  while(!feof(Fin) && (C>=0) && (i<MaxLine))
    {
    if (C=='\n')
        {
        if (i > 0) return(i);
        /* if it is a blank line, then ignore it. */
        }
    else
        {
        Line[i]=C;
        i++;
        }
    C=fgetc(Fin);
    }
  return(i);
} /* ReadLine() */


/***************************************************
 IsFile(): Given a filename, is it a file?
 Link: should it follow symbolic links?
 Returns 1=yes, 0=no.
 ***************************************************/
FUNCTION int      IsFile  (char *Fname, int Link)
{
  stat_t Stat;
  int rc;
  if (!Fname || (Fname[0]=='\0')) return(0);  /* not a directory */
  if (Link) rc = stat64(Fname,&Stat);
  else rc = lstat64(Fname,&Stat);
  if (rc != 0) return(0); /* bad name */
  return(S_ISREG(Stat.st_mode));
} /* IsFile() */


/*********************************************************
 GetAgentKey(): Get the Agent Key from the database.
 Upload_pk is only used for error reporting.
 *********************************************************/
FUNCTION int	GetAgentKey	(void *DB, char * agent_name, long Upload_pk, char *svn_rev, char *agent_desc)
{
  int rc;
  int Agent_pk=-1;    /* agent identifier */
  char sql[256];

  sprintf(sql, "SELECT agent_pk FROM agent WHERE agent_name ='%s' ORDER BY agent_rev DESC", agent_name);
  rc = DBaccess(DB, sql);
  if (rc < 0)
  {
	printf("ERROR upload %ld unable to access the database\n",Upload_pk);
	printf("LOG upload %ld unable to select %s from the database table agent\n",Upload_pk, agent_name);
	DBclose(DB);
	exit(16);
  }

  if (DBdatasize(DB) <= 0)
  {
    /* Not found? Add it! */
    sprintf(sql, "INSERT INTO agent (agent_name,agent_rev,agent_desc) VALUES ('%s',E'%s',E'%s')",
              agent_name, svn_rev, agent_desc);
    rc = DBaccess(DB,sql);
    if (rc < 0)
	{
	printf("ERROR upload %ld unable to write to the database\n",Upload_pk);
	printf("LOG upload %ld unable to write %s to the database table agent\n",Upload_pk, agent_name);
	DBclose(DB);
	exit(17);
	}

    sprintf(sql, "SELECT agent_pk FROM agent WHERE agent_name ='%s' ORDER BY agent_pk DESC", agent_name);
    rc = DBaccess(DB,sql);
    if (rc < 0)
	{
      printf("ERROR upload %ld unable to access the database\n",Upload_pk);
      printf("LOG upload %ld unable to select %s from the database table agent\n",Upload_pk, agent_name);
      DBclose(DB);
      exit(18);
    }
  }
  Agent_pk = atoi(DBgetvalue(DB,0,0));
  return Agent_pk;
} /* GetAgentKey() */
