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

********************
 Scheduler is based off of a proof-of-concept spawning system
 called "spawner".  The basic idea: load the tasks to process and
 spawn them off as individual processes.  The scheduler is single-threaded,
 but the spawned processes make processing happen in parallel.

 About the spawner process...
 Originally I used a script to spawn processes, but I found a bug.
 Bash has a problem: spawned processes have their return code
 generated before output handles are flushed.
 This means, shell "wait" may return before data is written
 by stdout/stderr.  This leads to a tight race condition where
 data used by one shell script step is not yet available.

 Example bash race condition:
 =====
 #!/bin/sh
 # Sometimes the wait completes before the contents of "file" is written.
 export MaxThread=2

 # Repeat the test 10 times -- some should fail (if no fail, re-run this script)
 for loop in 1 2 3 4 5 6 7 8 9 10 ; do
 # Initialize
 rm -f file* > /dev/null 2>&1
 Thread=0

 # The loop
 echo test | while read i ; do
  (date >> file$Thread) &
  ((Thread=$Thread+1))
  if [ "$Thread" -ge "$MaxThread" ] ; then
	wait
	Thread=0
  fi
 done
 wait  # ensure that all processes finish!
 # sync # enable a call to sync after the wait in order to bypass the problem

 cat file[0-9]* > file
 # display file contents
 echo "File contains:"
 cat file
 echo "EOF"
 done # end of loop
 =====

 As a workaround: "spawner".
 This program took a command-line that says the number of processes to
 spawn at any given time.
 Then it reads commands from stdin -- one command per line.
 All output is sent to stdout WHEN THE PROCESS FINISHES!
 All error is sent to stderr AS IT HAPPENS!

 Spawner stops:
   - When there is nothing left to stdin.
   - When any application ends with a non-zero return code.
 Spawner returns:
   - 0 if all processes ended with zero, or
   - Return code from first failed process.

 How spawner became the scheduler:
   - Data can come from stdin (for testing), but usually comes from the
     database job queue.
   - The jobs to spawn come from a configuration file.  The data only
     identifies the type of job and the parameters for it.
 *******************************************************/

#include <stdlib.h>
#include <stdio.h>
#include <string.h>
#include <ctype.h>
/* for user and group permission */
#include <sys/types.h>
#include <grp.h>
#include <pwd.h>
#include <syslog.h>

#include "scheduler.h"
#include "spawn.h"
#include "sockets.h"
#include "agents.h"
#include "hosts.h"
#include "dbq.h"
#include "dbstatus.h"
#include "dberror.h"
#include "selftest.h"

