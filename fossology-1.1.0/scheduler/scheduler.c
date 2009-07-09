/*******************************************************
 Scheduler: Spawn off processes in parallel, feed them data
 as they request it.

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
#include <string.h>
#include <ctype.h>
/* for user and group permission */
#include <sys/types.h>
#include <assert.h>

#include <libfossdb.h>

#include "scheduler.h"
#include "sched_utils.h"
#include "spawn.h"
#include "sockets.h"
#include "agents.h"
#include "hosts.h"
#include "dbq.h"
#include "dbstatus.h"
#include "dberror.h"
#include "selftest.h"
#include "logging.h"
#include "lockfs.h"

int Verbose=0;
int ShowState=1;
int UseStdin=0;
int IgnoreHost=0;
int SLOWDEATH=0;	/* exit politely: complete current jobs, then exit */
void *DB=NULL;	/* the DB queue */
char ProcessName[]="fossology-scheduler";

#ifndef DEFAULTSETUP
#define DEFAULTSETUP "Scheduler.conf"
#endif
#ifndef PROJECTUSER
#define PROJECTUSER "fossology"
#endif
#ifndef PROJECTGROUP
#define PROJECTGROUP "fossology"
#endif

#ifdef SVN_REV
char BuildVersion[]="Build version: " SVN_REV ".\n";
#else
char BuildVersion[]="Build version: Unknown\n";
#endif

/**********************************************
 Usage(): Display usage.
 **********************************************/
void	Usage	(char *Name)
{
  fprintf(stderr,"Usage: %s [options] [setup.conf] < 'type command'\n",Name);
  fprintf(stderr,"  -i :: Initialize the database, then exit.\n");
  fprintf(stderr,"  -k :: Kill all running schedulers (on this system)\n");
  fprintf(stderr,"        -k kills this process too.  All other options are ignored.\n");
  fprintf(stderr,"  -d :: Run as a daemon!  Still generates stdout and stderr\n");
  fprintf(stderr,"  -H :: Ignore hosts for host-specific agent requests\n");
  fprintf(stderr,"  -I :: Use stdin and queue (default: use queue only)\n");
  fprintf(stderr,"  -v :: verbose (-v -v = more verbose)\n");
  fprintf(stderr,"  -L log :: send stdout and stderr to log\n");
#if 0
  fprintf(stderr,"  -l :: tell the running scheduler to redo its log file (for log rotation)\n");
#endif
  fprintf(stderr,"  -q :: turn off show stages\n");
  fprintf(stderr,"  -R :: reset the job queue in case something was hung.\n");
  fprintf(stderr,"  -t :: test every agent to see if it runs, then quit.\n");
  fprintf(stderr,"  -T :: test every agent to see if it runs, then continue if no problems.\n");
  fprintf(stderr,"  setup.conf: defines each engine -- one 'type command' per line\n");
  fprintf(stderr,"    If setup.conf is not specified then %s is used.\n",DEFAULTSETUP);
  fprintf(stderr,"  stdin lists type+data, one per line.\n");
  fprintf(stderr,"  stdout comes from threads, non-interlaced and only when thread ends.\n");
  fprintf(stderr,"  stderr comes from threads, interlaced and immediate.\n");
  fprintf(stderr,"Each command is executed as a running engine.\n");
  fprintf(stderr,"Each stdin line is matched to a free engine of the same type.\n");
  fprintf(stderr,"If no engine is free, then it will pause until one is available.\n");
} /* Usage() */



/************************************************************************/
/************************************************************************/
/** Program *************************************************************/
/************************************************************************/
/************************************************************************/

