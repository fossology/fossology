/*******************************************************
 spawn.c: Functions for spawning children.

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

 ==========
 Definitions of terms:
 - Child :: a spawned process. Parent spawns children.
 - Agent :: a child that performs a task for the scheduler.
 In general, all children are agents and vice versa.
 The difference in terms denotes the different levels of interaction.
 In particular, Agents are high-level and denote functionality.
 Children are low-level and denote basic communication.

 ==========
 Known bugs and workarounds:
   syslog is not signal-safe!  There can be a race condition!
   This has been seen by other people:
	http://kerneltrap.org/mailarchive/git/2008/7/3/2335624
	http://linux.derkeiler.com/Mailing-Lists/Kernel/2007-09/msg08633.html
	http://linux.derkeiler.com/Mailing-Lists/Kernel/2007-09/msg08759.html
   Here's the problem (as far as I can tell):
   When the child dies, closelog() is called.  This sets a lock.
   However, there may be a delay between the handle locking
   and the next parent call to syslog().
   This is a race condition.
   The solution:
     Do NOT call syslog inside a SIGCHLD signal handler.
   The workaround:
     Instead, dump any messages to a temp file (Log).
     Later, dump the contents of the temp file to syslog (Log2Syslog).
     This is an ugly hack, but it works.
 Side note: this problem does not seem to appear when using "gcc".
 But it can be reliably reproduced when using "gcc -g".
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
#include <fcntl.h>
#include <syslog.h>

#include "debug.h"
#include "scheduler.h"
#include "spawn.h"
#include "agents.h"
#include "sockets.h"
#include "dbq.h"
#include "dbstatus.h"
#include "hosts.h"

#ifndef WEXITED
  /* For some odd reason, this is missing from older GCC headers */
  #define WEXITED 4
#endif

int MaxThread=0;	/* total number of spawned threads */

time_t	RespawnInterval=5*60;	/* 5 minutes */
time_t	RespawnCount=5;		/* Up to ## respawns every RespawnInterval */
#define MINFREETIME	5	/* seconds: must be dead before being respawned */

childmanager CM[MAXCHILD+1];	/* manage children */

char *StatusName[] = {
	"FAIL",	/* ST_FAIL */
	"FREE",	/* not spawned yet, no I/O allocated */
	"FREEING",	/* was spawned, now dying; no I/O allocated */
	"PREPARING",	/* getting it ready to spawn */
	"SPAWNED",	/* spawned but not yet ready (I/O allocated) */
	"READY",	/* live and ready for data */
	"RUNNING",	/* actively processing data */
	"DONE",	/* completed processing data */
	"END",	/* unused marker */
	NULL
	};

FILE	*Log=NULL;

/************************************************************************/
/************************************************************************/
/** Debug Functions *****************************************************/
/************************************************************************/
/************************************************************************/

/**********************************************
 ShowStates(): Debug state and show failures.
 **********************************************/
void	ShowStates	(int Thread)
{
  time_t Now;
  char Ctime[MAXCTIME];
  if (!ShowState) return;

  /* for speed: don't show READY/RUNNING transitions */
  if (CM[Thread].Status == ST_PREP) return;
  if ( ((CM[Thread].Status == ST_RUNNING) || (CM[Thread].Status == ST_READY))
	&&
	((CM[Thread].StatusLast == ST_RUNNING) ||
	 (CM[Thread].StatusLast == ST_READY)) )
	return;

  Now = time(NULL);
  memset(Ctime,'\0',MAXCTIME);
  ctime_r(&Now,Ctime);
  fprintf(Log,"Child[%d] '%s' state=%s(%d) @ %s",
	Thread,CM[Thread].Attr,
	StatusName[CM[Thread].Status],CM[Thread].Status,Ctime);
  if (CM[Thread].Status == ST_FAIL)
    {
    fprintf(Log,"  Attr:    '%s'\n",CM[Thread].Attr);
    fprintf(Log,"  Command: '%s'\n",CM[Thread].Command);
    fprintf(Log,"  Parm:    '%s'\n",CM[Thread].Parm);
    }
  CM[Thread].StatusLast = CM[Thread].Status;
} /* ShowStates() */

/**********************************************
 DebugThread(): Verbose -- say who is doing what.
 **********************************************/
