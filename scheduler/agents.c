/*******************************************************
 agents.c: Manage agent requests.

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
#include <unistd.h>
#include <string.h>
#include <ctype.h>
#include <signal.h>

#include "agents.h"
#include "debug.h"
#include "scheduler.h"
#include "spawn.h"
#include "hosts.h"
#include "sockets.h"
#include "dbq.h"
#include "dbstatus.h"
#include "dberror.h"
#include "logging.h"

/************************************************************************/
/************************************************************************/
/** Child-Control Functions *********************************************/
/************************************************************************/
/************************************************************************/

/********************************************
 ReadChild(): read and process output from a child.
 Returns: 1 = Ready for command, 0=not ready, -1 for error.
 ********************************************/
int	ReadChild	(int Thread)
{
  char Cmd[MAXCMD];
  time_t Now;
  int rc;

  Now = time(NULL);

  if (Thread < 0) return(-1);
  if (CM[Thread].ChildStdout == 0) return(-1);
  while(1)
    {
    rc = ReadCmdFD(CM[Thread].ChildStdout,Cmd,MAXCMD);
    if (rc <= 0) return(rc);

    if (Verbose)
	{
	LogPrint("Child[%d] says: %s\n",Thread,Cmd);
	}
    /* Here is where we process the child's reply.
       Stop when we get to a line saying "OK" */
    if (Cmd[0]=='\0')	return(0);	/* skip blank lines */
    CM[Thread].Heartbeat = Now; /* heard from the child! */
    if (!strncmp(Cmd,"OK",2))
	{
	DBclose(CM[Thread].DB); CM[Thread].DB = NULL;
	/* Keep track of how long it was in the previous !ST_READY state.
	   This is used by DBremoveChild for logging durations. */
	/* set number of processed items, if it is not already set */
	if (CM[Thread].ItemsProcessed==0) CM[Thread].ItemsProcessed=1;
	ChangeStatus(Thread,ST_READY);
	if ((CM[Thread].StatusLast==ST_RUNNING) && (CM[Thread].IsDB==1))
	  {
	  DBSaveJobStatus(Thread,-1);
	  }
	if (CM[Thread].IsDB) DBremoveChild(Thread,0,"OK");
	CM[Thread].DBJobKey = 0;
	return(1);
	}
    /* FATAL are fatal errors. Don't be surprised if the child dies. */
    /* WARNING are non-fatal errors. The child should not die. */
    /* ERRORS are non-fatal errors. But the child may still die. */
    else if (!strncmp(Cmd,"FATAL ",6) || !strncmp(Cmd,"FATAL:",6))
	{
	DBErrorWrite(Thread,"FATAL",Cmd+6);
	LogPrint("DEBUG[%d]: %s\n",Thread,Cmd);
	}
    else if (!strncmp(Cmd,"ERROR ",6) || !strncmp(Cmd,"ERROR:",6))
	{
	DBErrorWrite(Thread,"ERROR",Cmd+6);
	LogPrint("DEBUG[%d]: %s\n",Thread,Cmd);
	}
    else if (!strncmp(Cmd,"WARNING ",8) || !strncmp(Cmd,"WARNING:",8))
	{
	DBErrorWrite(Thread,"WARNING",Cmd+8);
	LogPrint("DEBUG[%d]: %s\n",Thread,Cmd);
	}
    else if (!strncmp(Cmd,"LOG ",4) || !strncmp(Cmd,"LOG:",4))
	{
	DBErrorWrite(Thread,"LOG",Cmd+4);
	LogPrint("DEBUG[%d]: %s\n",Thread,Cmd);
	}
    else if (!strcmp(Cmd,"Success"))	{ /* TBD success */ }
    else if (!strncmp(Cmd,"Heartbeat",9))	{ /* Do nothing; just a heartbeat */ }
    else if (!strncmp(Cmd,"DB:",3))
	{
	/*** "DB:" is for debugging only!  Don't depend on it! ***/
	if (Verbose)
	  {
	  LogPrint("Child[%d]: '%s'\n",Thread,Cmd);
	  }
#if 0
	int PID;
	PID = fork(); /* don't tie up the scheduler with minutia! */
	if (PID == 0) /* if child */
#endif
	  {
	  int MaxCol, MaxRow;
	  int Row,Col;
	  char *V,*F;
	  int rc;
	  int Offset;
	  if (!CM[Thread].DB) CM[Thread].DB=DBopen();
	  Offset=3;
	  while(isspace(Cmd[Offset])) Offset++;
	  rc = DBLockAccess(CM[Thread].DB,Cmd+Offset);
	  switch(rc)
	    {
	    case 0: /* no data */
	      break;
	    case 1: /* got data */
	      if (CM[Thread].Status != ST_RUNNING) break; /* could be dying */

	      MaxRow=DBdatasize(CM[Thread].DB);
	      MaxCol=DBcolsize(CM[Thread].DB);
	      if (Verbose)
	        {
		LogPrint("Telling Child[%d]: DBSTART\n",Thread);
		}
	      write(CM[Thread].ChildStdin,"DBSTART\n",8);
	      for(Row=0; Row < MaxRow; Row++)
	        {
	        for(Col=0; Col < MaxCol; Col++)
	          {
		  if (Col > 0) write(CM[Thread].ChildStdin," ",1);
		  F = DBgetcolname(CM[Thread].DB,Col);
		  write(CM[Thread].ChildStdin,F,strlen(F));
		  write(CM[Thread].ChildStdin,"='",2);
		  V = DBgetvalue(CM[Thread].DB,Row,Col);
		  write(CM[Thread].ChildStdin,V,strlen(V));
		  write(CM[Thread].ChildStdin,"'",1);
		  if (Verbose)
		    {
		    LogPrint("Telling Child[%d]: %s=%s\n",Thread,F,V);
		    }
		  }
	        write(CM[Thread].ChildStdin,"\n",1);
	        } /* for each record */
	      if (Verbose)
	        {
		LogPrint("Telling Child[%d]: DBEOF\n",Thread);
		}
	      write(CM[Thread].ChildStdin,"DBEOF\n",4);
	      break;
	    default:
	      if (CM[Thread].Status != ST_RUNNING) break; /* could be dying */
	      if (Verbose)
	        {
		LogPrint("Telling Child[%d]: ERROR (%d)\n",Thread,rc);
		}
	      write(CM[Thread].ChildStdin,"ERROR\n",6);
	      break;
	    } /* switch */
	  if (Verbose)
	    {
	    LogPrint("Telling Child[%d]: OK\n",Thread);
	    }
	  if (CM[Thread].Status == ST_RUNNING)
		{
		write(CM[Thread].ChildStdin,"OK\n",3);
		}
#if 0
	  exit(0);
#endif
	  } /* if child DB command */
	/* else parent does nothing */
	} /* if DB: */
    else if (!strncmp(Cmd,"ItemsProcessed ",15))
	{
	CM[Thread].ItemsProcessed += atol(Cmd+15);
	}
    else if (!strncmp(Cmd,"ECHO ",5))
	{
	/* display command */
	LogPrint("%s\n",Cmd+5);
	return(0);
	}
    else
	{
	/* Unknown reply.  Use it as a debug. */
	LogPrint("DEBUG[%d]: %s\n",Thread,Cmd);
	return(0);
	}
    /** If we need to send stuff back to the DB, do it here! **/
    }
  /* Never gets here */
  return(-1);
} /* ReadChild() */

