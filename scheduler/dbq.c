/*******************************************************
 dbq: Functions for processing the database queue.

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
/* for signals */
#include <sys/types.h>
#include <signal.h>

#include <libfossdb.h>
#include <libfossrepo.h>
#include "scheduler.h"
#include "spawn.h"
#include "sockets.h"
#include "agents.h"
#include "dbq.h"
#include "dbstatus.h"
#include "hosts.h"

multisqlqueue MSQ[MAXMSQ];
int	MSQpending=0;	/* how many are being held? */
int	MSQShiftWatch=0;	/* set to 1 whenever the MSQ shifts */

/***********************************************************
 DBQreset(): Mark any incomplete queue elements for a restart.
 NOTE: ONLY run this when the scheduler first starts.
 Use it to reset any incomplete jobs from a previous scheduler.
 If we every start running multiple schedulers, then never run this.
 ***********************************************************/
void	DBQreset	()
{
  /***********************************************
   UPDATE jobqueue
     SET jq_starttime = NULL,
         jq_endtext = 'Restart'
     WHERE jq_starttime IS NOT NULL and jq_endtime IS NULL;
   ***********************************************/
  DBLockAccess(DB,"UPDATE jobqueue SET jq_starttime = NULL, jq_endtext = 'Restart', jq_schedinfo = NULL WHERE jq_starttime IS NOT NULL and jq_endtime IS NULL;");
} /* DBQreset() */

/***********************************************************
 DBQinit(): Get things ready.
 ***********************************************************/
void	DBQinit	()
{
  int i;
  memset(MSQ,0,sizeof(MSQ));
  for(i=0; i<MAXMSQ; i++)
	{
	MSQ[i].JobId = -1;
	}
  MSQpending = 0;
#if 0
  DBQreset();	/* blow away any partially completed jobs */
#endif
} /* DBQinit() */

/***********************************************************
 DBQclose(): Shut things down.
 ***********************************************************/
void	DBQclose	()
{
  int i;
  for(i=0; i<MAXMSQ; i++)
    {
    if (MSQ[i].JobId != -1)
      {
      DBclose(MSQ[i].DBQ);
      if (MSQ[i].Processed != NULL) free(MSQ[i].Processed);
      }
    }
  DBQinit();
} /* DBQclose() */

/***********************************************************
 DBMSQremove(): Free a MSQ[i].
 The queue is re-ordered so the oldest is first.
 ***********************************************************/
void	DBMSQremove	(int i)
{
  int j;

  if (MSQ[i].JobId == -1) return; /* idiot checking */
  if (Verbose) fprintf(stderr,"DBMSQremove: %d\n",i);

  /* free memory */
  DBclose(MSQ[i].DBQ);
  if (MSQ[i].Processed) free(MSQ[i].Processed);

  /* move everything up */
  for(j=i; j+1 < MAXMSQ; j++)
    {
    MSQShiftWatch=1;
    memcpy(MSQ+j,MSQ+j+1,sizeof(multisqlqueue));
    }
  /* blank the last one */
  memset(MSQ+j,0,sizeof(multisqlqueue));
  MSQ[j].JobId = -1;
} /* DBMSQremove() */

/***********************************************************
 DBMkAttr(): Convert a DB row into an attribute list.
 Returns Urgent flag.
 ***********************************************************/
int	DBMkAttr	(void *DB, int Row, char *Attr, int MaxAttr)
{
  int IsUrgent;
  char *Value;

  memset(Attr,'\0',sizeof(Attr));
  Value = DBgetvaluename(DB,Row,"jq_type");
  if (Value && (Value[0] != '\0'))
	{
	strcat(Attr,"agent=");
	strcat(Attr,Value);
	strcat(Attr," ");
	}

  /* get the job priority */
  Value = DBgetvaluename(DB,Row,"job_priority");
  IsUrgent=0;
  if (Value && (Value[0] != '\0'))
	{
	if (atoi(Value) >= 100) IsUrgent=1;
	}

  if (Verbose) fprintf(stderr,"Attr='%s'\n",Attr);
  return(IsUrgent);
} /* DBMkAttr() */

/***********************************************************
 DBMemoveChild(): Given a child, remove any DB entries.
 Status: 0=success, 1=retry, 2=failure abort.
 ***********************************************************/
