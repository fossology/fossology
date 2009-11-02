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
#ifndef DBQ_H
#define DBQ_H

/************************************************
 Some DB queue items require running on a specific
 host.  Each row can be fed to a new host.
 Rather than calling the SQL every time, let's call
 it once and hold it.
 Stuff the results in a queue stack of things to
 process.
 ************************************************/
struct multisqlqueue
  {
  /* Class of agent */
  int JobId;    /* queue "jq_pk" -- unique id for this job (-1 for dead) */
  int IsRepeat; /* queue "jq_repeat" -- is this a repeat request? (1=yes) */
  int IsUrgent; /* queue is urgent? (1=yes) */
  void *DBQ;    /* results of the multi-SQL */
  int DBagent;  /* agent_pk of agent needed */

  /* Metrics */
  long ProcessCount;	/* how many have been processed? */
  time_t ProcessTimeStart;	/* time spend doing ProcessCount jobs */
  time_t ProcessTimeAgent;	/* when did this MSQ item start? */
    /*****
     ProcessTimeAgent is cumulative agent times.
     time(NULL)-ProcessTimeStart = time all queue items were in the scheduler.
     ProcessCount is ADDED to jq_filesprocessed.
     time(NULL)-ProcessTimeStart is ADDED to jq_elapsedtime.
     ProcessTimeAgent is ADDED to jq_processtime.
     (The added assumption is that they were zero'd when jq_pk was created.)
     *****/
  
  /* Parameters (per item) */
  int MaxItems;	/* number of results (for quick index) */
  int ItemsDone;	/* number of results (for quick index) */
  int *Processed;       /* Processed[MaxItems] = CHILD_STATUS */
  char Type[MAXCMD];    /* jq_type, aka  agent.agent_name */
  char Attr[MAXCMD];    /* common attributes */
  char HostCol[MAXCMD]; /* column containing the "host=" attribute variable */
  };
typedef struct multisqlqueue multisqlqueue;

#define MAXMSQ	6	/* how many can be held at once? */
			/* holding takes memory -- don't hold too many */
#define MAXMSQTYPE	3	/* how many of the same type to hold? */

extern multisqlqueue MSQ[MAXMSQ];

void	DBQinit	();
void	DBQclose	();
int	DBGetAgentIndex	(char *Attr, int HasAgent);

int	DBProcessQueue	();
void	DBremoveChild	(int Thread, int Status, char *Message);

#endif

