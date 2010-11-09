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
#include <pwd.h>
#include <grp.h>

#include <libfossdb.h>

#include "sched_utils.h"
#include "lockfs.h"
#include "logging.h"

char SchedName[]="fossology-scheduler";

/************************************************************************
 * Stop the scheduler (killsched =1)
 * or just cleanup the scheduler (killsched = 0)
 * Clean up the scheduler_status table
 * Return  0 Success
 *        -1 Failure (see log file for messages)
 */
int StopScheduler(int killsched)
{
  pid_t Pid;
  int   rc;
  int   rv = 0;

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
        rv = -1;
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

  /* remove all scheduler_status records */
  assert(DB);
  DBaccess2(DB, "DELETE from scheduler_status");
  if (!strstr(DBstatus(DB), "OK"))
    fprintf(stderr, "*** StopScheduler DELETE from scheduler_status. Status %s, %s ***\n", DBstatus(DB), DBerrmsg(DB));
  
  return(rv);
}


/************************************************************************
 * Stop the scheduler watchdog.
 * Return  0 Success
 *        -1 any errors (see log file for messages)
 */
int StopWatchdog()
{
  char *WatchdogName = "fo_watchdog";
  pid_t Pid;
  int   rc;
  int   rv = 0;

  Pid = LockGetPID(WatchdogName);
  if (Pid)
  {
    rc = kill(Pid, SIGKILL);
    if (rc == -1)
    {
      fprintf(stderr, "*** Unable to kill %s PID %d. %s  ***\n", WatchdogName, Pid, strerror(errno));
      rv = -1; 
    } 
    else 
      fprintf(stderr, "*** Exit %s PID %d  ***\n", WatchdogName, Pid);

    if (UnlockName(WatchdogName))
    {
      fprintf(stderr, "*** Unlock %s PID %d failed. %s  ***\n", WatchdogName, Pid, strerror(errno));
      rv = -1;
    }
  }

  return(rv);
}


/************************************************************************
 * Set PROJECTUSER:PROJECTGROUP
 * Returns none.  This exits if the user and group cannot be set
 */
void SetPuserPgrp(char *ProcessName)
{
  struct group *Grp;
  struct passwd *Pwd;

  /* make sure group exists */
  Grp = getgrnam(PROJECTGROUP);
  if (!Grp)
  {
    fprintf(stderr,"FATAL: Group PROJECTGROUP '%s' not found.  Aborting.\n",PROJECTGROUP);
    exit(-1);
  }

  /* set PROJECTGROUP */
  setgroups(1,&(Grp->gr_gid));
  if ((setgid(Grp->gr_gid) != 0) || (setegid(Grp->gr_gid) != 0))
  {
    fprintf(stderr,"%s error: You need to run this as root or %s.  Set group '%s' aborting due to error: %s.\n",
            ProcessName, PROJECTUSER, PROJECTGROUP, strerror(errno));
    exit(-1);
  }

  /* run as PROJECTUSER */
  /* make sure PROJECTUSER exists */
  Pwd = getpwnam(PROJECTUSER);
  if (!Pwd)
  {
    fprintf(stderr,"FATAL: User '%s' not found.  %s will not run as root.  Aborting.\n",
          PROJECTUSER, ProcessName);
    exit(-1);
  }
  
  /* Run as PROJECTUSER, not root or any other user  */
  if ((setuid(Pwd->pw_uid) != 0) || (seteuid(Pwd->pw_uid) != 0))
  {   
    fprintf(stderr,"%s error: You must run this as root or %s.  SETUID aborting due to error: %s\n",
            ProcessName, PROJECTUSER, strerror(errno));
    exit(-1);
  } 
}