void	DBremoveChild	(int Thread, int Status, char *Message)
{
  int msq;	/* ID of MSQ */
  int UpdateType;

  /* idiot checking */
  if (CM[Thread].IsDB <= 0) return;	/* not a DB request? */
  if (CM[Thread].DBJobKey == 0) return;	/* no job? */

  /* check if it is a repeat */
  UpdateType=1;	/* default: it's ok! */
  if (CM[Thread].IsDBRepeat) UpdateType=2;
  /* check if it is a failure */
  if (Status == 1)	UpdateType=2; /* How do we handle retries? */
  if (Status == 2)	UpdateType=3; /* How do we handle failures? */

  if (CM[Thread].IsDB == 2) /* MSQ request */
	{
	for(msq=0; msq < MAXMSQ; msq++)
	  {
	  if (MSQ[msq].JobId == CM[Thread].DBJobKey)
	    {
	    /* mark this one MSQ job as completed */
	    MSQ[msq].Processed[CM[Thread].DBMSQrow] = ST_DONE;
	    MSQ[msq].ProcessCount += CM[Thread].ItemsProcessed;
	    MSQ[msq].ItemsDone++; /* increase number of DB items processed */
	    MSQ[msq].ProcessTimeAgent += CM[Thread].StatusLastDuration;
	    if (MSQ[msq].ItemsDone < MSQ[msq].MaxItems)
		{
		/* still have items to process */
		return;
		}
	    DBSaveJobStatus(-1,msq);
	    DBMSQremove(msq);
	    MSQpending--;
	    break; /* break loop */
	    } /* if found JobId */
	  } /* foreach MSQ */
	} /* if MSQ request */
  /* MSQ only gets here if all jobs are done. */
  if (CM[Thread].IsDB)	DBUpdateJob(CM[Thread].DBJobKey,UpdateType,Message);

  CM[Thread].IsDB = 0; /* no longer a DB request */
  CM[Thread].IsDBRepeat = 0; /* no longer a repeat request */
} /* DBremoveChild() */

/**********************************************
 DBGetAgentIndex(): Each agent should be in the DB agent
 table.  Get the index for the agent.
 If the agent does not exist, then add it.
 Attribute fields that are used: agent= and version=.
 Either may be any string!  (NULL or non-numbers are fine!)
 **********************************************/
int	DBGetAgentIndex	(char *Attr, int HasAgent)
{
  char SQL[MAXCMD];
  char *Val;
  char Empty[]="default";
  int rc;

  if (!Attr) return(-1);

  /** SELECT the agent info **/
  memset(SQL,'\0',MAXCMD);
  if (!HasAgent) Val = Attr;
  else Val = GetValueFromAttr(Attr,"agent=");
  if (!Val) Val = Empty;
  snprintf(SQL,MAXCMD-1,"SELECT agent_pk FROM agent WHERE agent_name = '%s'",
	Val);
  Val = GetValueFromAttr(Attr,"version=");
  if (!Val) Val = Empty;
  snprintf(SQL+strlen(SQL),MAXCMD-1-strlen(SQL)," AND agent_rev = '%s';",Val);
  rc = DBLockAccess(DB,SQL);
  if ((rc <= 0) || (DBdatasize(DB) < 1))
    {
    /** No select? INSERT **/
    memset(SQL,'\0',MAXCMD);
    Val = GetValueFromAttr(Attr,"agent=");
    if (!Val) Val = Empty;
    snprintf(SQL,MAXCMD-1,"INSERT INTO agent (agent_name,agent_desc,agent_rev) VALUES ('%s','%s agent for use with scheduler'",Val,Val);
    Val = GetValueFromAttr(Attr,"version=");
    if (!Val) Val = Empty;
    snprintf(SQL+strlen(SQL),MAXCMD-1-strlen(SQL),",'%s');",Val);
    DBLockAccess(DB,SQL); /* INSERT */
    DBLockAccess(DB,"SELECT currval('agent_agent_pk_seq'::regclass);"); /* GET */
    }

  /* Return value */
  return(atoi(DBgetvalue(DB,0,0)));
} /* DBGetAgentIndex() */

/***********************************************************
 DBMSQinsert(): Add the request to the DB queue.
 DBQ and row = queue item being processed.
 Returns MSQ index, or -1 on failure (not enough room).
 ***********************************************************/