void	DebugThread	(int Thread)
{
  char Ctime[MAXCTIME];
  fprintf(Log,"\nThread %d:\n",Thread);
  fprintf(Log,"  PID:       %d\n",CM[Thread].ChildPid);
  fprintf(Log,"  Pipes:     in=%d->%d / out=%d->%d\n",
	CM[Thread].ChildStdinRev,CM[Thread].ChildStdin,
	CM[Thread].ChildStdoutRev,CM[Thread].ChildStdout);
  fprintf(Log,"  Attr:      '%s'\n",CM[Thread].Attr);
  fprintf(Log,"  Command:   '%s'\n",CM[Thread].Command);
  fprintf(Log,"  Parm:      '%s'\n",CM[Thread].Parm);
  memset(Ctime,'\0',MAXCTIME);
  ctime_r(&(CM[Thread].Heartbeat),Ctime);
  fprintf(Log,"  Heartbeat:  %s",Ctime);
  memset(Ctime,'\0',MAXCTIME);
  ctime_r(&(CM[Thread].StatusTime),Ctime);
  fprintf(Log,"  State:      %s",Ctime);
  fprintf(Log,"  Status:     %d (%s)\n",CM[Thread].Status,StatusName[CM[Thread].Status]);
  memset(Ctime,'\0',MAXCTIME);
  ctime_r(&(CM[Thread].SpawnTime),Ctime);
  fprintf(Log,"  Spawn:      %d at %s",CM[Thread].SpawnCount,Ctime);
  fprintf(Log,"  DB:\n");
  fprintf(Log,"    IsDB:     %d\n",CM[Thread].IsDB);
  fprintf(Log,"    DBJobKey: %d\n",CM[Thread].DBJobKey);
  fprintf(Log,"    DBMSQrow: %d\n",CM[Thread].DBMSQrow);
  fprintf(Log,"    DBagent:  %d\n",CM[Thread].DBagent);
} /* DebugThread() */

/**********************************************
 DebugThreads(): Verbose -- say who is doing what.
 **********************************************/
void	DebugThreads	(int Flag)
{
  int Thread;
  fprintf(Log,"==============================\n");
  /* BuildVersion has a \n at the end */
  fprintf(Log,"Scheduler %s",BuildVersion);
  if (Flag & 0x01)
    {
    fprintf(Log,"Max Thread  = %d\n",MaxThread);
    fprintf(Log,"Total Running = %d\n",RunCount);
    }
  if (Flag & 0x02)
    {
    for(Thread=0; Thread < MaxThread; Thread++)
      {
      DebugThread(Thread);
      }
    }
} /* DebugThreads() */

/*********************************************
 SaveStatus(): Write the current status to the DB.
 *********************************************/
void	SaveStatus	()
{
  static time_t LastCheck = 0;
  static time_t LastSave = 0;
  static time_t LastReconnect = 0;
  time_t Now;
  int Thread;

  Now = time(NULL);

  /* Refresh DB connection every hour */
  if ((Now - LastReconnect) > 60*60)
    {
    DBLockReconnect();
    LastReconnect = Now;
    }

  /* Delete old schedule entries every 10 minutes. */
  if ((Now - LastCheck) > 60*10)
    {
    DBCheckStatus();
    LastCheck = Now;
    }

  /* Save status every 10 seconds */
  if ((Now - LastSave) >= 10)
    {
    CheckPids(); /* look for defunct children */
    DBSaveSchedulerStatus(-1,"RUNNING");
    for(Thread=0; Thread < MaxThread; Thread++)
      {
      DBSaveSchedulerStatus(Thread,StatusName[CM[Thread].Status]);
      }
    DBSaveJobStatus(-1,-1);
    LastSave = Now;
    }
} /* SaveStatus() */

/************************************************************************/
/************************************************************************/
/** Signals Functions ***************************************************/
/************************************************************************/
/************************************************************************/

/********************************************
 Log2Syslog(): Dump the contents of the log file
 to syslog().  This rotates FILE *Log.
 ********************************************/
void	Log2Syslog	()
{
  char Line[1024];
  int i,c;
  FILE *Fin;

  /* Check for new Log */
  if (Log==NULL)
    {
    Log = tmpfile();
    return;
    }

  /* Check for empty Log */
  if (ftell(Log) == 0) return; 

  /* There are messages! */
  /* Rotate the message holder */
  Fin = Log;
  Log = tmpfile();

  /* Dump the file contents */
  rewind(Fin);
  while(!feof(Fin))
    {
    memset(Line,'\0',sizeof(Line));
    c = fgetc(Fin);
    for(i=0; (i<1023) && (c > 0) && (c != '\n'); i++)
      {
      Line[i] = c;
      c = fgetc(Fin);
      }
    if (i > 0) syslog(LOG_INFO,"%s",Line);
    }
  fclose(Fin);
} /* Log2Syslog() */

/********************************************
 ChangeStatus(): Change a thread's state.
 ********************************************/