int	main	(int argc, char *argv[])
{
  int Thread;
  int c;
  char Input[MAXCMD];
  char *Arg;
  struct sigaction SigAct;
  int Fed;
  int Len;
  int rc;
  int IsUrgent;
  int KeepRunning=1;
  int RunAsDaemon=0;
  int ResetQueue=0;
  int KillScheduler=0;
  int Test=0; /* 1=test and continue, 2=test and quit */
  pid_t Pid;

  /* check args */
  while((c = getopt(argc,argv,"dkHiIL:lvqRtT")) != -1)
    {
    switch(c)
      {
      case 'd':
	RunAsDaemon=1;
	break;
      case 'H':
	IgnoreHost=1;
	break;
      case 'i':
	DB = DBopen();
	if (!DB)
	  {
	  fprintf(stderr, "FATAL: Unable to connect to database\n");
	  exit(-1);
	  }
	/* Nothing to initialize */
	DBclose(DB);
	return(0);
      case 'I':
	UseStdin=1;
	break;
      case 'k': /* kill the scheduler */
         KillScheduler = 1;
      case 'l': /* tell the scheduler to redo logs */
	break;
      case 'L':
	LogFile=optarg;
	break;
      case 'q':
	ShowState=0;
	break;
      case 'R':
	ResetQueue=1;
	break;
      case 't':
	Test=2;
	break;
      case 'T':
	Test=1;
	break;
      case 'v':
	Verbose++;
	break;
      default:
	Usage(argv[0]);
	DBclose(DB);
	exit(-1);
      }
    }

  if ((optind != argc-1) && (optind != argc))
	{
	Usage(argv[0]);
	DBclose(DB);
	exit(-1);
	}

  /* set to PROJECTUSER and PROJECTGROUP */
  SetPuserPgrp(ProcessName);

  if (KillScheduler)
  {
	  DB = DBopen();
    if (!DB)
    {
	    fprintf(stderr, "FATAL: Unable to connect to database\n");
      exit(-1);
    }

    /* kill scheduler */
    LogPrint("Scheduler kill requested.  Killing scheduler.\n");
    StopScheduler(1);
    StopWatchdog();
    exit(0);
  }

  /* Become a daemon?  */
  if (RunAsDaemon)
  {
    /* do not close stdout/stderr when using a LogFile */
    rc = daemon(0,(LogFile!=NULL));
    fclose(stdin);
  }

  /* must be after uid is set since this might create the log file */
  LogPrint("Scheduler started.  %s\n", BuildVersion);

  /* Lock the scheduler, so no other scheduler can run */
  rc = LockName(ProcessName);
  if (rc > 0)  /* already locked */
  {
    Pid = rc;
  }
  else if (rc == 0)  /* new lock */
  {
    Pid = LockGetPID(ProcessName);
    if (!Pid)
    { 
      LogPrint("*** %s lock error ***\n", ProcessName);
      exit(-1);
    }
  }
  else
  {
    LogPrint("*** %s lock failed. ***\n", ProcessName);
    exit(-1);
  }

  if (Verbose) 
    LogPrint("*** %s successfully locked, pid %d  ***\n", ProcessName, Pid);


  /**** From here on, I am the only scheduler running ****/


  /**************************************/
  /* catch signals */
  /**************************************/
  memset(&SigAct,0,sizeof(SigAct));
  SigAct.sa_sigaction = HandleSig;
  sigemptyset(&(SigAct.sa_mask));
  SigAct.sa_flags = SA_SIGINFO | SA_RESTART;
  sigaction(SIGCHLD,&SigAct,NULL);

  /* handle signals to the parent */
  SigAct.sa_flags = SA_SIGINFO | SA_RESTART;
  SigAct.sa_sigaction = ParentSig;
  if (sigaction(SIGSEGV,&SigAct,NULL) != 0) perror("SIGSEGV");
  if (sigaction(SIGQUIT,&SigAct,NULL) != 0) perror("SIGQUIT");
  if (sigaction(SIGTERM,&SigAct,NULL) != 0) perror("SIGTERM");
  if (sigaction(SIGINT,&SigAct,NULL) != 0) perror("SIGINT");
  if (sigaction(SIGUSR1,&SigAct,NULL) != 0) perror("SIGUSR1");
  if (sigaction(SIGUSR2,&SigAct,NULL) != 0) perror("SIGUSR2");
  if (sigaction(SIGALRM,&SigAct,NULL) != 0) perror("SIGALRM");
  /* ignore dead pipes when using -lpq -- see http://archives.postgresql.org/pgsql-bugs/2003-03/msg00118.php */
  signal(SIGPIPE,SIG_IGN);
  signal(SIGALRM,SIG_IGN); /* ignore self-wakeups */

  /* Prepare logging */
  LogPrint("*** Scheduler started, PID %d  ***\n", Pid);

  /* Log to file? (Not if I'm killing schedulers) */
  if ((dup2(fileno(stdout),fileno(stderr))) < 0)
      {
      LogPrint("FATAL: Unable to write to redirect stderr to log.  Exiting. \n");
      DBclose(DB);
      exit(-1);
      }

  /* init queue */
  DB = DBopen();
  if (!DB)
    {
    LogPrint("FATAL: Unable to connect to database.  Exiting. \n");
    exit(-1);
    }

  DBSetHostname();

  /* Prepare for logging errors to DB */
  DBErrorInit();

  /* If we're resetting the queue */
  if (ResetQueue)
	{
    /* If someone has a start without an end, then it is a hung process */
    DBLockAccess(DB,"UPDATE jobqueue SET jq_starttime=null WHERE jq_endtime is NULL;");
    LogPrint("Job queue reset.\n");
	}

  /* init storage */
  DBQinit();
  if (optind == argc) InitEngines(DEFAULTSETUP);
  else InitEngines(argv[optind]);

  /* Check for good agents */
  if (SelfTest())
    {
    LogPrint("FATAL: Self Test failed.  Inconsistent agent(s) detected.  Exiting. \n");
    DBclose(DB);
    exit(-1);
    }

  /* Check for competing schedulers */
  if (DBCheckSchedulerUnique())
  {
    /* Yes, a scheduler is running.  So log and exit.  */
    LogPrint("*** Scheduler not starting since another currently running.  ***\n");
    exit(0);
  }
  else
    DBCheckStatus();  /* clean scheduler_status and jobqueue tables */

  /* See if we're testing */
  if (Test)
  {
    rc = TestEngines();
    /* rc = number of engine failures */
    if (rc == 0) 
      LogPrint("STATUS: All scheduler agents are operational.\n");
    else 
      LogPrint("STATUS: %d agents failed to initialize.\n",rc);

    if ((Test > 1) || rc)
	  {
	    LogPrint("*** %d agent failures.  Scheduler exiting. \n", rc);
      /* clean up scheduler */
      StopScheduler(0);
      exit(0);
  	}
  }


  /**************************************/
  /* while there are commands to run... */
  /**************************************/
  Thread=0;
  Fed=0;
  while(KeepRunning)
    {
    SaveStatus();

    /* check for data to process */
    if (UseStdin)
	{
	rc = SelectAnyData(1,fileno(stdin));
	if (feof(stdin)) SLOWDEATH=1;
	}
    else rc = SelectAnyData(0,0);

    if (rc & 0x01) /* got stdin input to feed to a child */
	{
	IsUrgent=0;
	Len=ReadCmd(stdin,Input,MAXCMD);
	if (Len < 0) break; /* skip blank lines and EOF */
	if (Len == 0) continue; /* skip blank lines and EOF */
	if (Input[0]=='!')
	  {
	  IsUrgent=1;
	  Input[0]=' ';
	  }

	/* Got a command! */
	if (Verbose) LogPrint("Parent got command: %s\n",Input);
	Arg = strchr(Input,'|');
	if (!Arg)
		{
		LogPrint("ERROR: Unknown command (len=%d) '%s'\n",Len,Input);
		continue; /* skip unknown lines */
		}
	Arg[0]='\0'; Arg++;	/* skip space */
	while((Arg[0] != '\0') && isspace(Arg[0]))	Arg++;

	/* feed command to child */
	/* handle special commands (scheduler is the child) */
	Fed=SchedulerCommand(Input,Arg);

	/* if command needs child, find a child to feed it to */
	while(!Fed)
	  {
	  Thread = GetChild(Input,IsUrgent);
	  if (Thread < 0)
	    {
	    if (SelectAnyData(0,0) & 0x02) /* wait for a child to become ready */
		{
		Thread = GetChild(Input,IsUrgent);
		}
	    }
	  if (Thread >= 0)
		{
		if (CM[Thread].Status != ST_RUNNING)
		  {
		  ChangeStatus(Thread,ST_RUNNING);
		  }
		if (Verbose) LogPrint("(a) Feeding child[%d]: '%s'\n",Thread,Arg);
		memset(CM[Thread].Parm,'\0',MAXCMD);
		strcpy(CM[Thread].Parm,Arg);
		Input[Len++]='\n'; /* add a \n to end of Arg */
		write(CM[Thread].ChildStdin,Arg,strlen(Arg));
		Fed=1;
		}
	  /* Thread == -1 is a timeout -- retry the request */
	  else if (Thread <= -2)
		{
		LogPrint("ERROR: No living engines for '%s'\n",Input);
		Fed=1;	/* skip this bad command */
		}
	  } /* while not Fed */
	} /* if processing stdin input */
    else
	{
	/* this will pause if it detects a fast loop */
	Fed = DBProcessQueue(DB);
	}
//    if (Verbose) printf("Time: %d  Fed=%d\n",(int)time(NULL),Fed);
    if (Fed==0)
      {
      /* What happens if there was no job to process? */
      StaleChild();
      if (SLOWDEATH)
	{
	Thread=0;
	while((Thread < MaxThread) && (CM[Thread].Status != ST_RUNNING))
		Thread++;
	/* nothing running? Quit! */
	if (Thread >= MaxThread) KeepRunning=0;
	/* else, keep running */
	} /* if SLOWDEATH */
      }
    } /* while reading a command */

  /* if it gets here, then either all children are dead or there is
     no more input */

  /* wait for all children to finish */
  while(RunCount > 0)
    {
    SelectAnyData(0,0);
    }

  /* tell children "no more food" by closing stdin */
  if (Verbose) LogPrint("Telling all children: No more items to process.\n");
  for(Thread=0; Thread < MaxThread; Thread++)
    {
    if (CM[Thread].Status > ST_FREE) CheckClose(CM[Thread].ChildStdin);
    }

  /* At this point, there should be no children */
  SLOWDEATH=1;
  SelectAnyData(0,0);

  /* clean up: kill children (should be redundant) */
  signal(SIGCHLD,SIG_IGN); /* ignore screams of death */
  for(Thread=0; (Thread < MaxThread); Thread++)
    {
    if (CM[Thread].Status > ST_FREE)
      {
      /* kill the children! kill! kill! */
      if (CM[Thread].Status == ST_RUNNING)
	{
	if (CM[Thread].IsDB) DBremoveChild(Thread,1,"Scheduler ended");
	}
      CM[Thread].IsDB=0;
      CheckClose(CM[Thread].ChildStdin);
      CheckClose(CM[Thread].ChildStdinRev);
      CheckClose(CM[Thread].ChildStdout);
      CheckClose(CM[Thread].ChildStdoutRev);
      CM[Thread].ChildStdin = 0;
      CM[Thread].ChildStdinRev = 0;
      CM[Thread].ChildStdout = 0;
      CM[Thread].ChildStdoutRev = 0;
      ChangeStatus(Thread,ST_FREE);
      if (CM[Thread].ChildPid) kill(CM[Thread].ChildPid,SIGKILL);
      }
    }

  /* scheduler cleanup */
  if (DB) DBErrorClose();  /* currently a noop */
  DBQclose();

  /* cleanup scheduler */
  StopScheduler(0);

  /* print statistics */
  DebugThreads(1);
  LogPrint("*** Scheduler completed.  Exiting.  ***\n");
  return(0);
} /* main() */