int	DBMSQinsert	(void *DBQ, int Row)
{
  int i,j;
  void *DBtmp;
  int CountJobType;
#if 0
  char *RunOnPfile;
#endif

  if (MSQpending >= MAXMSQ)	return(-1);

  /* idiot check: Don't add an empty entry */
  if (DBdatasize(DBQ) <= 0) return(-1);

  /* find the available MSQ slot */
  /*************************************
   Optimization: There can be lots of pending license jobs
   as well as a few other jobs.  We don't want to have space for
   6 jobs and have all 6 being license.
   Why not?  Chances are very good that job #4 will not be processed
   until after one of job 1-3 completes.
   The solution: Don't add more than a known number of job types.
   *************************************/
  CountJobType=0;
  for(i=0; (i<MAXMSQ) && (MSQ[i].JobId != -1); i++)
    {
    if (!strcmp(MSQ[i].Type,DBgetvaluename(DBQ,Row,"jq_type")))
	{
	CountJobType++;
	if (CountJobType >= MAXMSQTYPE) return(-1);
	}
    }
  if (i >= MAXMSQ) return(-1);	/* no slots */

  /*************************************/
  /** If it gets here, then MSQ[i] needs filling **/

  /* cleanup as needed */
  /** DBtmp is used to prevent usage race condition with signals */
  DBtmp = MSQ[i].DBQ;
  MSQ[i].DBQ = NULL;
  DBclose(DBtmp);
  if (MSQ[i].Processed) free(MSQ[i].Processed);
  MSQ[i].Processed = NULL;
  memset(MSQ[i].Type,0,MAXCMD);
  memset(MSQ[i].Attr,0,MAXCMD);
  memset(MSQ[i].HostCol,0,MAXCMD);
  MSQ[i].ItemsDone=0;
  MSQ[i].ProcessCount=0;
  MSQ[i].ProcessTimeAgent=0;
  MSQ[i].ProcessTimeStart=time(NULL);

  /* get the specific item */
  MSQ[i].JobId = atol(DBgetvaluename(DBQ,Row,"jq_pk"));
  strncpy(MSQ[i].Type,DBgetvaluename(DBQ,Row,"jq_type"),MAXCMD);
  MSQ[i].IsRepeat = !strcasecmp(DBgetvaluename(DBQ,Row,"jq_repeat"),"yes");
  MSQ[i].IsUrgent = DBMkAttr(DBQ,Row,MSQ[i].Attr,MAXCMD);
  strncpy(MSQ[i].HostCol,DBgetvaluename(DBQ,Row,"jq_runonpfile"),MAXCMD-1);
  MSQ[i].DBagent = DBGetAgentIndex(MSQ[i].Type,0);
  while(isspace(MSQ[i].HostCol[strlen(MSQ[i].HostCol)-1]))
	{
	MSQ[i].HostCol[strlen(MSQ[i].HostCol)-1] = '\0';
	}

#if 0
  /* If it is an MSQ, then initialize each row */
  RunOnPfile = DBgetvaluename(DBQ,Row,"jq_runonpfile");
  if (RunOnPfile && RunOnPfile[0])
    {
#endif
    /* Get the SQL results */
    DBUpdateJob(MSQ[i].JobId,0,NULL);
    if (Verbose)
	fprintf(stderr,"SQL: '%s'\n",DBgetvaluename(DBQ,Row,"jq_args"));
    switch(DBLockAccess(DB,DBgetvaluename(DBQ,Row,"jq_args")))
	{
	case 0: /* no data; mark it as done */
		if (Verbose) fprintf(stderr,"SQL -- no data.\n");
		DBUpdateJob(MSQ[i].JobId,1,"No data");
		DBMSQremove(i);
		break;
	case 1:	/* got data -- save it */
		MSQ[i].DBQ = DBmove(DB);
		MSQ[i].MaxItems = DBdatasize(MSQ[i].DBQ);
		if (Verbose) fprintf(stderr,"SQL -- %d items, inserted into MSQ[%d].\n",MSQ[i].MaxItems,i);
		if (MSQ[i].MaxItems <= 0)
			{
			/* no data, mark it as done */
			/* this can happen from a bad request OR from a reschedule */
			MSQ[i].IsRepeat = 0; /* no data? no repeat! */
			DBUpdateJob(MSQ[i].JobId,1,"All data processed");
			DBMSQremove(i);
			return(-2);
			}
		MSQ[i].Processed = (int *)malloc(sizeof(int)*MSQ[i].MaxItems);
		if (!MSQ[i].Processed)
		  {
		  DBclose(MSQ[i].DBQ);
		  MSQ[i].DBQ=NULL;
		  return(-1);
		  }
		for(j=0; j < MSQ[i].MaxItems; j++)
		  {
		  MSQ[i].Processed[j] = ST_READY;
		  }
		MSQpending++;
		return(i);
	case -3:	/* operation timeout */
	  if (Verbose) fprintf(stderr,"SQL -- Timeout.\n");
	  fprintf(stderr,"ERROR: job %d: SQL timeout (%s)\n",MSQ[i].JobId,DBgetvaluename(DBQ,Row,"jq_args"));
	  DBUpdateJob(MSQ[i].JobId,2,"Timeout");
	  DBMSQremove(i);
	  return(-1);
	default: /* error */
	  if (Verbose) fprintf(stderr,"SQL -- ERROR.\n");
	  fprintf(stderr,"ERROR: job %d: SQL error (%s)\n",MSQ[i].JobId,DBgetvaluename(DBQ,Row,"jq_args"));
	  DBUpdateJob(MSQ[i].JobId,1,"Error");
	  DBMSQremove(i);
	  return(-1);
	} /* switch SELECT */
#if 0
    } /* if MSQ */
  else
      {
      /** Non-MSQ jobs are stored as MSQ jobs that just have one row
          and a host of -1. **/
      MSQ[i].DBQ = NULL;
      MSQ[i].MaxItems = 1;
      MSQ[i].Processed = (int *)malloc(sizeof(int)*MSQ[i].MaxItems);
      if (!MSQ[i].Processed)
        {
	return(-1);
	}
      MSQ[i].Processed[0] = ST_READY;
      }
#endif
  return(i);
} /* DBMSQinsert() */

