/*
 regexscan: Scan file(s) for regular expression(s)
 
 SPDX-FileCopyrightText: © 2013 Hewlett-Packard Development Company, L.P.

 SPDX-License-Identifier: GPL-2.0-only
*/
/*!
 * \dir
 * \brief Sample scanner with multiple stages.
 * \page regexscan Regex Scanner
 * \tableofcontents
 * \section regexscanabout About Regex Scanner
 * A Fossology agent creation "Tutorial" in multiple stages.
 *
 * This is stage 3 and demonstrates the fundamental agent requirements:
 * -# Connect to database
 * -# Connect to Scheduler
 * -# Process -r as a regex argument
 * -# Process filename and report regex scan results from command line
 * -# Process upload file collection from upload_pk stdin input
 * -# Terminate
 * \section regexscanactions Supported actions
 * Usage: `regexscan [options] [id [id ...]]`
 * | Command line flag | Description |
 * | ---: | :--- |
 * | -i        | Initialize the database, then exit |
 * | -c SYSCONFDIR | FOSSology configuration directory |
 * | -h        | Show available command line options |
 * | -v        | Increase agent logging verbosity |
 * | -r        | Regex expression to load from command line |
 * | filename  | Filename to process with regex |
 * \section regexscansource Agent source
 *   - \link src/regexscan/agent \endlink
 */

/*!
 * \file regexscan.c
 * \brief Regular expression scanning agent. Tutorial sample code
 * \author Paul Guttmann
 * \date 2013
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
  \brief Scan a file for a regex - regular expression.
  \n the regex is compiled in this function for performance

  \param regex holds the compiled regular expression
  \param regexStr string containing the regex
  \param scanFilePtr    handle for file to scan
  \param fileName string name of file to scan
  \return 0 = OK, otherwise error code
 */