/********************************************
 MatchOneAttr(): Given one attribute, see if
 it matches.
 Returns: 1=match, 0=miss.
 ********************************************/
int	MatchOneAttr	(char *AttrList, char *Attr, int AttrLen)
{
  while(AttrList[0] != '\0')
    {
    while(isspace(AttrList[0])) AttrList++; /* skip spaces */
    /* if they are the same and it is not a subset match... */
    if (!strncmp(AttrList,Attr,AttrLen) &&
	(isspace(AttrList[AttrLen]) || (AttrList[AttrLen] == '\0')) )
	{
	return(1);
	}
    while((AttrList[0] != '\0') && !isspace(AttrList[0]))
	AttrList++; /* skip chars */
    }
  return(0); /* missed */
} /* MatchOneAttr() */

/********************************************
 MatchAttr(): Given a thread's attribute list and a
 string containing ZERO or more attributes, determine
 if all the attributes match the thread number.
 (Zero attributes always match.)
 Returns: 1=match, 0=miss.
 ********************************************/
int	MatchAttr	(char *AttrList, char *Attr)
{
  int a; /* index into Attr */
  int alen; /* length of Attr+a string */
  a=0;
  if (!Attr) return(1);
  while(Attr[0] != '\0')
    {
    while(isspace(Attr[0])) Attr++; /* skip spaces */
    /* find length */
    for(alen=0; (Attr[alen] != '\0') && !isspace(Attr[alen]); alen++) ;
    /* check if it matched */
    if ((alen > 0) && !MatchOneAttr(AttrList,Attr,alen))
	return(0); /* missed */
    Attr+=alen; /* skip string */
    }
  /* matched all?  Done! */
  return(1);
} /* MatchAttr() */