void	ChangeStatus	(int Thread, int NewState)
{
  time_t Now;
  Now = time(NULL);
  if ((NewState < ST_SPAWNED) && (CM[Thread].Status >= ST_SPAWNED))
	{ SetHostRun(CM[Thread].HostId,-1); }
  else if ((NewState >= ST_SPAWNED) && (CM[Thread].Status < ST_SPAWNED))
	{ SetHostRun(CM[Thread].HostId,1); }
  CM[Thread].StatusLast = CM[Thread].Status;
  CM[Thread].Status = NewState;
  CM[Thread].StatusLastDuration = Now - CM[Thread].StatusTime;
  CM[Thread].StatusTime = Now;
  CM[Thread].Heartbeat = Now;
  if (NewState == ST_RUNNING) CM[Thread].ItemsProcessed=0; /* reset count */
  ShowStates(Thread);
} /* ChangeStatus() */

/********************************************
 KillChild(): Forcefully kill a child.
 Returns: 1 if the child was active, 0 if not.
 ********************************************/
int	KillChild	(int Thread)
{
  int ActiveThread=0;

  if (CM[Thread].Status > ST_READY)
    {
    /* close the DB */
    if (CM[Thread].IsDB) DBremoveChild(Thread,1,"Scheduler terminated");
    CM[Thread].IsDB=0;
    }

  if (CM[Thread].Status > ST_FREE)
    {
    ActiveThread++;

    /** Close structure FIRST since SIGCHLD may be received */
    ChangeStatus(Thread,ST_FREEING);
    CheckClose(CM[Thread].ChildStdin);
    CheckClose(CM[Thread].ChildStdinRev);
    /* kill the children! kill! kill! */
    if (CM[Thread].ChildPid > 0)
	{
	kill(CM[Thread].ChildPid,SIGKILL);
	}

#if 0
    /* Allow up to 10 lines from the child */
    {
    int Count,rc;
    for(Count=0, rc=1; (Count < 10) && (rc > 0); Count++)
	{
	rc = ReadChild(Thread);
	}
    }
#else
    /** Give it up to ten seconds to flush I/O **/
    alarm(10);
    while(ReadChild(Thread) > 0)  ;
    alarm(0);
#endif

    /** Close remaining structures **/
    CheckClose(CM[Thread].ChildStdout);
    CheckClose(CM[Thread].ChildStdoutRev);
    if (CM[Thread].DB) DBclose(CM[Thread].DB);
    CM[Thread].DB = NULL;
    CM[Thread].ChildStdin = 0;
    CM[Thread].ChildStdinRev = 0;
    CM[Thread].ChildStdout = 0;
    CM[Thread].ChildStdoutRev = 0;
    ChangeStatus(Thread,ST_FREE);
    }
  return(ActiveThread);
} /* KillChild() */

/********************************************
 ParentSig(): Handle signals to parent.
 ********************************************/
void	ParentSig	(int Signo, siginfo_t *Info, void *Context)
{
  int Thread;
  char Ctime[MAXCTIME];

  switch(Signo)
    {
    case SIGALRM: /* alarm went off */
	{
	time_t Now;
	Now = time(NULL);
	memset(Ctime,'\0',MAXCTIME);
	ctime_r(&Now,Ctime);
	printf("ALARM at %s",Ctime);
	}
	break;
    case SIGINT: /* kill all children and exit */
	if (Verbose) fprintf(Log,"Got slow death signal: %d\n",Signo);
	SLOWDEATH=1;
	break;
    case SIGTERM: /* kill all children and exit (default kill signal) */
    case SIGQUIT: /* kill all children and exit */
    case SIGKILL: /* kill all children and exit (cannot trap this! but fun to try) */
	if (Verbose) fprintf(Log,"Got signal %d\n",Signo);
	fclose(stdin);	/* no more input! */
	SLOWDEATH=1;
	signal(SIGCHLD,SIG_IGN); /* ignore screams of death */
	fprintf(Log,"Sending kill signal to all child processes.\n");
	for(Thread=0; (Thread < MaxThread); Thread++)
	  {
	  if (CM[Thread].ChildPid) kill(CM[Thread].ChildPid,SIGKILL);
	  }
	/** if all children are dead, then I'll exit through signal handler */
	DBclose(DB);
	fprintf(Log,"*** Scheduler completed (terminated by signal).\n");
	exit(0);
	break;
    case SIGHUP: /* Display stats */
	DebugThreads(3);
	break;
    case SIGUSR1: /* Display general stats */
	DebugThreads(1);
	break;
    case SIGUSR2: /* Display MSQ contents */
	DebugMSQ();
	break;
    case SIGSEGV: /* CRASH! */
	{
	time_t Now;
	Now = time(NULL);
	memset(Ctime,'\0',MAXCTIME);
	ctime_r(&Now,Ctime);
	fprintf(Log,"CRASH DEBUG! %s",Ctime);
	fprintf(Log,"  DEBUG: %s :: %d\n",Debug.File,Debug.Line);
	DebugThreads(3);
	fprintf(Log,"CRASH DEAD! %s",Ctime);
	raise(SIGABRT); /* generate a core dump */
	DBclose(DB);
	fprintf(Log,"*** Scheduler completed.\n");
	Log2Syslog();
	exit(-1);
	}
	break;
    default:
	if (Verbose) fprintf(Log,"Got unknown signal: %d\n",Signo);
	break;
    }
} /* ParentSig() */