/***********************************************************
 DBMkArg(): Save jq_args as the value.
 ***********************************************************/
void	DBMkArg	(void *DB, int Row, char *Arg, int MaxArg)
{
  char *Value;

  memset(Arg,'\0',MaxArg);
  Value = DBgetvaluename(DB,Row,"jq_args");
  if (Value) strncpy(Arg,Value,MaxArg-2);
  strcat(Arg,"\n");	/* add a \n to the line */
} /* DBMkArg() */

/***********************************************************
 DBCheckPendingMSQ(): Check and spawn any pending MSQ elements.
 Returns:
   0 = nothing in the queue
   1 = something in queue, but nothing new processed
   2 = something processed
 NOTE: This needs to be optimized for performance.
 ***********************************************************/
int	DBCheckPendingMSQ	()
{
  int i,j;
  int rc=0;
  char Arg[MAXCMD];
  int Thread;
  char *Value=NULL;
  char Attr[MAXCMD];

  MSQShiftWatch=0;

  if (MSQpending <= 0) return(0);
  for(i=0; i<MAXMSQ; i++)
    {
    if (!rc) rc=1; /* something in the queue, nothing processed yet */
    if (MSQ[i].JobId >= 0)
      {
      /* First see if there is an available thread... */
      for(Thread=0; Thread < MaxThread; Thread++)
	{
	if ((CM[Thread].DBagent == MSQ[i].DBagent) &&
	    (CM[Thread].Status <= ST_READY))	break;
	}
      if (Thread >= MaxThread)
	      	{
		continue; /* no agents available */
		}

      for(j=0; j<MSQ[i].MaxItems; j++)
	{ /* process segment */
	if (MSQ[i].Processed[j] != ST_READY) continue;

	/* found an item to process */
	if (Verbose) fprintf(stderr,"MSQ: Checking items in MSQ[%d]\n",i);

	DBMkArgCols(MSQ[i].DBQ,j,Arg,MAXCMD);
	memset(Attr,'\0',MAXCMD);
	strcpy(Attr,MSQ[i].Attr);
	Value = DBgetvaluename(MSQ[i].DBQ,j,MSQ[i].HostCol); /* get host column */
	Value = RepGetHost("files",Value);
	if (!IgnoreHost && Value && (Value[0] != '\0'))
	  {
	  strcat(Attr,"host=");
	  strcat(Attr,Value);
	  strcat(Attr," ");
	  }
	if (Value) free(Value); /* free string from RepGetHost() */
	Thread = GetChild(Attr,MSQ[i].IsUrgent);

	/** WATCH OUT!
	    MSQ is a shifting array.  When all jobs end, the entire
	    array shifts down.  If it shifted, then restart this entire
	    scanning process. Otherwise, who knows what I'm looking at now,
	    and I could end up looking at a NULL pointer.
	    When can it shift?  When GetChild() calls SpawnEngine() calls
	    SelectAnyData().  SelectAnyData() can result in the completion
	    of an MSQ set of data and shifts the array.
	    Solution: Check if the MSQ shifted.  If it did, re-scan
	    everything.  Since we're talking microseconds, the spawned
	    thread won't change.
	**/
	if (MSQShiftWatch)
		{
		if (Verbose) fprintf(stderr,"MSQ shifted. Retrying.\n");
		return(DBCheckPendingMSQ());
		}

	if (Thread >= 0)
	  {
	  /* mark the DB queue item as taken */
	  if (Verbose)
		fprintf(stderr,"(b) Feeding child[%d][%d/%d][%d/%d]: attr='%s' | arg='%s'\n",Thread,i,MAXMSQ,j,MSQ[i].MaxItems,Attr,Arg);
	  MSQ[i].Processed[j] = ST_RUNNING;
	  if (CM[Thread].Status != ST_RUNNING)
	    {
	    ChangeStatus(Thread,ST_RUNNING);
	    }
	  CM[Thread].IsDB = 2;	/* MSQ request */
	  CM[Thread].DBMSQrow = j;	/* MSQ item */
	  CM[Thread].IsDBRepeat = MSQ[i].IsRepeat;
	  CM[Thread].DBJobKey = MSQ[i].JobId;
#if 0
	  DBUpdateJob(CM[Thread].DBJobKey,0,"In progress"); /* mark it in use */
#endif
	  memset(CM[Thread].Parm,'\0',MAXCMD);
	  strcpy(CM[Thread].Parm,Arg);
	  write(CM[Thread].ChildStdin,Arg,strlen(Arg));
	  rc=2;
	  } /* write to child */
	} /* for each item j in the MSQ */
      } /* if MSQ[i].JobId >= 0 */
    } /* for each MSQ i */
  if (Verbose) fprintf(stderr,"DBCheckPendingMSQ()=%d\n",rc);
  return(rc);
} /* DBCheckPendingMSQ() */

