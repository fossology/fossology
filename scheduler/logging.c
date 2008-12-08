/*******************************************************
 logging.c: Functions for handling system logs.

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

 ==========
 Definitions of terms:
 - Child :: a spawned process. Parent spawns children.
 - Agent :: a child that performs a task for the scheduler.
 In general, all children are agents and vice versa.
 The difference in terms denotes the different levels of interaction.
 In particular, Agents are high-level and denote functionality.
 Children are low-level and denote basic communication.

 ==========
 Known bugs and workarounds:
   syslog is not signal-safe!  There can be a race condition!
   This has been seen by other people:
	http://kerneltrap.org/mailarchive/git/2008/7/3/2335624
	http://linux.derkeiler.com/Mailing-Lists/Kernel/2007-09/msg08633.html
	http://linux.derkeiler.com/Mailing-Lists/Kernel/2007-09/msg08759.html
   Here's the problem (as far as I can tell):
   When the child dies, closelog() is called.  This sets a lock.
   However, there may be a delay between the handle locking
   and the next parent call to syslog().
   This is a race condition.
   The solution:
     Do NOT use syslog.
   The workaround:
     Manage my own logfile.
     -L overwrites the default location (see LogOpen).
     SIGHUP tells the system to refresh the logfile (for log rotations).
 *******************************************************/

#include <stdlib.h>
#include <stdio.h>
#include <unistd.h>
#include <errno.h>
#include <string.h>
#include <ctype.h>
#include <time.h>
#include <sys/types.h>
#include <sys/wait.h>
#include <fcntl.h>
#include <sys/stat.h>
#include <stdarg.h>

#include <libfossdb.h>
#include "debug.h"
#include "scheduler.h"
#include "dbstatus.h"
#include "logging.h"

FILE	*Log=NULL;
char	*LogFile=LOGDIR;
int	LogReopenFlag=0;
int	LogUseSyslog=0;

/********************************************
 LogOpen(): Open or Re-open the system logfile.
 ********************************************/
void	LogOpen	()
{
  struct stat Stat;

  if ((Log != NULL) && (Log != stderr) && (Log != stdout))
    {
    fclose(Log);
    Log=NULL;
    }

  /* Check the type of logfile.
     Two options:
     1. LogFile is a file.  Use it.
     2. LogFile is a directory.  Open LogFile/fossology.log.
     3. No LogFile.  Use syslog. (Bwa ha ha ha)
     Unless the user does something weird with the command-line -L,
     the default LogFile is already set to option 1 or 2.
   */
  if (!LogFile || !LogFile[0])
    {
    fprintf(stderr,"FATAL: Bad logfile (no name)\n");
    DBclose(DB);
    exit(1);
    }

  /* check for special names */
  if (!strcmp(LogFile,"stderr")) { Log = stderr; return; }
  if (!strcmp(LogFile,"stdout")) { Log = stdout; return; }

  /* What am I looking at? File or directory... */
  if (stat(LogFile,&Stat) != 0)
    {
    /* Cannot stat it.  Assume it is a file. */
    Log = fopen(LogFile,"a");
    }
  /* Check if it is a directory */
  else if (S_ISDIR(Stat.st_mode))
    {
    char Path[1024];
    snprintf(Path,sizeof(Path),"%s/fossology.log",LogFile);
    Log = fopen(Path,"a");
    }
  else
    {
    Log = fopen(LogFile,"a");
    }

  /* Check the logfile */
  if (!Log)
    {
    perror("Logfile failure");
    fprintf(stderr,"FATAL: Unable to log to logfile '%s'\n",LogFile);
    exit(1);
    }

  LogPrint("Log opened\n");
  return;
} /* LogOpen() */

/********************************************
 LogPrint(): Like printf, but for logs!
 ********************************************/
int	LogPrint	(const char *fmt, ...)
{
  va_list Args;
  int rc;
  time_t Now;
  struct tm *TimeData;
  char TimeString[40];

  if (!Log) LogOpen();
  if (!fmt) return(0);

  Now = time(NULL);
  TimeData = localtime(&Now);
  strftime(TimeString,sizeof(TimeString),"%F %T",TimeData);

  va_start(Args,fmt);
  fprintf(Log,"%s scheduler[%d] : ",TimeString,getpid());
  rc=vfprintf(Log,fmt,Args);
  va_end(Args);
  fflush(Log);
  return(rc);
} /* LogPrint() */

