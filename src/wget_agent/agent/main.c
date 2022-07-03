/*
 SPDX-FileCopyrightText: © 2011-2013 Hewlett-Packard Development Company, L.P.

 SPDX-License-Identifier: GPL-2.0-only
*/

/**
 * \file
 * \brief main for wget agent
 *        locally to import the file to repo
 */

#define _GNU_SOURCE
#include "wget_agent.h"
#include <gcrypt.h>

#ifdef COMMIT_HASH_S
char BuildVersion[]="wget_agent build version: " VERSION_S " r(" COMMIT_HASH_S ").\n";
#else
char BuildVersion[]="wget_agent build version: NULL.\n";
#endif

/**
 * \page wget_agent Wget Agent
 * \tableofcontents
 * \section wget_agentabout About Wget_agent
 * Wget agent is use to get files from FTP, FTPS, HTTP, HTTPS, GIT and SVN.
 * It supports major flags of the GNU Wget.
 *
 * \section wget_agentuseage Ways to use Wget agent
 * There are 3 ways to use the wget_agent:
 * -# Command Line download: download one file or one directory from the command line
 * -# Agent Based download: run from the scheduler
 * -# Command Line locally to import the file(directory):
 *     - Import one file or one directory from the command line, used by upload from file and upload from server
 *
 * \subsection wget_agentclid Command Line download
 *
 * To download one file or one directory from the command line:
 *   example:
 *   \code ./wget_agent http://www.aaa.com/bbb \endcode
 *
 * \subsection wget_agentagentbased Agent Based
 *
 * To download one file or one directory (one URL )from the scheduler:
 *   example:
 * -# part 1 parameters from the scheduler:
 *     \code 19 - http://g.org -l 1 -R index.html* \endcode
 *     \b 19 is uploadpk, <b>'http://g.org'</b> is downloadfile url,
 *     <b>'-l 1  -R index.html*'</b> is several parameters used by wget_agent
 * -# part 2 parameters from wget_agent.conf:
 *     \code `-d /var/local/lib/fossology/agents` \endcode
 *     \b '/var/local/lib/fossology/agent' is directory for downloaded file(directory)
 *     storage temporarily, after all file(directory) is dowloaded, move them into repo
 *
 * \subsection wget_agentcliimport Command Line locally to import the file(directory)
 *
 * To Import one file or one directory from the command line into repo:
 *   example:
 *   \code ./wget_agent -g fossy -k $uploadpk '$UploadedFile' \endcode
 *
 * \section wget_agentactions Supported actions
  | Command line flag | Description |
  | ---: | :--- |
  | -h | Help (print this message), then exit |
  | -i | Initialize the DB connection then exit (nothing downloaded) |
  | -g group | Set the group on processed files (e.g., -g fossy) |
  | -G | Do NOT copy the file to the gold repository |
  | -d dir | Directory for downloaded file storage |
  | -k key | Upload key identifier (number) |
  | -A acclist | Specify comma-separated lists of file name suffixes or patterns to accept |
  | -R rejlist | Specify comma-separated lists of file name suffixes or patterns to reject |
  | -l depth | Specify recursion maximum depth level depth.  The default maximum depth is 5 |
  | -c configdir | Specify the directory for the system configuration |
  | -C | Run from command line |
  | -v | Verbose (-vv = more verbose) |
  | -V | Print the version info, then exit |
  | OBJ | if a URL is listed, then it is retrieved |
  |     |   if a file is listed, then it used |
  |     |   if OBJ and Key are provided, then it is inserted into |
  |     |   the DB and repository |
  | no file | process data from the scheduler |
  \section wget_agentsource Agent source
 *   - \link src/wget_agent/agent \endlink
 *   - \link src/wget_agent/ui \endlink
 *   - Functional test cases \link src/wget_agent/agent_tests/Functional \endlink
 *   - Unit test cases \link src/wget_agent/agent_tests/Unit \endlink
 */
/**
 * \biref main entry point for agent
 * \param argc the number of command line arguments
 * \param argv the command line arguments
 * \return 0 on a successful program execution
 */