/***********************************************************
 DBProcessQueue(): Look at the DB queue and process any
 pending items.  This returns after starting ALL possible items.
 Returns:
   0 = nothing in the queue
   1 = something in queue, but nothing processed
   2 = something processed
 NOTE: Right now, there are two types of DB args:
   (1) Text that is not host-specific.
   (2) Multi-SQL results that ARE host-specific.
 Other cases, such as Multi-SQL that are not host-specific do not exist.
 If they are every a requirement, this code will need to change.
 ***********************************************************/
int	DBProcessQueue	()
{
  int rc;
  int IsProcess=0;	/* function return value: is something processed? */
  char Attr[MAXCMD];
  char Arg[MAXCMD];
  int ArgLen;
  int Row,MaxRow;
  int IsUrgent;
  int Thread;
  void *DBQ;	/* the database queue results */
  char *Value;
  static time_t Poll=0;	/* time for next poll */
  time_t Now;
  int Job_Id;

  /***********************************
   Highest priority: Things currently being held in the queue scheduler.
   ***********************************/
  IsProcess = DBCheckPendingMSQ(DB);

  /* prevent fast spawning! */
  Now = time(NULL);
  if (Poll >= Now-10)
    {
    if (IsProcess > 1) return(2);
    /* ok, nothing processed, and it is too soon to poll the DB */
    if (SelectAnyData(0,0) == 0)
	{
	/* no running processes?  Just sleep... */
	usleep(100000); /* one tenth of a second */
	}
    return(IsProcess);
    }
  Poll=Now;

  /* If SLOWDEATH, then complete existing jobs, but don't check for
     new ones. */
  if (SLOWDEATH) return(IsProcess);

  if (Verbose) printf("Checking DB\n");

  /***********************************
   Get the queue, and prioritize it by job_priority.
   We do this with a stored procedure: getrunnable().
   If that fails, try this:
   SELECT DISTINCT(jobqueue.*), job.* FROM jobqueue
     LEFT JOIN jobdepends ON jobqueue.jq_pk = jobdepends.jdep_jq_fk 
     LEFT JOIN jobqueue AS depends 
        ON depends.jq_pk = jobdepends.jdep_jq_depends_fk
     LEFT JOIN job ON jobqueue.jq_job_fk = job.job_pk 
   WHERE 
     jobqueue.jq_starttime IS NULL 
     AND ( 
        (depends.jq_endtime IS NOT NULL AND depends.jq_end_bits < 2 )
        OR jobdepends.jdep_jq_depends_fk IS NULL
     ) 
   ORDER BY job.job_priority DESC,job.job_queued ASC LIMIT 6;
   ***********************************/
  rc = DBLockAccess(DB,"SELECT * FROM getrunnable() LIMIT 10;");
  if (rc < 0)
    {
  rc = DBLockAccess(DB,"SELECT DISTINCT(jobqueue.*), job.* FROM jobqueue LEFT JOIN jobdepends ON jobqueue.jq_pk = jobdepends.jdep_jq_fk LEFT JOIN jobqueue AS depends ON depends.jq_pk = jobdepends.jdep_jq_depends_fk LEFT JOIN job ON jobqueue.jq_job_fk = job.job_pk WHERE jobqueue.jq_starttime IS NULL AND ( (depends.jq_endtime IS NOT NULL AND depends.jq_end_bits < 2 ) OR jobdepends.jdep_jq_depends_fk IS NULL) ORDER BY job.job_priority DESC,job.job_queued ASC LIMIT 6;");
    }
  if (Verbose) fprintf(stderr,"SQL: Getting queue = %d :: %d items\n",rc,DBdatasize(DB));
  if (rc == 1) /* if get list of queued items */
    {
    /* save results in DBQ since DB may be use for other requests */
    DBQ = DBmove(DB);
    if (!IsProcess) IsProcess=1; /* there is something in the queue */
    MaxRow = DBdatasize(DBQ);
    if (MaxRow <= 0) return(0);
    if (Verbose) printf("Items in queue: %d\n",MaxRow); fflush(stdout);
    for(Row=0; Row < MaxRow; Row++)
      {
      if (SLOWDEATH) return(IsProcess);

      /** Check if the job is even possible **/
      Job_Id = atoi(DBgetvaluename(DBQ,Row,"jq_pk"));
      Value = DBgetvaluename(DBQ,Row,"jq_type");
      if (!strcmp(Value,"command"))
	{
	ProcessCommand(Job_Id,DBgetvaluename(DBQ,Row,"jq_args"));
	continue;
	}
      else if (CheckAgent(Value) < 0) continue; /* no such agent -- skip it */

      /* check if this is a multi-SQL result */
      Value = DBgetvaluename(DBQ,Row,"jq_runonpfile");
      /* if jq_runonpfile is defined, then it is a MSQ request */
      if (Value && (Value[0] != '\0'))
        {
	/* found another high-priority item -- add it and check it */
	DBMSQinsert(DBQ,Row);
	if (DBCheckPendingMSQ(DB) == 2) IsProcess=2;
	continue;
	}

      /** If it gets here, it is not a multi-SQL (MSQ) request **/

      /* convert DBQ request into a queue request */
      IsUrgent = DBMkAttr(DBQ,Row,Attr,sizeof(Attr));
      DBMkArg(DBQ,Row,Arg,MAXCMD);
      ArgLen = strlen(Arg);

      Thread = GetChild(Attr,IsUrgent);
      if (Thread >= 0)
	{
	/* mark the DB queue item as taken */
	CM[Thread].IsDB = 1;	/* DB request */
	if (CM[Thread].Status != ST_RUNNING)
	  {
	  ChangeStatus(Thread,ST_RUNNING);
	  }
	CM[Thread].DBJobKey = atoi(DBgetvaluename(DBQ,Row,"jq_pk"));
	DBUpdateJob(CM[Thread].DBJobKey,0,"In progress"); /* mark it in use */
	if (Verbose)
		fprintf(stderr,"(c) Feeding child[%d]: attr='%s' | arg='%s'\n",Thread,Attr,Arg);
	memset(CM[Thread].Parm,'\0',MAXCMD);
	strcpy(CM[Thread].Parm,Arg);
	write(CM[Thread].ChildStdin,Arg,ArgLen);
	IsProcess = 2;
	}
      } /* for each row */

    /* return results */
    DBclose(DBQ);
    } /* if there is data */

  return(IsProcess); /* nothing queued */
} /* DBProcessQueue() */

