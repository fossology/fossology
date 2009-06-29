/*******************************************************
 lockfs.c: Functions for locking the fossology scheduler

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
#include <sys/types.h>
#include <errno.h>

#include <sys/mman.h>
#include <fcntl.h>

#include "debug.h"
#include "scheduler.h"
#include "logging.h"

/********************************************
 LockGetPID() returns PID of process that 
 owns a lock (or zero if there is no lock).
 ********************************************/
pid_t	LockGetPID	(char *ProcessName)
{
  pid_t Pid = 0;
  int Handle;
  char S[10];

  Handle = shm_open(ProcessName,O_RDONLY,0444);
  if (Handle < 0)
  {
    /* don't report error if lock file does not exist.  That may be normal.  */
    if (errno != ENOENT)
      LogPrint("*** failed to open lock file for %s (see LockGetPID). %s\n", ProcessName, strerror(errno));
    return(0);
  }

  /* Find out who owns it.  */
  read(Handle,S,10);
  Pid = atoi(S);
  if (Pid < 2)
  {
    /* bogus pid, remove lockfile */
    if (shm_unlink(ProcessName) == -1)
      LogPrint("*** failed to remove invalid lock file for %s (see LockGetPID). %s\n", ProcessName, strerror(errno));
    return(0);
  }

  if (Verbose) LogPrint("DEBUG: Successfully found PID[%s] in lock for %s.\n",S, ProcessName); 
  return(Pid);
} /* LockGetPID() */

/********************************************
 UnlockName(): Unlock the scheduler shared memory file.
 Returns: 0 on success, non-zero on failure.
 ********************************************/
int	UnlockName	(char *ProcessName)
{
  return(shm_unlink(ProcessName));
} /* UnlockName() */

/********************************************
 LockName(): Create a shm lock for ProcessName
 Returns: 0 Success, the lock was set by this function.
          >0 PID of the process that already has the lock.
          <0 Error, see logfile
 ********************************************/
pid_t	LockName	(char *ProcessName)
{
  static int RecurseCheck=0;
  int Handle;
  int rc, rv;
  pid_t Pid = 0;
  char S[10];

  /* Create the lock file */
  Handle = shm_open(ProcessName,O_RDWR|O_CREAT|O_EXCL,0744);
  if (-1 == Handle)
  {
    /* create failed */
    if (errno == EEXIST)  /* create failed because file exists */
    {
      /* At this point, we have an existing lock file.
       * Make sure the pid in the lock file still exists.
       * If it doesn't, remove the file and try to lock again.
       */
      Pid = LockGetPID(ProcessName);
      if (!Pid) return (-1);

      rc = kill(Pid,0); /* check if pid exists */
      if (0 == rc)
      {
        /* pid is good, lock is good, return the pid */
        return(Pid);
      }
      else
      {
        /* PID in lock is stale.
         * Remove the lock and try again (1 time).
         */
        LogPrint("*** PID[%d] (%s) may be stale. Attempt to unlock. \n",Pid, ProcessName);
        if (UnlockName(ProcessName))
        {
          /* Unlock failed */
          LogPrint("*** %s PID[%d] is stale.  Unlock failed %s\n",ProcessName, Pid, strerror(errno));
          return (-1);
        }
        else
          LogPrint("*** %s  Unlock successful ***\n",ProcessName);

        /* File is unlocked, try, one time,  to lock again */
        if (!RecurseCheck)
        {
          RecurseCheck = 1;
          return (LockName(ProcessName));
        }
        else
        {
          LogPrint("*** %s Unlock failed (max recursion depth = 1)\n",ProcessName);
          return (-1);
        }
      }
    }
    else
    {
      /* create failed for reasons besides the file already existing */
      LogPrint("*** %s failed on shm_open. %s\n", ProcessName, strerror(errno));
      return (-1);
    }
  }
  else
  {
    /* New lock file created.
       Store the PID and return.
     */
    snprintf(S,sizeof(S),"%d          ",getpid());
    if (Verbose) fprintf(stderr,"DEBUG: Storing PID[%s] in lock for %s.\n",S, ProcessName); 
    rv = write(Handle,S,10);
    if (rv < 1)
    {
      LogPrint("*** %s failed to write pid to lock file.  %s\n", ProcessName, strerror(errno));
      return(-1);
    }
    return(0);  // Successful new lock file
  }

  /* No execution path should get here  */
  LogPrint("FATAL: LockName(%s) should't not be able to reach this code.\n", ProcessName);
  return (-1);

} /* LockName() */