int main  (int argc, char *argv[])
{
  int arg;
  char *Parm = NULL;
  char *TempFileDir=NULL;
  int c;
  int InitFlag=0;
  int CmdlineFlag = 0; /* run from command line flag, 1 yes, 0 not */
  int user_pk;
  char *agent_desc = "Network downloader.  Uses wget(1).";

  memset(GlobalTempFile,'\0',STRMAX);
  memset(GlobalURL,'\0',URLMAX);
  memset(GlobalParam,'\0',STRMAX);
  memset(GlobalType,'\0',STRMAX);
  GlobalUploadKey = -1;
  int upload_pk = 0;           // the upload primary key
  //int Agent_pk;
  char *COMMIT_HASH;
  char *VERSION;
  char agent_rev[STRMAX];

  /* open the connection to the scheduler and configuration */
  fo_scheduler_connect(&argc, argv, &pgConn);

  /* Process command-line */
  while((c = getopt(argc,argv,"d:Gg:ik:A:R:l:Cc:Vvh")) != -1)
  {
    switch(c)
    {
      case 'd':
        TempFileDir = PathCheck(optarg);
        break;
      case 'g':
      {
        struct group *SG;
        SG = getgrnam(optarg);
        if (SG) ForceGroup = SG->gr_gid;
      }
      break;
      case 'G':
        GlobalImportGold=0;
        break;
      case 'i':
        InitFlag=1;
        break;
      case 'k':
        GlobalUploadKey = atol(optarg);
        if (!GlobalTempFile[0])
          strcpy(GlobalTempFile,"wget.default_download");
        break;
      case 'A':
        strncat(GlobalParam, " -A ", STRMAX - strlen(GlobalParam) -1);
        strncat(GlobalParam, optarg, STRMAX - strlen(GlobalParam) -1);
        break;
      case 'R':
        strncat(GlobalParam, " -R ", STRMAX - strlen(GlobalParam) -1);
        strncat(GlobalParam, optarg, STRMAX - strlen(GlobalParam) -1);
        break;
      case 'l':
        strncat(GlobalParam, " -l ", STRMAX - strlen(GlobalParam) -1);
        strncat(GlobalParam, optarg, STRMAX - strlen(GlobalParam) -1);
        break;
      case 'c': break; /* handled by fo_scheduler_connect() */
      case 'C':
        CmdlineFlag = 1;
        break;
      case 'v':
        agent_verbose++;   // global agent verbose flag.
        break;
      case 'V':
       printf("%s", BuildVersion);
       SafeExit(0);
      default:
        Usage(argv[0]);
        SafeExit(-1);
    }
  }
  if (argc - optind > 1)
  {
    Usage(argv[0]);
    SafeExit(-1);
  }

  /* When initializing the DB, don't do anything else */
  if (InitFlag)
  {
    if (pgConn) PQfinish(pgConn);
    SafeExit(0);
  }

  /* Initialize gcrypt and disable security memory */
  gcry_check_version(NULL);
  gcry_control (GCRYCTL_DISABLE_SECMEM, 0);
  gcry_control (GCRYCTL_INITIALIZATION_FINISHED, 0);

  COMMIT_HASH = fo_sysconfig("wget_agent", "COMMIT_HASH");
  VERSION = fo_sysconfig("wget_agent", "VERSION");
  sprintf(agent_rev, "%s.%s", VERSION, COMMIT_HASH);
  /* Get the Agent Key from the DB */
  fo_GetAgentKey(pgConn, basename(argv[0]), GlobalUploadKey, agent_rev, agent_desc);

  /* get proxy */
  GetProxy();

  /* Run from the command-line (for testing) */
  for(arg=optind; arg < argc; arg++)
  {
    memset(GlobalURL,'\0',sizeof(GlobalURL));
    strncpy(GlobalURL,argv[arg],sizeof(GlobalURL)-1);
    /* If the file contains "://" then assume it is a URL.
       Else, assume it is a file. */
    LOG_VERBOSE0("Command-line: %s",GlobalURL);
    if (strstr(GlobalURL,"://"))
    {
      fo_scheduler_heart(1);
      LOG_VERBOSE0("It's a URL");
      if (GetURL(GlobalTempFile,GlobalURL,TempFileDir) != 0)
      {
        LOG_FATAL("Download of %s failed.",GlobalURL);
        SafeExit(21);
      }
      if (GlobalUploadKey != -1) { DBLoadGold(); }
      unlink(GlobalTempFile);
    }
    else /* must be a file */
    {
      LOG_VERBOSE0("It's a file -- GlobalUploadKey = %ld",GlobalUploadKey);
      if (GlobalUploadKey != -1)
      {
        memcpy(GlobalTempFile,GlobalURL,STRMAX);
        DBLoadGold();
      }
    }
  }

  /* Run from scheduler! */
  if (0 == CmdlineFlag)
  {
    user_pk = fo_scheduler_userID(); /* get user_pk for user who queued the agent */
    while(fo_scheduler_next())
    {
      Parm = fo_scheduler_current(); /* get piece of information, including upload_pk, downloadfile url, and parameters */
      if (Parm && Parm[0])
      {
        fo_scheduler_heart(1);
        /* set globals: uploadpk, downloadfile url, parameters */
        SetEnv(Parm,TempFileDir);
        upload_pk = GlobalUploadKey;

        /* Check Permissions */
        if (GetUploadPerm(pgConn, upload_pk, user_pk) < PERM_WRITE)
        {
          LOG_ERROR("You have no update permissions on upload %d", upload_pk);
          continue;
        }

        char TempDir[STRMAX];
        memset(TempDir,'\0',STRMAX);
        snprintf(TempDir, STRMAX-1, "%s/wget", TempFileDir); // /var/local/lib/fossology/agents/wget
        struct stat Status = {};

        if (GlobalType[0])
        {
          if (GetVersionControl() == 0)
          {
            DBLoadGold();
            unlink(GlobalTempFile);
          }
          else
          {
            LOG_FATAL("upload %ld File retrieval failed: uploadpk=%ld tempfile=%s URL=%s Type=%s",
                GlobalUploadKey,GlobalUploadKey,GlobalTempFile,GlobalURL, GlobalType);
            SafeExit(23);
          }
        }
        else if (strstr(GlobalURL, "*") || stat(GlobalURL, &Status) == 0)
        {
          if (!Archivefs(GlobalURL, GlobalTempFile, TempFileDir, Status))
          {
            LOG_FATAL("Failed to archive. GlobalURL, GlobalTempFile, TempFileDir are: %s, %s, %s, "
               "Mode is: %lo (octal)\n", GlobalURL, GlobalTempFile, TempFileDir, (unsigned long) Status.st_mode);
            SafeExit(50);
          }
          DBLoadGold();
          unlink(GlobalTempFile);
        }
        else
        {
          if (GetURL(GlobalTempFile,GlobalURL,TempDir) == 0)
          {
            DBLoadGold();
            unlink(GlobalTempFile);
          }
          else
          {
            LOG_FATAL("upload %ld File retrieval failed: uploadpk=%ld tempfile=%s URL=%s",
                GlobalUploadKey,GlobalUploadKey,GlobalTempFile,GlobalURL);
            SafeExit(22);
          }
        }
      }
    }
  } /* if run from scheduler */

  SafeExit(0);
  exit(0);  /* to prevent compiler warning */
} /* main() */

