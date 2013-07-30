/***************************************************************
 regexscan: Scan file(s) for regular expression(s)

 Copyright (C) 2007-2013 Hewlett-Packard Development Company, L.P.

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

 -------------------------------------------

 regexscan - A Fossology agent creation "Tutorial" in multiple stages.
   This is stage 1 and demonstrates the fundamental agent requirements:

    1. Connect to database
    2. Connect to Scheduler
    3. Terminate

 ***************************************************************/
#include <stdlib.h>
#include <stdio.h>
#include <unistd.h>
#include <string.h>
#include <ctype.h>
#include <signal.h>
#include <libgen.h>

#include "libfossology.h"

#define MAXCMD 4096
char SQL[256];

#define myBUFSIZ  2048

/*
#ifdef SVN_REV
char BuildVersion[]="Build version: " SVN_REV ".\n";
#endif
*/

PGconn *pgConn = NULL;  // Database connection

/*********************************************************
 Usage():
 *********************************************************/
void    Usage   (char *Name)
{
  printf("Usage: %s [options] [id [id ...]]\n",Name);
  printf("  -i        :: initialize the database, then exit.\n");
  printf("  -c SYSCONFDIR :: FOSSology configuration directory.\n");
  printf("  -h        :: show available command line options.\n");
  printf("  -v        :: increase agent logging verbosity.\n");
} /* Usage() */

/*********************************************************/
int	main	(int argc, char *argv[])
{
  int c;

  char *SVN_REV;
  char *VERSION;
  char agent_rev[myBUFSIZ];

  /* connect to scheduler.  Noop if not run from scheduler.  */
  fo_scheduler_connect(&argc, argv, &pgConn);

/*
  Version reporting.
*/
  SVN_REV = fo_sysconfig("regexscan", "SVN_REV");
  VERSION = fo_sysconfig("regexscan", "VERSION");
  sprintf(agent_rev, "%s.%s", VERSION, SVN_REV);

  fprintf(stdout, "regexscan reports version info as '%s.%s'.\n", VERSION, SVN_REV);

  /* Process command-line */
  while((c = getopt(argc,argv,"civ")) != -1)
  {
    switch(c)
    {
    case 'c':
      break;  /* handled by fo_scheduler_connect()  */
    case 'i':
      PQfinish(pgConn);
      return(0);
    case 'v':
      agent_verbose++;
      break;
    case 'h':
    default:
      Usage(argv[0]);
      fflush(stdout);
      PQfinish(pgConn);
      exit(-1);
    }
  }

  PQfinish(pgConn);
  fo_scheduler_disconnect(0);

  return 0;
} /* main() */

