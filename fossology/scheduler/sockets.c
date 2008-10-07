/*******************************************************
 sockets.c: Manage spawned sockets.

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
 *******************************************************/

#include <stdlib.h>
#include <stdio.h>
#include <unistd.h>
#include <errno.h>
#include <string.h>
#include <ctype.h>
#include <time.h>
#include <sys/types.h>
#include <sys/wait.h>
#include <syslog.h>

#include "debug.h"
#include "scheduler.h"
#include "spawn.h"
#include "agents.h"
#include "sockets.h"

/************************************************************************/
/************************************************************************/
/** I/O Functions *******************************************************/
/************************************************************************/
/************************************************************************/

/**********************************************
 ReadCmdFD(): Read a command from a file descriptor.
 If the line is empty, then try again.
 Returns line length, or -1 for EOF, or 0 for no data.
 NOTE: This stops when a CR is found!
 **********************************************/
int	ReadCmdFD	(int Fin, char *Cmd, int MaxCmd)
{
  int i;
  int rc;
  time_t StartTime;

  if (Fin==0) return(-1); /* Fin==0 means unallocated */

  StartTime = time(NULL);
  memset(Cmd,'\0',MaxCmd);
  i=0;
  /* read() should be done on a non-blocking file descriptor, but just in
     case, use the alarm signal to break out after 10 seconds. */
  alarm(10);	/* only allow this to sit for 10 seconds */
  rc=read(Fin,Cmd+i,1);
  alarm(0);
  /* man page says read() returns 0 for EOF, but 0 sometimes happens with
     signals */
  if (!rc && (errno == EAGAIN)) return(0); /* no data */
  while((rc != 0) && (Cmd[i]>=0) && (i<MaxCmd))
    {
    if (rc < 0)
	{
	/* some kind of error */
	switch(errno)
	  {
	  case EAGAIN:
		/* no data, would have blocked */
		/* allow it to retry for up to 2 seconds */
		/* This return only happens when a partial line is read and
		   the entire line could not be read within 1 full second. */
		if (time(NULL) <= StartTime+2) return(i);
		break;
	  case EBADF:
	  default:
		perror("Read error from client:");
		syslog(LOG_ERR,"READ ERROR: errno=%d  rc=%d  Bytes=%d '%s'\n",errno,rc,i,Cmd);
		return(i); /* huh? -- no data */
	  }
	}
    else if (Cmd[i]=='\n')
	{
	/* This is the correct place for a normal return */
	Cmd[i]='\0'; /* remove CR */
	return(i);
	/* if it is a blank line, then ignore it. */
	}
    else
	{
	i++;
	}
    alarm(10);
    rc=read(Fin,Cmd+i,1);
    alarm(0);
    }
  /* This return only happens when a partial line is read. */
  return(i);
} /* ReadCmdFD() */

/**********************************************
 ReadCmd(): Read a command from stdin.
 If the line is empty, then try again.
 Returns line length, or -1 of EOF.
 **********************************************/
int	ReadCmd	(FILE *Fin, char *Cmd, int MaxCmd)
{
  int C;
  int i;

  memset(Cmd,'\0',MaxCmd);
  if (feof(Fin)) return(-1);

  i=0;
  C=fgetc(Fin);
  if (C<0) return(-1);
  while(!feof(Fin) && (C>=0) && (i<MaxCmd))
    {
    if (C=='\n')
	{
	if (i > 0) return(i);
	/* if it is a blank line, then ignore it. */
	}
    else
	{
	Cmd[i]=C;
	i++;
	}
    C=fgetc(Fin);
    }
  return(i);
} /* ReadCmd() */

/************************************************************************/
/************************************************************************/
/** Debugging ***********************************************************/
/************************************************************************/
/************************************************************************/

/**********************************************
 Pause(): print a message and wait for a CR.
 This is used for debugging.
 **********************************************/
void	Pause	(char *Message)
{
  int C;

  fprintf(stderr,"======================================\n");
  fprintf(stderr,"%s\n",Message);
  fprintf(stderr,"PAUSED (press enter)\n");
  C='@';
  while(!feof(stdin) && (C!='\n'))
	{
	C=fgetc(stdin);
	}
} /* Pause() */


/************************************************************************/
/************************************************************************/
/** Sockets *************************************************************/
/************************************************************************/
/************************************************************************/

/**********************************************
 SelectAnyData(): Check for any pending data.
 This checks the input stream (Fin) and all living children.
 NOTE: This blocks until *something* has pending data.
 Returns logical OR:
   0 = no data (timeout)
   1 = data found on Fin
   2 = data found on at least one thread
   4 = error
 **********************************************/
int	SelectAnyData	(int HasFin, int Fin)
{
  fd_set FD;
  int Thread;
  int MaxFD;
  int rc;
  time_t Now;
  static time_t LastTimeCheck = 0;
  struct timeval Timeout;
  char Ctime[MAXCTIME];

  /* watch for any hung processes */
  Now = time(NULL);
  /** If the scheduler is slow, then don't check for dead children.
      Otherwise, all children will look dead. **/
  if (Now - LastTimeCheck < MAXHEARTBEAT) CheckChildren(Now);
  else if (LastTimeCheck != 0)
	{
	memset(Ctime,'\0',MAXCTIME);
	ctime_r(&Now,Ctime);
	printf("WARNING: scheduler is running slow.  It took %d seconds to check agent status: %s",(int)(Now - LastTimeCheck),Ctime);
	/* Reset all heardbeats since I cannot tell if any are hung */
	for(Thread=0; Thread < MaxThread; Thread++)
	  {
	  CM[Thread].Heartbeat = Now;
	  }
	}
  LastTimeCheck = Now;

reselect:
  FD_ZERO(&FD);
  MaxFD=-1;
  Timeout.tv_sec = 10;
  Timeout.tv_usec = 0;
  if (HasFin)
    {
    FD_SET(Fin,&FD); /* check for data on input */
    MaxFD = Fin;
    }

  for(Thread=0; Thread < MaxThread; Thread++)
    {
    if (CM[Thread].Status > ST_FREEING)
	{
	FD_SET(CM[Thread].ChildStdout,&FD);
	if (MaxFD < CM[Thread].ChildStdout) MaxFD=CM[Thread].ChildStdout;
	}
    /* Not really the best place for this, but convenient */
    else if ((CM[Thread].Status == ST_FREEING) &&
        (CM[Thread].StatusTime+MINKILLTIME < Now))
        {
        /* if there is a child that won't die, then kill it!!! */
        if (CM[Thread].ChildPid) kill(CM[Thread].ChildPid,SIGKILL);
        }
    }
  if (MaxFD < 0) return(0);	/* nobody to look at */

  rc = select(MaxFD+1,&FD,NULL,NULL,&Timeout);
  if (rc==0) return(0); /* timeout */
  if ((rc<=-1) && (errno == EINTR)) goto reselect; /* interrupted */
  if (rc<=-1) { perror("Select failed"); return(4); } /* error */

  /* handle children that have data */
  for(Thread=0; Thread < MaxThread; Thread++)
    {
    if ((CM[Thread].Status > ST_FREE) && FD_ISSET(CM[Thread].ChildStdout,&FD))
	ReadChild(Thread);
    }

  if ((rc == 1) && HasFin && FD_ISSET(Fin,&FD)) return(1); /* input only */
  /* else, rc > 1 or rc==1 and not Fin */

  if (HasFin && FD_ISSET(Fin,&FD)) return(3); /* input and thread */
  return(2); /* at least one child */
} /* SelectAnyData() */