/********************************************
 CheckPids(): Check if any children are dead.
 ********************************************/
void	CheckPids	()
{
  int Thread;
  /***************************************
   waitid() is Linux only.
   waitpid() is found on Linux and BSD.
   ***************************************/
#define USE_WAITID 0
#if USE_WAITID
  int rc;
  siginfo_t Info;
#else
  int Status, Pid;
#endif

  /* loop until there are no processes */
  do
    {
#if USE_WAITID
    memset(&Info,0,sizeof(siginfo_t));
    rc = waitid(P_ALL,P_ALL,&Info,WNOHANG|WEXITED);
    if ((rc != 0) || (Info.si_pid == 0))
	{
	/* distinguish WNOHANG from error: Info.si_pid == 0 if WNOHANG */
	return;
	}
#else
    Pid = waitpid(-1, &Status, WNOHANG);
    if ((Pid == 0) || (Pid == -1))
	{
	/* When options contain WNOHANG:
	   exit status of 0 means no children
	   exit status of -1 means no child terminated */
	return;
	}
#endif

    /* Here: a signal was read */
    /* Find the thread */
    for(Thread=0; (Thread < MaxThread) &&
#if USE_WAITID
        (CM[Thread].ChildPid != Info.si_pid);
#else
        (CM[Thread].ChildPid != Pid);
#endif
	Thread++)
	;
    /* If it is a known thread, then process it */
    if (Thread < MaxThread) /* if it found a child */
	{
	if (CM[Thread].Status != ST_FREEING)
		{
#if USE_WAITID
		fprintf(Log,"ERROR: Child[%d] died prematurely (was state %s, signal was %d)\n",Thread,StatusName[CM[Thread].Status],Info.si_signo);
#else
		fprintf(Log,"ERROR: Child[%d] died prematurely (was state %s, signal was %d)\n",Thread,StatusName[CM[Thread].Status],WTERMSIG(Status));
#endif
		DebugThread(Thread);
		}
	if (Verbose)
		{
		fprintf(stderr,"Child[%d] (pid=%d) found dead\n",Thread,CM[Thread].ChildPid);
		}
	if (CM[Thread].Status==ST_RUNNING)
		{
		/* error handled */
		if (CM[Thread].DBJobKey > 0)
			{
			DBUpdateJob(CM[Thread].DBJobKey,3,"Failed: Agent terminated prematurely");
			}
		}
	/* ST_FREEING is an intentional and expected death */
	if (CM[Thread].Status==ST_FREEING) { CM[Thread].SpawnCount=0; }

	/** Close structure FIRST since signals may be received */
	CheckClose(CM[Thread].ChildStdin);
	CheckClose(CM[Thread].ChildStdinRev);
	if (CM[Thread].Status != ST_FREEING)
	  {
	  ChangeStatus(Thread,ST_FREEING);

	  /** Process any pending I/O data **/
	  alarm(10);
	  while(ReadChild(Thread) > 0)  ;
	  alarm(0);
	  }

	/** Close remaining structures **/
	CheckClose(CM[Thread].ChildStdout);
	CheckClose(CM[Thread].ChildStdoutRev);

	if (CM[Thread].IsDB && (CM[Thread].Status >= ST_RUNNING))
		{
		DBremoveChild(Thread,2,"Process died");
		}
	DBclose(CM[Thread].DB);
	CM[Thread].DB = NULL;
	CM[Thread].ChildStdin = 0;
	CM[Thread].ChildStdinRev = 0;
	CM[Thread].ChildStdout = 0;
	CM[Thread].ChildStdoutRev = 0;
	CM[Thread].ChildPid = 0;
	ChangeStatus(Thread,ST_FREE);
	CM[Thread].IsDB=0; /* need to remove child */
	} /*  matched thread */
    else /* if unknown process sent signal */
	{
#if USE_WAITID
	if (Info.si_signo != SIGCHLD) /* ignore unknown children */
	  {
	  fprintf(Log,"INFO: Received signal %d from unknown (old) process-id %d; child returned status %x\n",
		Info.si_signo, Info.si_pid, Info.si_status);
	  }
#else
	if (WTERMSIG(Status) != SIGCHLD) /* ignore unknown children */
	  {
	  fprintf(Log,"INFO: Received signal %d from unknown (old) process-id %d; child returned status %x\n",
		WTERMSIG(Status), Pid, Status);
	  }
#endif
	}
    } /* while loop */
#if USE_WAITID
    while(rc==0); /* while !rc == while got a signal */
#else
    while(Pid > 0); /* while "Pid > 0"  == while got a signal */
#endif

  /* Dump any pending messages */
  Log2Syslog();
} /* CheckPids() */