int Verbose=0;
int ShowState=1;
int UseStdin=0;
int IgnoreHost=0;
int SLOWDEATH=0;	/* exit politely: complete current jobs, then exit */
void *DB=NULL;	/* the DB queue */

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
char BuildVersion[]="Unknown\n";
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
  int KillSchedulers=0;
  char *LogFile=NULL;
  int Test=0; /* 1=test and continue, 2=test and quit */

  openlog("fossology",LOG_PERROR|LOG_PID,LOG_DAEMON);
  /* Prepare system logging */
  Log2Syslog();

  /* check args */
  while((c = getopt(argc,argv,"dkHiIL:vqRtT")) != -1)
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
	  syslog(LOG_CRIT,"FATAL: Unable to connect to database\n");
	  exit(-1);
	  }
	/* Nothing to initialize */
	DBclose(DB);
	closelog();
	return(0);
      case 'I':
	UseStdin=1;
	break;
      case 'k': /* kill the scheduler */
	KillSchedulers=1;
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

  syslog(LOG_INFO,"*** Scheduler started\n");

  /* All config files require group access.  Validate access. */
  if (getuid() == 0)
    {
    /* Don't run as root unless I'm trying to kill schedulers. */
    /* It is alright for root to send kill signals to schedulers. */
    /* Without this condition, Root would become PROJECTUSER and would not
       be able to kill schedulers started by other users. */
    struct group *G;
    struct passwd *P;
    if (!KillSchedulers)
      {
      G = getgrnam(PROJECTGROUP);
      if (!G)
	{
	syslog(LOG_CRIT,"FATAL: Group '%s' not found.  Aborting.\n",PROJECTGROUP);
	DBclose(DB);
	exit(-1);
	}
      setgroups(1,&(G->gr_gid));
      if ((setgid(G->gr_gid) != 0) || (setegid(G->gr_gid) != 0))
	{
	syslog(LOG_CRIT,"FATAL: Cannot run as group '%s'.  Aborting.\n",PROJECTGROUP);
	DBclose(DB);
	exit(-1);
	}
      /* Don't run as root */
      P = getpwnam(PROJECTUSER);
      if (!P)
	{
	syslog(LOG_CRIT,"FATAL: User '%s' not found.  Will not run as root.  Aborting.\n",PROJECTUSER);
	DBclose(DB);
	exit(-1);
	}
      if ((setuid(P->pw_uid) != 0) || (seteuid(P->pw_uid) != 0))
	{
	syslog(LOG_CRIT,"FATAL: Cannot run as user '%s'.  Will not run as root.  Aborting.\n",PROJECTUSER);
	DBclose(DB);
	exit(-1);
	}
      } /* if !!KillScheduler */
    }
  else
    {
    /* Not running as root.  Am I in the right group? */
    struct passwd *P;
    struct group *G;
    gid_t *Groups;
    int MaxGroup=0;
    int Match=0;
    int i;

    G = getgrnam(PROJECTGROUP); /* the group we want to match */
    /* Get list of groups for this user. */
    P = getpwuid(getuid());
    getgrouplist(P->pw_name,getgid(),NULL,&MaxGroup);
    Groups = (gid_t *)malloc(MaxGroup*sizeof(gid_t));
    if (!Groups)
      {
      syslog(LOG_CRIT,"FATAL: Unable to allocate memory.\n");
      DBclose(DB);
      exit(-1);
      }
    getgroups(MaxGroup,Groups);
    /* Now, check if the group matches */
    for(i=0; i<MaxGroup; i++)
      {
      if (Groups[i] == G->gr_gid) Match=1;
      }
    free(Groups);
    if (!Match)
      {
      syslog(LOG_CRIT,"FATAL: You are not in group '%s'.  Aborting.\n",PROJECTGROUP);
      DBclose(DB);
      exit(-1);
      }
    } /* check group access */

  /* Become a daemon? (Not if I'm killing schedulers) */
  if (!KillSchedulers && RunAsDaemon)
    {
    /* do not close stdout/stderr when using a LogFile */
    daemon(0,(LogFile!=NULL));
    fclose(stdin);
    }

  /* Log to file? (Not if I'm killing schedulers) */
  if (!KillSchedulers && LogFile)
    {
    if (freopen(LogFile,"wb",stdout) == NULL)
      {
      syslog(LOG_CRIT,"FATAL: Unable to write to logfile '%s'\n",LogFile);
      DBclose(DB);
      exit(-1);
      }
    if ((dup2(fileno(stdout),fileno(stderr))) < 0)
      {
      syslog(LOG_CRIT,"FATAL: Unable to write to redirect stderr to log\n");
      DBclose(DB);
      exit(-1);
      }
    }

  /* init queue */
  DB = DBopen();
  if (!DB)
    {
    syslog(LOG_CRIT,"FATAL: Unable to connect to database\n");
    DBclose(DB);
    exit(-1);
    }

  DBSetHostname();

  /* Prepare for logging errors to DB */
  DBErrorInit();

  /* If we're killing schedulers... */
  if (KillSchedulers)
	{
	DBkillschedulers();
	DBclose(DB);
	syslog(LOG_INFO,"*** Scheduler completed\n");
	exit(0); /* kill me too! */
	}

  /* If we're resetting the queue */
  if (ResetQueue)
	{
	/* If someone has a start without an end, then it is a hung process */
	DBLockAccess(DB,"UPDATE jobqueue SET jq_starttime=null WHERE jq_endtime is NULL;");
	syslog(LOG_NOTICE,"Job queue reset.\n");
	}

  /* init storage */
  DBQinit();
  if (optind == argc) InitEngines(DEFAULTSETUP);
  else InitEngines(argv[optind]);

  /* Check for good agents */
  if (SelfTest())
    {
    syslog(LOG_CRIT,"FATAL: Inconsistent agent(s) detected.\n");
    DBclose(DB);
    exit(-1);
    }

  /* See if we're testing */
  if (Test)
    {
    rc = TestEngines();
    /* rc = number of engine failures */
    if (rc == 0) syslog(LOG_INFO,"STATUS: All scheduler jobs appear to be functional.\n");
    else syslog(LOG_INFO,"STATUS: %d agents failed.\n",rc);
    if ((Test > 1) || rc)
	{
	DBclose(DB);
	closelog();
	syslog(LOG_INFO,"*** Scheduler completed\n");
	return(rc);
	}
    }

  /* Check for competing schedulers */
  DBCheckSchedulerUnique();

  /* catch signals */
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
  if (sigaction(SIGHUP,&SigAct,NULL) != 0) perror("SIGHUP");
  if (sigaction(SIGUSR1,&SigAct,NULL) != 0) perror("SIGUSR1");
  if (sigaction(SIGUSR2,&SigAct,NULL) != 0) perror("SIGUSR2");
  if (sigaction(SIGALRM,&SigAct,NULL) != 0) perror("SIGALRM");
  signal(SIGALRM,SIG_IGN); /* ignore self-wakeups */

  /**************************************/
  /* while there are commands to run... */
  /**************************************/
  Thread=0;
  Fed=0;
  while(KeepRunning)
    {
    SaveStatus();
    Log2Syslog();

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
	if (Verbose) syslog(LOG_DEBUG,"Parent got command: %s\n",Input);
	Arg = strchr(Input,'|');
	if (!Arg)
		{
		syslog(LOG_ERR,"ERROR: Unknown command (len=%d) '%s'\n",Len,Input);
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
		if (Verbose) syslog(LOG_DEBUG,"(a) Feeding child[%d]: '%s'\n",Thread,Arg);
		memset(CM[Thread].Parm,'\0',MAXCMD);
		strcpy(CM[Thread].Parm,Arg);
		Input[Len++]='\n'; /* add a \n to end of Arg */
		write(CM[Thread].ChildStdin,Arg,strlen(Arg));
		Fed=1;
		}
	  /* Thread == -1 is a timeout -- retry the request */
	  else if (Thread <= -2)
		{
		syslog(LOG_ERR,"ERROR: No living engines for '%s'\n",Input);
		Fed=1;	/* skip this bad command */
		}
	  } /* while not Fed */
	} /* if processing stdin input */
    else
	{
	/* this will pause if it detects a fast loop */
	Fed = DBProcessQueue(DB);
	}
    if (Verbose) printf("Time: %d  Fed=%d\n",(int)time(NULL),Fed);
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
  if (Verbose) syslog(LOG_DEBUG,"Telling all children: No more food.\n");
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

  /* print statistics */
  if (DB) DBErrorClose();
  DBQclose();
  DBclose(DB);
  DebugThreads(1);
  Log2Syslog(); /* dump any final messages */
  syslog(LOG_INFO,"*** Scheduler completed\n");
  closelog();
  return(0);
} /* main() */

