/*******************************************************
 Copyright (C) 2009 Hewlett-Packard Development Company, L.P.
 
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
This is a watchdog for the fossology scheduler.  It checks every
few minutes to see if the scheduler has updated the
scheduler_status table.  If it has not, this program will
restart the scheduler.
 *******************************************************/

void *DB=0;	/* the DB queue */
int Verbose=0;

#define MAXCMD 8192

#include <stdlib.h>
#include <stdio.h>
#include <string.h>
#include <errno.h>
#include <signal.h>
#include <sys/types.h>
#include <unistd.h>
#include <sys/stat.h>
#include <fcntl.h>

#include "logging.h"
#include "scheduler.h"
#include "sched_utils.h"
#include "lockfs.h"
#include <libfossdb.h>

void  Usage (char *Name)
{
  fprintf(stderr,"Usage: %s [options]\n",Name);
  fprintf(stderr,"  -k :: Kill the watchdog\n");
}


int	main	(int argc, char *argv[])
{
  int rv, c;
  pid_t mypid = -1, oldpid;
  char *ProcessName="fo_watchdog";
  PGresult *Res;
  PGconn   *Conn;

  /* set user/group */
  SetPuserPgrp(ProcessName);

  /* check args */
  while((c = getopt(argc,argv,"k")) != -1)
  {
    switch(c)
    {
      case 'k':
        StopWatchdog();
        exit(0);
      default:
        Usage(argv[0]);
        exit(0);
    }
  }

  /* Is another watchdog running? */
  oldpid = LockGetPID(ProcessName);
  if (oldpid)
  {
    if (Verbose) 
    {
      LogPrint("*** %s (pid %d) already running.  No need to start another.  ***\n", ProcessName, oldpid);
    }
    exit(0);
  }

  /* start as daemon */
  if (daemon(0,(LogFile!=NULL)) != 0)
  {
    LogPrint("*** %s exiting due to failure to start as a daemon. %s ***\n", ProcessName, strerror(errno));
    exit(-1);
  }

  /* store lock for this process.  */
  rv = LockName(ProcessName);
  if (rv < 0)
  {
    LogPrint("*** %s lock error, see log file ***\n", ProcessName);
    exit(-1);
  }

  /* rv == 0  Got a new lock */
  if (rv == 0)
  {
    if (Verbose) 
      LogPrint("*** New %s successfully locked ***\n", ProcessName);
  }

  mypid = getpid();
  LogPrint("*** %s daemon started. PID %d ***\n", ProcessName, mypid);

  DB = DBopen();
  if (!DB)
  {
    LogPrint("FATAL: %s unable to connect to database.  Terminating.\n", ProcessName);
    exit(-1);
  }
  Conn = DBgetconn(DB);

  while(1)
  {
    /* Check every 5 minutes to see if the scheduler is updating the scheduler_status table */
    sleep(5*60);

    Res = PQexec(Conn, "SELECT record_update from scheduler_status where agent_number='-1' and (now()-record_update) < '4 minutes' ");

    if (PQresultStatus(Res) != PGRES_TUPLES_OK)
    {
      LogPrint("*** %s FATAL error: %s ***\n", ProcessName, PQerrorMessage(Conn));
      LogPrint("%s exiting\n", ProcessName);
      exit(1);
    } 

    if (PQntuples(Res) == 0)
    {
      /* scheduler is dead. Log restart */
      LogPrint("*** Scheduler not responding: killing and restarting ***\n");
      
      /* kill the scheduler and clean up locks, db */
      StopScheduler(1);

      // Restart scheduler as daemon, reset job queue
      rv = system( LIBEXECDIR "/fossology-scheduler -dR");
      if (-1 == rv)
      {
        LogPrint("*** Scheduler restart failed ***\n");
        LogPrint("*** Error: %s ***\n", strerror(errno));
      }
      else
      {
        LogPrint("*** Scheduler restarted successfully by %s ***\n", ProcessName);
      }
    }
    PQclear(Res);
  }    /* while(1) */
} /* main() */