/********************************************
 HandleSig(): Handle signals from children.
 ********************************************/
void	HandleSig	(int Signo, siginfo_t *Info, void *Context)
{
  int Thread;
  time_t Now;

  /* Find the child... */
  Now = time(NULL);
  Thread=0;
  while((Thread < MaxThread) && (Info->si_pid != CM[Thread].ChildPid))
	Thread++;

  if (Thread >= MaxThread)
    {
    /* Huh?  A signal from a non-child?  Forget it! */
    /** NOTE: Some children send a sigchld way too late.  Ignore sigchld. **/
    if (Signo != SIGCHLD)
      {
      fprintf(Log,"INFO: Signal from unknown process: pid=%d sig=%d\n",
	Info->si_pid,Signo);
      }
    CheckPids();
    return;
    }

  /* got a good signal for a known child */
  switch(Signo)
    {
    case SIGCHLD:
	/* we could decide to respawn the process... */
	if (Verbose) fprintf(stderr,"Child[%d] (pid=%d) died?\n",Thread,Info->si_pid);
	/***
	 Problem: SIGCHLD indicates that "one or more" children died.
	 Solution: Check for any other dead children.
	 Source: http://www.xs4all.nl/~evbergen/unix-signals.html
	 ***/
	CheckPids();
	break;
    default:
	fprintf(Log,"Child[%d] did something unexpected: signal=%d, state was %s(%d)\n",
		Thread,Signo,
		StatusName[CM[Thread].Status],CM[Thread].Status);
	KillChild(Thread);
	CheckPids();
	break;
    } /* switch signal */
} /* HandleSig() */


/************************************************************************/
/************************************************************************/
/** Spawning Functions **************************************************/
/************************************************************************/
/************************************************************************/

/********************************************
 MyExec(): Create a list of parms and then
 run as an exec.
 Each exec gets a unique string in the environment:
 $THREAD_UNIQUE.
 This is unique for this thread, but if the thread dies
 then it could be used by another thread.
 ********************************************/
void	MyExec	(int Thread, char *Cmd)
{
  int i;
  int InQuote=0;
  int Quote1=0, Quote2=0; /* counters for single and double quotes */
  int IsSpace=0;
  char *Arg[MAXARG+1];
  int a;
  int CmdLen;
  char ThreadUnique[100];
  int TUval, TUdigit; /* thread unique value */

  /* set the thread unique value (kinda like base-64 encoding) */
  /* max combination is 99^64 (a huge value) */
  memset(ThreadUnique,'\0',sizeof(ThreadUnique));
  TUval = Thread;
  for(i=0; (i<99) && (TUval >= 0); i++)
    {
    TUdigit = TUval % 64;
    TUval = TUval / 64; /* ignore remainder */
    if (TUval == 0) TUval = -1; /* break out */
    if (TUdigit < 10) ThreadUnique[i] = TUdigit + '0';
    else if (TUdigit < 36) ThreadUnique[i] = TUdigit-10 + 'A';
    else if (TUdigit < 62) ThreadUnique[i] = TUdigit-36 + 'A';
    else if (TUdigit < 63) ThreadUnique[i] = '-';
    else ThreadUnique[i] = '_';
    }
  setenv("THREAD_UNIQUE",ThreadUnique,1);

  CmdLen = strlen(Cmd);
  for(i=0; (i < CmdLen) && isspace(Cmd[i]); i++)
  	/* skip initial spaces */
	;

  IsSpace=1;
  a=0;
  for( ; (i < CmdLen) && (a < MAXARG); i++)
    {
    if (InQuote)
      {
      if ((InQuote == '"') && (Cmd[i]=='\\')) i++; /* skip single quote */
      else if (Cmd[i]==InQuote) InQuote=0; /* end quote */
      }
    else
      {
      /* not in a quote */
      if (Cmd[i]==' ')
        {
	/* is a separator */
	if (!IsSpace) { Cmd[i]='\0'; }
	IsSpace=1;
	}
      else
        {
	/* not a space and not a quote */
	if (IsSpace)
	  {
	  IsSpace=0;
	  Arg[a++] = Cmd+i;
	  }
        if (Cmd[i]=='\\') i++;	/* single letter quote */
        else if (Cmd[i]=='"') { InQuote=Cmd[i]; }
        else if (Cmd[i]=='\'') { InQuote=Cmd[i]; }
	}
      } /* else */
    } /* for each character */
  Arg[a++] = NULL;

  /* check for wrapping quotes */
  for(a=0; Arg[a] != NULL; a++)
    {
    Quote1=0;
    Quote2=0;
    for(i=0; Arg[a][i] != '\0'; i++)
      {
      if (Arg[a][i]=='\'') Quote1++;
      else if (Arg[a][i]=='"') Quote2++;
      }
    if ((Quote1==2) && (Arg[a][0]=='\'') && (Arg[a][i-1]=='\''))
	{
	Arg[a][i-1]='\0';
	Arg[a]++;
	}
    else if ((Quote2==2) && (Arg[a][0]=='"') && (Arg[a][i-1]=='"'))
	{
	Arg[a][i-1]='\0';
	Arg[a]++;
	}
    }
  a++;	/* set a to one past null */

  /* debug */
  if (Verbose)
    {
    fprintf(Log,"Max Args = %d\n",a);
    for(i=0; i<a; i++)
      {
      fprintf(Log,"Arg[%d] = '%s'\n",i,Arg[i]);
      }
    }
  execv(Arg[0],Arg);

  /* should never get here */
  fprintf(Log,"Exec failed: %s\n",Cmd);
  perror("Exec failure reason");
  DBclose(DB);
  exit(1);
} /* MyExec() */

