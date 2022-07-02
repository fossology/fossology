/*
 regexscan: Scan file(s) for regular expression(s)
 
 SPDX-FileCopyrightText: © 2007-2013 Hewlett-Packard Development Company, L.P.

 SPDX-License-Identifier: GPL-2.0-only
*/
/**
 * \file
 * \brief Stage 2 demonstration
 *
 * This is stage 2 and demonstrates the fundamental agent requirements:
 * -# Connect to database
 * -# Connect to Scheduler
 * -# Process -r as a regex argument
 * -# Process filename and report regex scan results
 * -# Terminate
 */

#include <stdlib.h>
#include <stdio.h>
#include <unistd.h>
#include <string.h>
#include <ctype.h>
#include <signal.h>
#include <libgen.h>

#include <regex.h>
#include <stdbool.h>

#include "libfossology.h"

#define MAXCMD 4096
char SQL[256];

#define myBUFSIZ  2048

/*
#ifdef COMMIT_HASH
char BuildVersion[]="Build version: " COMMIT_HASH ".\n";
#endif
*/

PGconn *pgConn = NULL;  ///< Database connection

/**
 * \brief Regex scanner
 * \param regexStr    String containing the regex
 * \param scanFilePtr Handle for file to scan
 * \param fileName    String name of file to scan
 * \return 0 = OK, otherwise error code
 */
int regexScan(char *regexStr, FILE *scanFilePtr, char *fileName)
{
  int retCode;

  regex_t regex;
  bool match = false;   /* regex match found indicator */

  char msgBuff[250];
  char textBuff[2000];  /* line buffer for regex match processing */

  regmatch_t  rm[1];
  int  lineCount = 0;

  /* Compile the regex for improved performance */
  retCode = regcomp(&regex, regexStr, REG_ICASE+REG_EXTENDED);
  if (retCode)
  {
    fprintf(stderr, "regex %s failed to compile\n", regexStr);
    return 1;
  }

  /* Now scan the file for regex line by line */
  while (fgets(textBuff, 1024, scanFilePtr) != NULL)
  {
    lineCount++;    /* Another line read */
    retCode = regexec(&regex, textBuff, 1, rm, 0);  /* nmatch = 1, matchptr = rm */
    if (!retCode)
    {
      sprintf(msgBuff, "%s: regex found at line %d at position %d. -> %.*s \n",
              fileName, lineCount, rm[0].rm_so+1, rm[0].rm_eo-rm[0].rm_so, textBuff + rm[0].rm_so);
      puts(msgBuff);
      if (!match)
      {
        match = true;   /* Indicate we've had at least one match */
      }
    }
    else if (retCode == REG_NOMATCH)
    {
      /* Skip the "no match" retCode */
    }
    else
    {
      regerror(retCode, &regex, msgBuff, sizeof(msgBuff));
      fprintf(stderr, "Out of memory? - regex match failure: %s\n", msgBuff);
      fclose(scanFilePtr);
      return 3;
    }
  }

  /* Report if no matches found */
  if (!match)
  {
    sprintf(msgBuff, "%s: %s not found\n", fileName, regexStr);
    puts(msgBuff);
  }

  /* clean up and exit */
  regfree(&regex);
  fclose(scanFilePtr);
  return 0;
}


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
  printf("  -r        :: regex expression to load from command line.\n");
  printf("  filename  :: filename to process with regex.\n");
} /* Usage() */

/*********************************************************/
int	main	(int argc, char *argv[])
{
  int index, nonoptargs = 0;

  int  c;

  regex_t regex;
  char regexStr[1024];  /* string storage for the regex expression */

  FILE *scanFilePtr;
  char fileName[1000];

  int   user_pk;
  long  UploadPK=-1;

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
#ifdef REGEX_DEBUG
  fprintf(stdout, "regexscan reports version info as '%s.%s'.\n", VERSION, COMMIT_HASH);
#endif

  /* Process command-line */
  while((c = getopt(argc,argv,"chir:v")) != -1)
  {
    switch(c)
    {
    case 'c':
      break;  /* handled by fo_scheduler_connect()  */
    case 'i':
      PQfinish(pgConn);
      return(0);
    case 'r':
      sprintf(regexStr, "%s", optarg);
      break;
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

  /* process filename after switches */
  for (index = optind; index < argc; index++)
  {
/*  process no option arguments
    printf("Non-option argument %s\n", argv[index]);  /* diag display */
    nonoptargs++;
  }

  if (nonoptargs == 0)
  {
    /* Assume it was a scheduler call */
    user_pk = fo_scheduler_userID();
    while(fo_scheduler_next())
    {
      UploadPK = atol(fo_scheduler_current());

      printf("UploadPK is: %ld\n", UploadPK);
    }
  }
  else
  {
    /* File access initialization */
    sprintf(fileName, "%s", argv[optind]);      /* Grab first non-switch argument as filename */
    scanFilePtr = fopen(fileName, "r");
    if (!scanFilePtr)
    {
      fprintf(stderr, "failed to open text inout file %s\n", fileName);
      regfree(&regex);
      return 2;
    }

  /* Call scan function */
  regexScan(regexStr, scanFilePtr, fileName);
  }

  PQfinish(pgConn);
  fo_scheduler_disconnect(0);

  return 0;
} /* main() */

