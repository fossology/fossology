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
  char SelfTest[] = "echo 'test' | " BINDIR "/selftest -g -s"; /* -g for generate test data */
  int c,i;
  int Thread;
  int rc=0;
  int *HostCheck; /* 0=not checked, 1=success, -1=failed */
  int HostId;
  char Line[2][1024];

  /* Prepare for the data */
  if (MaxHostList <= 0)
    {
    fprintf(stderr,"FATAL: No agent systems loaded for self-test.\n");
    return(1);
    }
  HostCheck = (int *)calloc(MaxHostList,sizeof(int));
  FData = tmpfile();
  if (!FData)
    {
    fprintf(stderr,"FATAL: Unable to open temporary file for self-test.\n");
    return(1);
    }

  /* Check if the selftest agent exists */
  Fin = popen(SelfTest,"r");
  if (!Fin)
    {
    fprintf(stderr,"FATAL: Unable to generate test data: '%s'.\n",SelfTest);
    fclose(FData);
    return(1);
    }

  /* Store test data */
  do
    {
    c = fgetc(Fin);
    if (c>=0) fputc(c,FData);
    } while(c >= 0);
  pclose(Fin);
  if (ftell(FData) < 1)
    {
    fprintf(stderr,"FATAL: Unable to generate test data: '%s'.\n",SelfTest);
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
      fprintf(stderr,"FATAL: Unable to test: %s | %s.\n",CM[Thread].Attr,CM[Thread].Command);
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
	  fprintf(stderr,"FATAL: Configuration on agent '%s' differs from scheduler.\n",HostList[HostId].Hostname);
	  if (Line[1]) fprintf(stderr,"FATAL: Offending line: %s\n",Line[1]);
	  rc=0;
	  HostCheck[HostId] = -1;
	  }
	} /* while */

      if (!feof(FData) != !feof(FTest))
	{
	fprintf(stderr,"FATAL: Configuration on agent '%s' differs from scheduler.\n",HostList[HostId].Hostname);
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
      fprintf(stderr,"FATAL: Host '%s' failed self-tested.\n",HostList[i].Hostname);
      rc=1;
      }
    }
  free(HostCheck);

  fclose(FData);
rc=1;
  return(rc);
} /* SelfTest() */