/********************************************
 SpawnEngine(): Make dead engines come alive!
 Returns: Number of spawned threads.
 ********************************************/
int	SpawnEngine	(int Thread)
{
  int Pid;
  time_t SpawnTime, NowTime;
  int p2c[2];
  int c2p[2];

  /* SpawnTime = minimum value for rechecking a failure */
  NowTime = time(NULL);
  SpawnTime = NowTime - RespawnInterval;

  /* if it is dead but spawning too fast, then make it fail */
  if ((CM[Thread].Status == ST_FREE) &&
	(CM[Thread].SpawnCount > RespawnCount))
	{
	fprintf(Log,"*** Child[%d] spawning too fast (%d in %d seconds)\n",
		Thread,
		CM[Thread].SpawnCount,(int)(NowTime-CM[Thread].SpawnTime));
	ChangeStatus(Thread,ST_FAIL);
	return(0);	/* skip it */
	}

  /* check if a failure can be changed to a spawn */
  if ((CM[Thread].Status == ST_FAIL) && (CM[Thread].SpawnTime < SpawnTime))
	{
	/* enough ellapsed time; let it respawn */
	ChangeStatus(Thread,ST_FREE);
	CM[Thread].SpawnCount = 0;
	}

  /* only spawn things that can spawn */
  if (CM[Thread].Status != ST_FREE) return(0);

#if 0
  /* only spawn things that have been dead a while */
  /** Without this pause, there is a race condition where a
      child dies and is respawned within microseconds. **/
  if (CM[Thread].StatusTime + MINFREETIME >= NowTime) return(0);
#endif

  /* track spawning */
  ChangeStatus(Thread,ST_PREP);
  if (CM[Thread].SpawnTime < SpawnTime)
	{
	CM[Thread].SpawnCount=0;
	CM[Thread].SpawnTime = NowTime;
	}
  else if (CM[Thread].SpawnCount == 0) CM[Thread].SpawnTime = NowTime;
  CM[Thread].SpawnCount++;

  /* create communication pipes */
  pipe(p2c);	/* parent to child pipe */
  pipe(c2p);	/* child to parent pipe */

  /* Set parent-to-child pipes to be blocking */
  if (fcntl(p2c[0],F_SETFL, fcntl(p2c[0],F_GETFL) ) != 0)
    {
    perror("FATAL: fcntl(p2c[0]) failed: ");
    fprintf(Log,"FATAL: fcntl(p2c[0]) failed.\n");
    exit(-1);
    }
  if (fcntl(p2c[1],F_SETFL, fcntl(p2c[1],F_GETFL) ) != 0)
    {
    perror("FATAL: fcntl(p2c[1]) failed: ");
    fprintf(Log,"FATAL: fcntl(p2c[1]) failed.\n");
    exit(-1);
    }
  /* Set child-to-parent pipes to be non-blocking */
  if (fcntl(c2p[0],F_SETFL, fcntl(c2p[0],F_GETFL) | O_NONBLOCK) != 0)
    {
    perror("FATAL: fcntl(c2p[0]) failed: ");
    fprintf(Log,"FATAL: fcntl(c2p[0]) failed.\n");
    exit(-1);
    }
  if (fcntl(c2p[1],F_SETFL, fcntl(c2p[1],F_GETFL) | O_NONBLOCK) != 0)
    {
    perror("FATAL: fcntl(c2p[1]) failed: ");
    fprintf(Log,"FATAL: fcntl(c2p[1]) failed.\n");
    exit(-1);
    }

  /* if parent writes to p2c[1] then child sees it on p2c[0] */
  /* if child writes to c2p[1] then parent sees it on c2p[0] */
  CM[Thread].ChildStdin = p2c[1];
  CM[Thread].ChildStdinRev = p2c[0];
  CM[Thread].ChildStdout = c2p[0];
  CM[Thread].ChildStdoutRev = c2p[1];
  CM[Thread].IsDB = 0;
  CM[Thread].DBJobKey = 0;
  CM[Thread].DBMSQrow = 0;

  /* spawn the process */
  Pid = fork();
  if (Pid==0) /* if child */
	{
	int T;
	/*** Child processing! ***/
	/* change io */
	dup2(CM[Thread].ChildStdinRev,0); /* replace stdin */
	dup2(CM[Thread].ChildStdoutRev,1); /* replace stdout */

	/* close all unused I/O */
	/** This allows the parent to close stdin for a child **/
	CheckClose(CM[Thread].ChildStdin);
	CheckClose(CM[Thread].ChildStdout);
	for(T=0; T<MaxThread; T++)
	  {
	  if ((T != Thread) && (CM[Thread].Status > ST_FREE))
	    {
	    CheckClose(CM[T].ChildStdin);
	    CheckClose(CM[T].ChildStdinRev);
	    CheckClose(CM[T].ChildStdout);
	    CheckClose(CM[T].ChildStdoutRev);
	    }
	  }
	/* Run command and return result to parent. */
	MyExec(Thread,CM[Thread].Command);
	/* should never get here! */
	DBclose(DB);
	exit(1);
	}
  else if (Pid == -1)
	{
	perror("FATAL: fork failed: ");
	DBclose(DB);
	fprintf(Log,"FATAL: fork failed.\n");
	Log2Syslog();
	exit(-1);
	}
  else
	{
	/*** Parent processing! ***/
	if (Verbose) fprintf(Log,"Child[%d] (pid=%d) spawned\n",Thread,Pid);
	CM[Thread].ChildPid = Pid;
	ChangeStatus(Thread,ST_SPAWNED);
	}

  /* Wait for child to start up */
  SpawnTime = time(NULL);
  NowTime = SpawnTime;
  /* give it 1 minute to get ready, otherwise assume it failed and hung */
  while((CM[Thread].Status == ST_SPAWNED) && (NowTime <= SpawnTime + 60))
    {
    SelectAnyData(0,0);
    NowTime = time(NULL);
    }
  if (CM[Thread].Status != ST_READY)
	{
	/* assume the child failed to spawn */
	fprintf(Log,"ERROR: Child[%d] failed to spawn after %d seconds\n",Thread,(int)(NowTime-SpawnTime));
	fprintf(Log,"ERROR: Child[%d] failed command was: '%s'\n",Thread,CM[Thread].Command);
	ShowStates(Thread);
	KillChild(Thread);
	return(0);
	}
  Log2Syslog();
  return(1);
} /* SpawnEngine() */

