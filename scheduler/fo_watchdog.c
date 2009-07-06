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
#include "lockfs.h"
#include <libfossdb.h>


/************************************************************************/
/************************************************************************/
/** Program *************************************************************/
/************************************************************************/
/************************************************************************/

int	main	(int argc, char *argv[])
{
  int rv;
  pid_t mypid = -1;
  char *ProcessName="fo_watchdog";

  /* start as daemon */
  if (daemon(0,(LogFile!=NULL)) != 0)
  {
    LogPrint("*** %s exiting due to failure to start as a daemon. %s ***\n", ProcessName, strerror(errno));
    rv = UnlockName(ProcessName);
    if (rv) LogPrint("*** Unlocking %s failed, %s ***\n", ProcessName, strerror(errno));
  }

  /* store lock for this process.
   * If another fo_watchdog is running, just exit
   */
  rv = LockName(ProcessName);
  if (rv < 0)
  {
    LogPrint("*** %s lock error, see log file ***\n", ProcessName);
    exit(-1);
  }

  if (rv == 0)
  {
    if (Verbose) 
      LogPrint("*** New %s successfully locked ***\n", ProcessName);
  }
  else
  {
    if (Verbose) 
      LogPrint("*** %s (pid %d) already running.  No need to start another.  ***\n", ProcessName, rv);
    exit(0);
  }
  
  mypid = getpid();
  LogPrint("*** %s daemon started. PID %d ***\n", ProcessName, mypid);

  DB = DBopen();
  if (!DB)
  {
    LogPrint("FATAL: %s unable to connect to database.  Terminating.\n", ProcessName);
    exit(-1);
  }
  
  while(1)
  {
    /* Check every 5 minutes to see if the scheduler is updating the scheduler_status table */
    sleep(5*60);

    DBaccess(DB, "SELECT record_update from scheduler_status where agent_number='-1' and (now()-record_update) > '4 minutes' ");
    if (DBdatasize(DB) > 0)
    {
      /* scheduler is dead. Log restart */
      LogPrint("*** Scheduler not responding: killing and restarting ***\n");
      
      /* kill the scheduler and clean up locks, db */
      StopScheduler(1,0);

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
  }    /* while(1) */
} /* main() */
