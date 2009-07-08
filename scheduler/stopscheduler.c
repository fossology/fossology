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

 *******************************************************/

#include <stdlib.h>
#include <unistd.h>
#include <stdio.h>
#include <string.h>
#include <assert.h>
#include <signal.h>
#include <errno.h>

#include <libfossdb.h>

#include "stopscheduler.h"
#include "lockfs.h"
#include "logging.h"

char SchedName[]="fossology-scheduler";


/************************************************************************
 * Stop the scheduler (killsched), and/or its watchdog (killwatch).
 * or just kill the watchdog and cleanup the scheduler (killsched = 0)
 * Clean up the scheduler_status table
 * Caller must exit.
 * Return  0 Success
 *        -1 Failure (see log file for messages)
 */
int StopScheduler(int killsched, int killwatch)
{
  char *WatchdogName = "fo_watchdog";
  pid_t Pid;
  int   rc;

killwatch = 0;
  if (killwatch)
  {
    /* kill the watchdog first, so that it doesn't restart a purposly stopped scheduler */
    Pid = LockGetPID(WatchdogName);
    if (Pid)
    {
      rc = kill(Pid, SIGKILL);
      if (rc == -1)
        fprintf(stderr, "*** Unable to kill %s PID %d. %s  ***\n", WatchdogName, Pid, strerror(errno));
      else
        fprintf(stderr, "*** Exit %s PID %d  ***\n", WatchdogName, Pid);
      if (UnlockName(WatchdogName))
        fprintf(stderr, "*** Unlock %s PID %d failed. %s  ***\n", WatchdogName, Pid, strerror(errno));
    }
  }


  /* clean up the scheduler  
   * Note that if the pid in the lock file is stale, a scheduler
   * could be left running.  
   */
  Pid = LockGetPID(SchedName);
  if (Pid)
  {
    if (killsched)  /* kill sched if requested */
    {
      /* as long as the running scheduler is still responding to interrupts, 
       * SIGQUIT will send SIGKILL's to each agent and run StopScheduler(0,0) from
       * the "active" scheduler to do cleanup.  This recursion is really an artifact
       * of StopScheduler being a general purpose function and getting called through
       * multiple scheduler failure and quit modes.
       * NOTE, that there may be one or two schedulers running.
       * One is the active (job running) scheduler, the other a scheduler started to kill the
       * active scheduler (as in fossology-scheduler -k).  SIGTERM/QUIT/KILL will stop the active.
       */
      rc = kill(Pid, SIGQUIT);
      if (rc == -1)
      {
        fprintf(stderr, "*** Unable to SIGQUIT %s PID %d. %s  ***\n", SchedName, Pid, strerror(errno));
        LogPrint("*** Unable to SIGQUIT %s PID %d. %s  ***\n", SchedName, Pid, strerror(errno));
      }
      else
      {
        fprintf(stderr, "*** Exiting %s PID %d  ***\n", SchedName, Pid);
        LogPrint("*** Exiting %s PID %d QUIT ***\n", SchedName, Pid);
      }
      sleep(30); /* give sigquit time to kill agents */
      kill(Pid, SIGKILL);  /* just in case */
    }

    /* ignore any unlock error since SIGQUIT calls stopscheduler, the pid may have already
     * been unlocked.
     */
    UnlockName(SchedName);
  }


  /* remove all scheduler_status records
   */
  assert(DB);
  DBaccess2(DB, "DELETE from scheduler_status");
  if (!strstr(DBstatus(DB), "OK"))
    fprintf(stderr, "*** StopScheduler DELETE from scheduler_status. Status %s, %s ***\n", DBstatus(DB), DBerrmsg(DB));
  
	DBclose(DB);
  return(0);
}