/********************************************
 InitEngines(): Create all the engines.
 This will set MaxThread.
 ********************************************/
void	InitEngines	(char *ConfigName)
{
  int Thread;
  FILE *Fin;
  char Cmd[MAXCMD];
  char *Arg;

  MaxThread=0;
  Thread=0;
  memset(CM,0,sizeof(CM)); /* clear management array */

  Fin = fopen(ConfigName,"rb");
  if (!Fin)
    {
    fprintf(Log,"FATAL: Unable to open configuration file: '%s'\n",
	ConfigName);
    DBclose(DB);
    Log2Syslog();
    exit(-1);
    }

  /* load in configuration file */
  while(!feof(Fin) && ReadCmd(Fin,Cmd,MAXCMD))
    {
    if (Cmd[0]=='#') continue;	/* skip comments */
    if (Cmd[0]==';') continue;	/* skip comments */
    if (Cmd[0]=='\0') continue;	/* skip blanks */
    /* a vertical bar separates attributes from command to run */
    if (Cmd[0]=='%') Arg=strchr(Cmd,' ');
    else Arg=strchr(Cmd,'|');
    if (!Arg)
	{
	fprintf(Log,"FATAL: Bad command '%s' in '%s'\n",Cmd,ConfigName);
	DBclose(DB);
	Log2Syslog();
	exit(-1);
	}
    if (Arg) { Arg[0]='\0'; Arg++; } /* skip space */
    while(Arg && isspace(Arg[0])) Arg++;

    /******************************************************/
    if (Verbose) fprintf(Log,"Config:  Cmd='%s'  Arg='%s'\n",Cmd,Arg);
    /* process Parent commands */
    if (!strcmp(Cmd,"%Verbose"))
	{
	int v;
	v = atoi(Arg);
	if (v > Verbose) Verbose=v;
	continue;
	}
    else if (!strcmp(Cmd,"%Host"))
	{
	/* line defines hostname, max processes, max urgent */
	int MaxRun=-1;	/* default: no limits */
	int MaxUrg=1;	/* default: 1 urgent */
	int i;
	for(i=0; (Arg[i] != '\0') && !isspace(Arg[i]); i++)	;
	/* found end of hostlist */
	/** next comes max running **/
	if (Arg[i] != '\0')
	  {
	  Arg[i]='\0';
	  for(i++; isspace(Arg[i]); i++)	; /* skip spaces */
	  MaxRun = atoi(Arg+i);
	  }
	/** next comes max urgent **/
	for( ; (Arg[i] != '\0') && !isspace(Arg[i]); i++)	;
	if (Arg[i] != '\0')
	  {
	  for(i++; isspace(Arg[i]); i++)	; /* skip spaces */
	  MaxUrg = atoi(Arg+i);
	  }
	HostAdd(Arg,MaxRun,MaxUrg);
	continue;
	}
    /* must be a child -- load it! */
    else if (Thread < MAXCHILD)
	{
	strncpy(CM[Thread].Attr,Cmd,MAXATTR);
	strcpy(CM[Thread].Command,Arg);
	CM[Thread].HostId = GetHostFromAttr(CM[Thread].Attr);
	ChangeStatus(Thread,ST_FREE); /* not spawned yet */
	CM[Thread].DBagent = -1; /* not yet set (will be set during the first DB status update) */
	Thread++;
	}
    } /* while reading config file */
  fclose(Fin);

  /* save results */
  MaxThread = Thread;
  if (MaxThread <= 0)
    {
    fprintf(Log,"FATAL: No agents found in the configuration file: '%s'\n",
	ConfigName);
    DBclose(DB);
    Log2Syslog();
    exit(-1);
    }
} /* InitEngines() */