int regexScan(regex_t *regex, char *regexStr, FILE *scanFilePtr, char *fileName)
{
  int retCode;

//  regex_t regex;
  bool match = false;   /* regex match found indicator */

  char msgBuff[2500];
  char textBuff[2000];  /* line buffer for regex match processing */

  regmatch_t  rm[1];
  int  lineCount = 0;

  /* Now scan the file for regex line by line */
  while (fgets(textBuff, 1024, scanFilePtr) != NULL)
  {
    lineCount++;    /* Another line read */
    retCode = regexec(regex, textBuff, 1, rm, 0);  /* nmatch = 1, matchptr = rm */
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
      regerror(retCode, regex, msgBuff, sizeof(msgBuff));
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
//  regfree(&regex);
  fclose(scanFilePtr);
  return 0;
}

/**
  \brief Creates filenames from pfile_pk value

  \param pfileNum string containing pfile_pk value
  \param pfileRepoName string with repo path and filename
  \param pfileRealName string with original filename
  \return 0 = OK, otherwise error code
 */
int pfileNumToNames(char *pfileNum, char *pfileRepoName, char *pfileRealName)
{
  char sqlSelect[256];
  PGresult *result;

  /* Attempt to locate the appropriate pFile_pk record */
  sprintf(sqlSelect, "SELECT pfile_sha1, pfile_md5, pfile_size, ufile_name FROM"
      " pfile, uploadtree WHERE pfile_fk = pfile_pk and pfile_pk = '%s'", pfileNum);
  result = PQexec(pgConn, sqlSelect);

  if (fo_checkPQresult(pgConn, result, sqlSelect, __FILE__, __LINE__)) return 0;

  /* confirm a sane result set */
  if (PQntuples(result) == 0)
  {
    PQclear(result);

    /* Not found */
    fprintf(stderr, "Database does not contain pfile_pk: %s\n", pfileNum);
    return 1;
  }
  else if (PQntuples(result) != 1)
  {
    PQclear(result);

    /* Not found */
    fprintf(stderr, "Database contains multiple  pfile_pk: %s\n", pfileNum);
    return 2;
  }
  /* We've managed to locate the one and only pfile_pk record. Build the filePath string */
  /* Concatenate first row fields 0, 1 and 2 */
  sprintf(pfileRepoName, "%s.%s.%s", PQgetvalue(result, 0, 0), PQgetvalue(result, 0, 1), PQgetvalue(result, 0, 2));
  /* and extract the actual filename from field 4 - uploadtree.ufile_name */
  sprintf(pfileRealName, "%s", PQgetvalue(result, 0, 3));

//  fprintf(stderr, "fileName is:%s\n", pFileName);
  PQclear(result);
  return 0;
}


/**
  \brief Scan an Upload for a regex - regular expression.
  \n gets a list of files in an upload and calls regexScan()

  \param uploadNum string containing upload_pk value
  \param regexStr string containing the regex
  \return i = number of files scanned, 0 = error
 */
int regexScanUpload(char *uploadNum, char *regexStr)
{
  char sqlSelect[256];
  PGresult *result, *pfileResult;

  int fileCount, i, retCode;

  char fileRealName[1000];
  char fileRepoName[1000];

  FILE *scanFilePtr;

  regex_t regex;

  /* Ensure uploadNum is "valid" then obtain a list of pfile entries and scan them */
  sprintf(sqlSelect, "SELECT upload_pk, upload_mode, upload_filename  from upload where upload_pk = '%s'", uploadNum);
  result = PQexec(pgConn, sqlSelect);

  if (fo_checkPQresult(pgConn, result, sqlSelect, __FILE__, __LINE__)) return 0;

  /* confirm a sane result set */
  if (PQntuples(result) == 0)
  {
    fprintf(stderr, "No uploads appear to be available here!\n");
    PQclear(result);
    return 0;   /* nothing found to scan */
  }

  /* Next ensure that uploadNum was successfully uploaded */
  /* We'll only look at upload_pk entries that have successfully run ununpack (64) and adj2nest (32) */
  if ((atoi(PQgetvalue(result, 0, 1)) & 96) != 96)
  {
    fprintf(stderr, "Upload %s was not successfully processed after upload!\n", uploadNum);
    PQclear(result);
    return 0;   /* nothing found to scan */
  }

  /* Now get our list of required pfile entries for this upload */
  sprintf(sqlSelect, "SELECT uploadtree.pfile_fk, ufile_name from uploadtree, upload"
      " where upload_fk = upload_pk and uploadtree.pfile_fk <> 0 and ufile_mode = 32768 and upload_pk = '%s'", uploadNum);
  result = PQexec(pgConn, sqlSelect);

  if (fo_checkPQresult(pgConn, result, sqlSelect, __FILE__, __LINE__)) return 0;

  fileCount = PQntuples(result);
//  fprintf(stderr, "Located %d files to process.\n", fileCount);

  /* Compile the regex for improved performance */
  retCode = regcomp(&regex, regexStr, REG_ICASE+REG_EXTENDED);
  if (retCode)
  {
    fprintf(stderr, "regex %s failed to compile\n", regexStr);
    return 1;
  }

  /* Scan the files we've found for this upload */
  for (i=0; i<fileCount; i++)
  {
    /* Attempt to locate the appropriate pFile_pk record */
    sprintf(sqlSelect, "SELECT pfile_sha1, pfile_md5, pfile_size, ufile_name"
            " FROM pfile, uploadtree WHERE pfile_fk = pfile_pk and pfile_pk = '%s'", PQgetvalue(result, i, 0));
    pfileResult = PQexec(pgConn, sqlSelect);

    if (fo_checkPQresult(pgConn, pfileResult, sqlSelect, __FILE__, __LINE__)) return 0;

    /* confirm a sane result set */
    if (PQntuples(pfileResult) == 1)
    {
      /* For each pfile value grind through the regex scan process */

      /* Locate and construct the appropriate full name from pfile table based upon pfile_pk value */
      if (pfileNumToNames(PQgetvalue(result, i, 0), fileRepoName, fileRealName) != 0)
      {
        fprintf(stderr, "ERROR: Unable to locate pfile_pk '%s'\n", PQgetvalue(result, i, 0));
        return 0;
      }

      /* Use fo_RepFread() for access. It uses fo_RepMkPath() to map name to full path. */
      scanFilePtr = fo_RepFread("files", fileRepoName);
      if (!scanFilePtr)
      {
        fprintf(stderr, "ERROR: Unable to open '%s/%s'\n", "files", fileRepoName);
        return 0;
      }

    /* Call scan function. Note that we'll need to "Humanize" the fileName at some point. */
    regexScan(&regex, regexStr, scanFilePtr, fileRealName);
    }
    else
    {
      fprintf(stderr, "WARNING: File: %s - Located %d instances of pfile_pk %s ! Size = %s bytes!\n",
              PQgetvalue(result, i, 1), PQntuples(pfileResult), PQgetvalue(result, i, 0), PQgetvalue(pfileResult, i, 2));
    }
  }
  /* return the number of scanned files */
  return i;
}


/**
 Usage():
 \brief Usage description for this regexscan agent.
 \param Name Path of the binary
 */
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
  int  nonoptargs;
  int  c, retCode;

  regex_t regex;

  char regexStr[1024];  /* string storage for the regex expression */
  bool regexSet = false;

  char fileName[1000];
  FILE *scanFilePtr;

  char uploadNum[10];

  int  scannedCount = 0;

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
      regexSet = true;
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

  /* Sanity check for regex value required here. */
  if (!regexSet)
  {
    fprintf (stderr, "No regex value has been requested!\n");
    PQfinish(pgConn);
    fo_scheduler_disconnect(0);
    return 1;
  }

  /* process filename after switches. How many non-option arguments are there ? */
  nonoptargs = argc - optind; /* total argument count minus the option count */

  if (nonoptargs == 0)
  {
    /* Assume it was a scheduler call */
    user_pk = fo_scheduler_userID();

    while(fo_scheduler_next())
    {
      UploadPK = atol(fo_scheduler_current());

      printf("UploadPK is: %ld\n", UploadPK);
      sprintf(uploadNum, "%ld", UploadPK);
	    scannedCount = regexScanUpload(uploadNum, regexStr);
      if (scannedCount == 0)
      {
        fprintf(stderr, "Failed to successfully scan: upload - %s!\n", uploadNum);
      }
    }
  }
  else
  {
    /* File access initialization - For Stage 3 use first arg as fileName */
    sprintf(fileName, "%s", argv[optind]);      /* Grab first non-switch argument as filename */

    scanFilePtr = fopen(fileName, "r");
    if (!scanFilePtr)
    {
      fprintf(stderr, "ERROR: Unable to open '%s'\n", fileName);
      PQfinish(pgConn);
      fo_scheduler_disconnect(0);
    }

    /* Compile the regex for improved performance */
    retCode = regcomp(&regex, regexStr, REG_ICASE+REG_EXTENDED);
    if (retCode)
    {
      fprintf(stderr, "regex %s failed to compile\n", regexStr);
      PQfinish(pgConn);
      fo_scheduler_disconnect(0);
    }

    /* Now call the function that scans a file for a regex */
    retCode = regexScan(&regex, (char *)regexStr, scanFilePtr, (char *)fileName);
//    retCode = regexScan(uploadNum, regexStr);
    if (retCode != 0)
    {
      fprintf(stderr, "Failed to successfully scan: %s!\n", fileName);
    }

  }

  PQfinish(pgConn);
  fo_scheduler_disconnect(0);

  return 0;
} /* main() */

