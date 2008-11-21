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
 UnlockScheduler(): Unlock the scheduler memory.
 Returns: 0 on success, non-zero on failure.
 ********************************************/
int	UnlockScheduler	()
{
  return(shm_unlink("fossology-scheduler"));
} /* UnlockScheduler() */

/********************************************
 LockScheduler(): Make sure only one scheduler is
 running on this system.
 Returns 0 if the lock is set by this function.
 If the lock is not set, returns the PID of the
 scheduler that is holding the lock.
 ********************************************/
pid_t	LockScheduler	()
{
  int Handle;
  int rc;
  pid_t Pid;
  char S[10];

#if 0
  shm_unlink("fossology-scheduler");
  exit(1);
#endif

  Handle = shm_open("fossology-scheduler",O_RDWR|O_CREAT|O_EXCL,0744);

  if (Handle >= 0)
    {
    /* This is my memory! Store PID */
    memset(S,'\0',sizeof(S));
    snprintf(S,sizeof(S),"%d",getpid());
    write(Handle,S,10);
    return(0);
    }

  /* Check why it failed... */
  rc=errno;
  switch(rc)
    {
    case EACCES:
    case EINVAL:
    case EMFILE:
    case ENAMETOOLONG:
    case ENFILE:
    case ENOENT:
	perror("FATAL shm_open");
	fprintf(stderr,"FATAL: shm_open set errno=%d\n",rc);
	exit(1);
    case EEXIST:
    default:
	break;
    }

  /* Someone else owns it!  Find out who! */
  Handle = shm_open("fossology-scheduler",O_RDONLY,0444);
  if (Handle < 0)
    {
    rc = errno;
    perror("FATAL: shm_open");
    fprintf(stderr,"FATAL: shm_open failed with errno=%d\n",rc);
    exit(1);
    }
  read(Handle,S,10);
  Pid = atoi(S);

  /* See if pid exists */
  rc = kill(Pid,0); /* no signal, just checking */
  if ((rc == -1) && (errno == ESRCH))
    {
    /* Does not exist.  Try again */
    if (UnlockScheduler() == 0)
      {
      Pid = LockScheduler();
      }
    }
  return(Pid);
} /* LockScheduler() */