/********************************************
 TestEngines(): Start and kill each engine.
 Return the number of failures.
 If no failures, then return 0.
 ********************************************/
int	TestEngines	()
{
  int Failures=0;
  int Thread;
  for(Thread=0; Thread < MaxThread; Thread++)
    {
    if (!SpawnEngine(Thread))
      {
      fprintf(Log,"FAILED: Could not run thread %d: %s\n",
        Thread,CM[Thread].Command);
      Failures++;
      }
    KillChild(Thread);
    }
  Log2Syslog();
  return(Failures);
} /* TestEngines() */

/************************************************************************/
/************************************************************************/
/** Command Functions ***************************************************/
/************************************************************************/
/************************************************************************/

/********************************************
 ProcessCommand(): Given a command, handle it.
 Returns: 1 on success, 0 on failure.
 ********************************************/
int	ProcessCommand	(int Job_ID, char *Cmd)
{
  int rc=0;
  int Thread;

  DBUpdateJob(Job_ID,0,"Working");
  if (!strcmp(Cmd,"shutdown")) { SLOWDEATH=1; rc=1; }
  else if (!strcmp(Cmd,"shutdown now"))
	{
	SLOWDEATH=1;
	signal(SIGCHLD,SIG_IGN); /* ignore screams of death */
	for(Thread=0; (Thread < MaxThread); Thread++)
	  {
	  KillChild(Thread);
	  }
	DBUpdateJob(Job_ID,1,"Done");
	fprintf(Log,"Command '%s' Done.\n",Cmd);
	DBclose(DB);
	fprintf(Log,"*** Scheduler completed by shutdown command.\n");
	Log2Syslog();
	exit(0);
	}
  else if (!strncmp(Cmd,"killjob ",8))
	{
	int JobId;
	JobId = atoi(Cmd+8);
	/* For all threads with this job id (should be <= 1) */
	for(Thread=0; Thread < MaxThread; Thread++)
	  {
	  if ((CM[Thread].Status==ST_RUNNING)&&(CM[Thread].DBJobKey == JobId))
	    {
	    KillChild(Thread);
	    DBUpdateJob(JobId,3,"Killed");
	    rc=1;
	    }
	  }
	}
  if (rc) DBUpdateJob(Job_ID,1,"Done");
  else DBUpdateJob(Job_ID,3,"Failed");
  return(rc);
} /* ProcessCommand() */

