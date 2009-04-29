/*******************************************************
 selftest.c: Check if the agents are configured properly.

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
#include <string.h>
#include <errno.h>
#include <sys/types.h>
#include <sys/stat.h>

#include "scheduler.h"
#include "agents.h"
#include "hosts.h"
#include "spawn.h"
#include "logging.h"

/**********************************************
 SelfTest(): Perform a self-test.
 - Generate self-test data.
 - See if every agent of type "selftest" returns the
   same data.
 Returns: 0 on success, 1 on any selftest failure.
 **********************************************/
int	SelfTest	()
{
  FILE *Fin, *FData, *FTest;
  char SelfTest[] = "echo 'test' | " AGENTDIR "/selftest -g -s"; /* -g for generate test data */
  char MkConfig[] = LIBEXECDIR "/mkschedconf -L 2>&1 | grep agent | sed 's/.*agent=\\(\\w*\\).*/\\1/' | sort -u"; /* get list of agents */
  int c,i;
  int Thread;
  int rc=0;
  int *HostCheck; /* 0=not checked, 1=success, -1=failed */
  int HostId;
  char Line[2][1024];
  int Lines=0;

  /* Prepare for the data */
  if (MaxHostList <= 0)
    {
    LogPrint("FATAL: No agent systems loaded for self-test.\n");
    return(1);
    }

  /*************************************************/
  /* Make sure every agent exists */
  Fin = popen(MkConfig,"r");
  if (!Fin)
    {
    LogPrint("FATAL: Unable to run mkschedconf for self-test.\n");
    return(1);
    }
  /* read every line and check if the agent exists */
  rc=0;
  while(!feof(Fin))
    {
    memset(Line[0],0,1024);
    i=0;
    c=fgetc(Fin);
    while(!feof(Fin) && (c>0) && (c!='\n'))
      {
      Line[0][i]=c;
      i++;
      c=fgetc(Fin);
      }
    /* Check if the agent exists */
    if (i>0)
      {
      Thread = CheckAgent(Line[0]);
      if (Thread < 0)
        {
	LogPrint("FATAL: Agent type '%s' not in Scheduler.conf.\n",Line[0]);
	rc=1;
	}
      }
    }
  pclose(Fin);
  if (rc) return(1);
  rc=0;

  /*************************************************/
  /* Make sure every agent tests properly */
  HostCheck = (int *)calloc(MaxHostList,sizeof(int));
  FData = tmpfile();
  if (!FData)
    {
    LogPrint("FATAL: Unable to open temporary file for self-test.\n");
    return(1);
    }

  /* Check if the selftest agent exists */
  Fin = popen(SelfTest,"r");
  if (!Fin)
    {
    LogPrint("FATAL: Unable to generate test data: '%s'.\n",SelfTest);
    fclose(FData);
    return(1);
    }

  /* Store test data */
  Lines=0;
  do
    {
    c = fgetc(Fin);
    if (c>=0) fputc(c,FData);
    if (c=='\n') Lines++;
    } while(c >= 0);
  pclose(Fin);
  if ((Lines < 5) || (ftell(FData) < 1))
    {
    LogPrint("FATAL: Unable to generate test data: '%s'.\n",SelfTest);
    fclose(FData);
    return(1);
    }

  /* Iterate through each agent and run all the test agents */
  for(Thread=0; Thread < MaxThread; Thread++)
    {
    if (!MatchAttr(CM[Thread].Attr,"agent=selftest")) continue; /* not a test agent */
    HostId = GetHostFromAttr(CM[Thread].Attr);
    if (HostId < 0) continue; /* no host */
    if (HostCheck[HostId] != 0) continue; /* already checked */
    memset(Line[1],'\0',1024);
    snprintf(Line[1],1024,"echo 'test' | %s",CM[Thread].Command);
    FTest = popen(Line[1],"r");
    if (!FTest)
      {
      LogPrint("FATAL: Unable to test: %s | %s.\n",CM[Thread].Attr,CM[Thread].Command);
      fclose(FData);
      rc=1;
      }
    else
      {
      rewind(FData);
      /* Check if the agent's data looks identical to the scheduler's data */
      /** Read line from server **/
      while(!feof(FData) && !feof(FTest) && !HostCheck[HostId])
	{
	/** Read line from scheduler system **/
	memset(Line[0],0,1024);
	i=0;
	do
	  {
	  c = fgetc(FData);
	  if ((c>=0) && (c!='\n')) Line[0][i]=c;
	  i++;
	  } while(!feof(FData) && (c >= 0) && (c!='\n') && (i<1023));
	if (!strncmp(Line[0],"FATAL:",6))
	  {
	  LogPrint("FATAL: Scheduler error: %s\n",Line[0]+6);
	  fclose(FData);
	  pclose(FTest);
	  return(1);
	  }

	/** Read line from agent system **/
	memset(Line[1],0,1024);
	i=0;
	do
	  {
	  c = fgetc(FTest);
	  if ((c>=0) && (c!='\n')) Line[1][i]=c;
	  i++;
	  } while(!feof(FTest) && (c >= 0) && (c!='\n') && (i<1023));

	/* See if they matched */
	if (memcmp(Line[0],Line[1],1024))
	  {
	  LogPrint("FATAL: Configuration on agent '%s' differs from scheduler.\n",HostList[HostId].Hostname);
	  if (Line[1])
		{
		if (Verbose)
		  {
		  LogPrint("FATAL: Difference: '%s' != '%s'\n",Line[0],Line[1]);
		  }
		for(i=0; (Line[1][i] != 0) && !strchr("=:",Line[1][i]); i++) ;
		LogPrint("FATAL: The difference is %.*s\n",i,Line[1]);
		LogPrint("  Observed: %s\n",Line[1]);
		LogPrint("  Expected: %s\n",Line[0]);
		}
	  rc=0;
	  HostCheck[HostId] = -1;
	  }
	} /* while */

      if (!feof(FData) != !feof(FTest))
	{
	LogPrint("FATAL: Configuration on agent '%s' differs from scheduler.\n",HostList[HostId].Hostname);
	rc=0;
	HostCheck[HostId] = -1;
	}
      if (!HostCheck[HostId]) HostCheck[HostId]=1;
      pclose(FTest);
      }
    }

  /* Check if every host has been validated */
  for(i=0; i<MaxHostList; i++)
    {
    if (HostCheck[i] != 1)
      {
      if (HostCheck[i] == 0) LogPrint("FATAL: Host '%s' missing self-test agent.\n",HostList[i].Hostname);
      LogPrint("FATAL: Host '%s' failed self-test.\n",HostList[i].Hostname);
      rc=1;
      }
    }
  free(HostCheck);

  fclose(FData);
  return(rc);
} /* SelfTest() */