/********************************************
 CheckAgent(): Given an agent string, determine
 if there is at least one agent of the same kind.
 This is used to prevent claiming jobs that are
 not handled by the scheduler.
 Returns: thread number, or -1 if no match.
 ********************************************/
int	CheckAgent	(char *AgentType)
{
  int Thread;
  char Attr[MAXCMD];
  memset(Attr,'\0',MAXCMD);
  snprintf(Attr,MAXCMD-1,"agent=%s",AgentType);
  for(Thread=0; Thread < MaxThread; Thread++)
    {
    if (MatchAttr(CM[Thread].Attr,Attr)) return(Thread);
    }
  return(-1);
} /* CheckAgent() */

/********************************************
 StaleChild(): If a child has been ready for too long,
 then kill it since it is not needed.
 ********************************************/
void	StaleChild	()
{
  int Thread;
  time_t Now;
  Now = time(NULL);
  for(Thread=0; Thread < MaxThread; Thread++)
    {
    /* Check if it's been ready too long */
    if ((CM[Thread].Status == ST_READY) &&
	(CM[Thread].StatusTime+MAXKILLTIME < Now))
	{
	/* Child claims ready but is not needed.  Kill it! (Close stdin) */
	ChangeStatus(Thread,ST_FREEING);
	CheckClose(CM[Thread].ChildStdin);
	CheckClose(CM[Thread].ChildStdinRev);
	CM[Thread].ChildStdin=0;
	CM[Thread].ChildStdinRev=0;
	if (Verbose)
	  {
	  LogPrint("Scheduler: Closing old child[%d]\n",Thread);
	  }
	}
    }
} /* StaleChild() */

/********************************************
 CheckChildren(): Review all children and free
 up any hung processes.
 ********************************************/
void	CheckChildren	(time_t Now)
{
  int Thread;
  for(Thread=0; Thread < MaxThread; Thread++)
    {
    /** Kill any child who isn't dying on time **/
    if ((CM[Thread].Status == ST_FREEING) &&
        (CM[Thread].StatusTime+MINKILLTIME < Now))
	{
	/* if there is a child that won't die, then kill it!!! */
	if (CM[Thread].ChildPid) kill(CM[Thread].ChildPid,SIGKILL);
	}

#if 1
/** DISABLED UNTIL ALL CHILDREN SUPPORT HEARTBEATS **/
    /* Look for missing heartbeats */
    if ((CM[Thread].Status >= ST_PREP) &&
        (CM[Thread].Heartbeat + MAXHEARTBEAT < Now))
	{
	/* No sound from the child; assume child is hung */
	printf("ERROR[%d]: No heartbeat from child.\n",Thread);
	KillChild(Thread);
	}
#endif
    }
} /* CheckChildren() */

/********************************************
 GetChild(): Get the thread number of a ready child.
 Attr = list of required attributes.  (No attribute = matches!)
 Possible return values:
   -2 = error.
   -1 = no child ready.
   >= 0 = child thread number.
 NOTE: This should be called AFTER calling SelectAnyData().
 SelectAnyData() manages child states.  GetChild() only works on
 running children.
 ********************************************/
