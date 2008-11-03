/*******************************************************
 spawn.h: Functions for spawning children.

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
#ifndef SPAWN_H
#define SPAWN_H

#include <stdlib.h>
#include <stdio.h>
#include <unistd.h>
#include <errno.h>
#include <time.h>
#include <sys/types.h>
#include <sys/wait.h>
#include <signal.h>

#include <libfossdb.h>

extern int MaxThread;	/* total number of spawned threads */

extern time_t	RespawnInterval;	/* 5 minutes */
extern time_t	RespawnCount;		/* Up to two respawns every RespawnInterval */
#define MINKILLTIME     20 /* seconds a child must be alive before killing */
#define MAXKILLTIME     (15*60) /* seconds a child must be idle before killing */
extern int	InSignalHandler;	/* don't uses syslog() when this is true! */
extern FILE	*MsgHolder;	/* Always valid when InSignalHandler */

/* Note: state used to be DEAD/DYING.  Changed to FREE/FREEING because
   the word "DEAD" scared people.  (I'm not kidding.) */
enum CHILD_STATUS
  {
  ST_FAIL = 0,	/* do not spawn */
  ST_FREE,	/* not spawned yet, no I/O allocated */
  ST_FREEING,	/* was spawned, now dying; no I/O allocated */
  ST_PREP,	/* preparing to be spawned (memory being allocated) */
  ST_SPAWNED,	/* spawned but not yet ready (I/O allocated) */
  ST_READY,	/* live and ready for data */
  ST_RUNNING,	/* actively processing data */
  ST_DONE,	/* completed processing data */
  ST_END	/* unused marker */
  };
typedef enum CHILD_STATUS status;
extern char *StatusName[];

struct childmanager
  {
  /* For spawned process communications */
  int ChildPid;	/* set to 0 if this record is not in use */
  int ChildStdin;	/* file handle */
  int ChildStdinRev;	/* file handle */
  int ChildStdout;	/* file handle */
  int ChildStdoutRev;	/* file handle */
  /* For managing state */
  status Status;	/* is the child ready for data? */
  status StatusLast;	/* previous status */
  time_t StatusTime;	/* when was the state changed? */
  time_t StatusLastDuration;	/* when changing state, how long was it at the previous state? */
  /* For managing child spawns and deaths */
  time_t SpawnTime;	/* when was the child created? */
  time_t Heartbeat;	/* when was the child last heard from? */
  int	SpawnCount;	/* number of respawns since SpawnTime */
  /* DB queue management */
  void *DB;		/* DB handle for child DB communications */
  int IsDB;		/* Is this a DB request? 0=no, 1=yes, 2=MSQ */
  int IsDBRepeat;	/* Is this a DB a repeat request? 1=yes, 0=no */
  int DBJobKey;		/* primary key for the job (jq_pk) */
  int DBMSQrow;		/* Row from an MSQ request */
  /* DB status */
  int DBagent;		/* Index into the DB agent table (for scheduler status) */
  /* Parameters for and about the agent */
  int HostId;		/* identifier for the host (see hosts.c) */
  long ItemsProcessed;	/* if 0, then RUNNING->READY makes it 1; can be set by "ItemsProcessed" string */
  char Attr[MAXATTR];	/* attributes for this agent */
  char Command[MAXCMD];	/* command used to run the child */
  char Parm[MAXCMD];	/* command used to run the child */
  /* How many items processed? */
  };
typedef struct childmanager childmanager;
#define MAXCHILD	4096
extern childmanager CM[MAXCHILD+1];	/* manage children */


void	ShowStates	(int Thread);
void	DebugThreads	(int Flag);
void	ChangeStatus	(int Thread, int NewState);
void	SaveStatus	();
void	ParentSig	(int Signo, siginfo_t *Info, void *Context);
void	CheckPids	();
void	HandleSig	(int Signo, siginfo_t *Info, void *Context);
int	ProcessCommand	(int Job_ID, char *Cmd);
void	MyExec	(int Thread, char *Cmd);
int	KillChild	(int Thread);
int	SpawnEngine	(int Thread);
void	InitEngines	(char *ConfigName);
int	TestEngines	();

#endif

