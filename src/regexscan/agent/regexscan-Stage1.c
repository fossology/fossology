/*
 regexscan: Scan file(s) for regular expression(s)
 
 SPDX-FileCopyrightText: © 2007-2013 Hewlett-Packard Development Company, L.P.

 SPDX-License-Identifier: GPL-2.0-only
*/
/**
 * \file
 * \brief Stage 1 demonstration
 *
 * This is stage 1 and demonstrates the fundamental agent requirements:
 * -# Connect to database
 * -# Connect to Scheduler
 * -# Terminate
 */

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
#ifdef COMMIT_HASH
char BuildVersion[]="Build version: " COMMIT_HASH ".\n";
#endif
*/

PGconn *pgConn = NULL;  // Database connection

/**
 Usage():
 */
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

  char *COMMIT_HASH;
  char *VERSION;
  char agent_rev[myBUFSIZ];

  /* connect to scheduler.  Noop if not run from scheduler.  */
  fo_scheduler_connect(&argc, argv, &pgConn);

/*
  Version reporting.
*/
  COMMIT_HASH = fo_sysconfig("regexscan", "COMMIT_HASH");
  VERSION = fo_sysconfig("regexscan", "VERSION");
  sprintf(agent_rev, "%s.%s", VERSION, COMMIT_HASH);

  fprintf(stdout, "regexscan reports version info as '%s.%s'.\n", VERSION, COMMIT_HASH);

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