int	GetChild	(char *Attr, int IsUrgent)
{
  int Thread;
  int PossibleMatch=0;	/* number of Threads that match Attr */
  int ActiveMatch=0;	/* number of Threads that match Attr */
  int PossibleSpawn=0;	/* number of spawned Threads that match Attr */
  int PossibleDead=-1;	/* Thread that can be spawned */
  int PossibleDying=-1;	/* Thread that is currently dying */
  int PossibleKill=-1; /* Thread that can be killed to make room */
  time_t Now;
  int HostId=-1;

  if (SLOWDEATH) return(-1);

  HostId = GetHostFromAttr(Attr);
  Now=time(NULL);
  if (Verbose > 1)
    {
    LogPrint("GetChild(): No running child (yet) -- want host=%d\n",HostId);
    }

  /* This loop summarizes the status of all running children. */
  for(Thread=0; Thread < MaxThread; Thread++)
    {
    /* check for the right host */
    if ((HostId >= 0) && (HostId != CM[Thread].HostId)) continue;

    /* if the job has the right attributes and can run... */
    if (MatchAttr(CM[Thread].Attr,Attr))
        {
	/* there is a possible match */
	PossibleMatch++;
	if (CM[Thread].Status != ST_FREE) ActiveMatch++;
	/* NOTE: There is a problem here since we are not checking
	   if the thread is reserved for Urgent use.  But since we are
	   not using the urgent flag, right now... ignore the problem. */
	if (CM[Thread].Status == ST_READY) return(Thread);
	else if (CM[Thread].Status == ST_SPAWNED) PossibleSpawn++;
	else if (CanHostRun(CM[Thread].HostId,IsUrgent))
	  {
	  /* if we could possibly spawn a new process... */
	  if (CM[Thread].Status == ST_FREEING) PossibleDying=Thread;
	  else if (CM[Thread].Status == ST_FREE) PossibleDead=Thread;
	  }
	}
    else
	{
	/* Ok: right host, but wrong thread. Tag it for possible killing. */
	if ((CM[Thread].Status == ST_READY) &&
	    (CM[Thread].StatusTime+MINKILLTIME < Now))
	  {
	  /* keep track of the oldest living target */
	  if ((PossibleKill == -1) || (CM[Thread].StatusTime < CM[PossibleKill].StatusTime))
	    {
	    PossibleKill=Thread;
	    }
	  }
	}
    } /* for summary loop */

  /* If it gets here, then there is no READY children. */

  /* If there is no possible way... (e.g., unknown job type) */
  if (!PossibleMatch) return(-2); /* no possible way */
  if (PossibleMatch == ActiveMatch) return(-1); /* all in use, so timeout */

  /* If a child is spawning, then don't wait for another child */
  /** Spawning another child won't make it run any faster. **/
  if (PossibleSpawn > 0) return(-1);

  /* At this point, there is nothing spawned, but it is allowed to run. */

  /* Case: We can spawn a child. */
  if (PossibleDead != -1)
	{
	if (SpawnEngine(PossibleDead))
	  {
	  if (Verbose)
	    {
	    LogPrint("Child[%d] spawned\n",PossibleDead);
	    }
	  return(PossibleDead);
	  }
        if (Verbose)
	  {
	  LogPrint("ERROR: SpawnEngine[%d] failed.\n",PossibleDead);
	  }
	return(-2);
	}

  /* Case: We can kill a child (only if nobody is dying) */
  if ((PossibleDying == -1) && (PossibleKill != -1))
	{
	if (Verbose > 1)
	  {
	  LogPrint("Scheduler: Need to kill child[%d].\n",PossibleKill);
	  }
	/* Child claims ready but is not needed.  Kill it! (Close stdin) */
	ChangeStatus(PossibleKill,ST_FREEING);
	CheckClose(CM[PossibleKill].ChildStdin);
	CheckClose(CM[PossibleKill].ChildStdinRev);
	CM[PossibleKill].ChildStdin=0;
	CM[PossibleKill].ChildStdinRev=0;
	}

  /*****
   Ok, here's the issue...
   There is a possible child, but it's not spawned.
   All running slots are taken by active Running, Spawning, or Dying.
   Solution: Timeout and wait for another running child.
   *****/
  return(-1);	/* must be a timeout */
} /* GetChild() */

/********************************************
 SchedulerCommand(): Check if a command is for the scheduler.
 If so, process it and return "1".
 If not, skip it and return "0".
 ********************************************/
int	SchedulerCommand	(char *Attr, char *Cmd)
{
  char *S;
  int i;
  int Thread;

  if (MatchAttr(Attr,"scheduler=wait"))
    {
    /* Ok, it's a command to wait */
    /* clear out the command and then wait */
    S=strstr(Attr,"scheduler=wait");
    for(i=0; i<strlen("scheduler=wait"); i++) S[i]=' ';
    /* wait for all children of this type to complete */
    for(Thread=0; Thread < MaxThread; Thread++)
	{
	/* skip children with different attributes */
	if (!MatchAttr(CM[Thread].Attr,Attr)) continue;
	while(CM[Thread].Status == ST_RUNNING)
	  {
	  SelectAnyData(0,0); /* wait for a child to become ready */
	  }
	}
    /* done with command */
    return(1);
    }
  return(0);
} /* SchedulerCommand() */

