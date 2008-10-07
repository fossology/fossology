/***********************************************************
 hosts.c: manage number of processes per host.

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
 ***********************************************************/

#include <stdlib.h>
#include <stdio.h>
#include <string.h>
#include <ctype.h>
#include <syslog.h>
#include "scheduler.h"
#include "hosts.h"

/**********************************************
 The Running/Urgent concept:
 Run: process is in use.
 Urgent: extra process for handling urgent requests.
 Since some processes consume memory, you might want to
 set MaxRunning a little lower.  It will slow spawning for new requests,
 but kills processes and frees memory.
 The idea:
   - If something needs to run and Running < MaxRunning, then you can
     spawn a new process.
   - But, if Running >= MaxRunning, then you can either feed an existing child
     or must kill a child before you can spawn a new one.
     (It's a cruel world.)
 **********************************************/
#define MAXHOSTLIST	64
hostlist HostList[MAXHOSTLIST];	/* currently limit number of hosts */
int	MaxHostList = 0;	/* how many loaded? */
int	RunCount=0;	/* total number of running processes */

/*****************************************************
 HostAdd(): Add (or update) a host.
 Set MaxRunning/MaxUrgent to -1 for no-limit.
 *****************************************************/
void	HostAdd	(char *Hostname, int MaxRunning, int MaxUrgent)
{
  int i;

  if (Verbose) syslog(LOG_DEBUG,"Adding host: '%s' Max=%d Urgent=%d\n",Hostname,MaxRunning,MaxUrgent);

  /* check for an update */
  for(i=0; i<MaxHostList; i++)
    {
    if (!strcmp(Hostname,HostList[i].Hostname))
	{
	HostList[i].MaxRunning = MaxRunning;
	HostList[i].MaxUrgent = MaxUrgent;
	return;
	}
    }
  /* not found... so add it! */
  if (MaxHostList < MAXHOSTLIST)
    {
    /* clear and set values */
    memset(HostList[MaxHostList].Hostname,'\0',65);
    strncpy(HostList[MaxHostList].Hostname,Hostname,64);
    HostList[MaxHostList].MaxRunning=MaxRunning;
    HostList[MaxHostList].MaxUrgent=MaxUrgent;
    HostList[MaxHostList].Running=0; /* none running right now */
    MaxHostList++;	/* host added! */
    }
} /* HostAdd() */

/*****************************************************
 GetHostFromAttr(): Given an attribute, find the host.
 Returns the host index.
 If there is no host index, then return -1;
 *****************************************************/
int	GetHostFromAttr	(char *Attr)
{
  char *H;
  int i,j;
  H=strstr(Attr,"host=");
  if (H && (H != Attr))
    {
    /* make sure we don't match something like "maxhost=" */
    while((H > Attr) && !isspace(H[-1]))
      {
      H=strstr(H,"host=");
      }
    }
  if (!H) return(-1); /* no host */

  /* ok, got a host in "H".  Now find the hostname */
  H=H+5; /* move past the "host=" */
  for(i=0; i<MaxHostList; i++)
    {
    /* get length of hostname */
    for(j=0; (H[j] != '\0') && !isspace(H[j]); j++)	;
    /* see if they match */
    if (!strncmp(HostList[i].Hostname,H,j))	return(i);
    }
  /* no match -- use default host */
  return(0);
} /* GetHostFromAttr() */

/*****************************************************
 GetValueFromAttr(): Given a field, find the value.
 Returns the value string (static string).
 If there is no value, then return NULL.
 *****************************************************/
char *	GetValueFromAttr	(char *Attr, char *Field)
{
  char *F;
  static char Value[256];
  int i;

  F=strstr(Attr,Field);
  if (F && (F != Attr))
    {
    /* make sure we don't match something like "maxFIELD=" */
    while((F > Attr) && !isspace(F[-1]))
      {
      F=strstr(F,Field);
      }
    }
  if (!F)
    {
    return(NULL); /* no field */
    }

  /* ok, got an agent in "F".  Now find the value */
  F=F+strlen(Field); /* move past the "field=" */
  memset(Value,'\0',sizeof(Value));
  for(i=0; (i<sizeof(Value)) && (F[i]!='\0') && !isspace(F[i]); i++)
    {
    Value[i]=F[i];
    }
  return(Value);
} /* GetValueFromAttr() */

/*****************************************************
 CanHostRun(): Given an attribute, check if the host
 can run a process.
 Returns 1=YES!  0=NO!
 If the host is unknown, then the answer is always NO.
 *****************************************************/
int	CanHostRun	(int HostId, int Urgent)
{
  int Limit;
  if (HostId < 0) return(0);	/* no host found */
  Limit = HostList[HostId].MaxRunning;
  if (Urgent) Limit += HostList[HostId].MaxUrgent;
  if (HostList[HostId].Running < Limit) return(1); /* there's room */
  return(0); /* no room */
} /* CanHostRun() */

/*****************************************************
 SetHostRun(): Given an attribute, set the host running counter.
 Value is either +1 for starting, or -1 for ended.
 *****************************************************/
void	SetHostRun	(int HostId, int Value)
{
  if (HostId < -1) HostId=0;	/* default to localhost */
  HostList[HostId].Running += Value;
  RunCount += Value;
} /* SetHostRun() */

