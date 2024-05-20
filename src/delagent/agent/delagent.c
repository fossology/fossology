/*
 SPDX-FileCopyrightText: © 2007-2013 Hewlett-Packard Development Company, L.P.
 SPDX-FileCopyrightText: © 2015-2019 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

/**
 * \file delagent.c
 * \brief Delagent to delete uploaded packages
 *
 * delagent: Remove an upload from the DB and repository
 * \page delagent Delagent
 * \tableofcontents
 * \section delagentabout About delagent
 * Delagent deletes the packages by upload id and folder id.
 *
 * Agent lists all the uploads inside the given folder and delete them one by
 * one when deleting by folder.
 *
 * While deleting by upload, the agent list all files contained in the upload
 * and delete every file from the DB and filesystem as well as relevant
 * decisions for the upload.
 *
 * \section delagentactions Supported actions
 * | Command line flag | Description |
 * | ---: | :--- |
 * | -i   | Initialize the DB, then exit |
 * | -u   | List uploads IDs |
 * | -U # | Delete upload ID |
 * | -f   | List folder IDs |
 * | -F # | Delete folder ID and all uploads under this folder |
 * |      | Folder '1' is the default folder.  '-F 1' will delete |
 * |      | every upload and folder in the navigation tree |
 * |      | use -P to indicate parent of the copied folder |
 * | -s   | Run from the scheduler |
 * | -T   | TEST -- do not update the DB or delete any files (just pretend) |
 * | -v   | Verbose (-vv for more verbose) |
 * | -c # | Specify the directory for the system configuration |
 * | -V   | print the version info, then exit |
 * | --user\|-n # | user name |
 * | --password\|-p # | password |
 * \section delagentsource Agent source
 *   - \link src/delagent/agent \endlink
 *   - \link src/delagent/ui \endlink
 *   - Functional test cases \link src/delagent/agent_tests/Functional \endlink
 *   - Unit test cases \link src/delagent/agent_tests/Unit \endlink
 */
#include "delagent.h"

#ifdef COMMIT_HASH_S
char BuildVersion[]="delagent build version: " VERSION_S " r(" COMMIT_HASH_S ").\n";
#else
char BuildVersion[]="delagent build version: NULL.\n";
#endif


/***********************************************
 \brief Print agent usage for the user

 Command line options allow you to write the agent so it works
 stand alone, in addition to working with the scheduler.
 This simplifies code development and testing.
 So if you have options, have a usage().
 Here are some suggested options (in addition to the program
 specific options you may already have).
 \param Name Absolute path of the agent called by the user.
 ***********************************************/
void usage (char *Name)
{
  fprintf(stderr,"Usage: %s [options]\n",Name);
  fprintf(stderr,"  List or delete uploads.\n");
  fprintf(stderr,"  Options\n");
  fprintf(stderr,"  -i   :: Initialize the DB, then exit.\n");
  fprintf(stderr,"  -u   :: List uploads IDs.\n");
  fprintf(stderr,"  -U # :: Delete upload ID.\n");
  fprintf(stderr,"  -f   :: List folder IDs.\n");
  fprintf(stderr,"  -F # :: Delete folder ID and all uploads under this folder.\n");
  fprintf(stderr,"          Folder '1' is the default folder.  '-F 1' will delete\n");
  fprintf(stderr,"          every upload and folder in the navigation tree.\n");
  fprintf(stderr,"          use -P to indicate parent of the copied folder.\n");
  fprintf(stderr,"  -s   :: Run from the scheduler.\n");
  fprintf(stderr,"  -T   :: TEST -- do not update the DB or delete any files (just pretend)\n");
  fprintf(stderr,"  -v   :: Verbose (-vv for more verbose)\n");
  fprintf(stderr,"  -c # :: Specify the directory for the system configuration\n");
  fprintf(stderr,"  -V   :: print the version info, then exit.\n");
  fprintf(stderr,"  --user|-n # :: user name\n");
  fprintf(stderr,"  --password|-p # :: password\n");
} /* usage() */

/**
 * \brief Write message to user after success/failure
 * \param kind Upload/Folder
 * \param id   Upload/Folder id
 * \param userName User created the request
 * \param returnedCode Code returned by agent
 */
void writeMessageAfterDelete(char *kind, long id, char *userName, int returnedCode)
{
  if (0 == returnedCode)
  {
    fprintf(stdout, "The %s '%ld' is deleted by the user '%s'.\n", kind, id, userName);
  }
  else
  {
    fprintf(stdout, "Deletion failed: user '%s' does not have the permsssion to delete the %s '%ld', or the %s '%ld' does not exist.\n", userName, kind, id, kind, id);
    exitNow(returnedCode);
  }
}

/**
 * \brief main function for the delagent
 *
 * There are 2 ways to use the delagent agent:
 *   1. Command Line :: delete/list upload/folder/license from the command line
 *   2. Agent Based  :: run from the scheduler
 *
 * \htmlonly <pre>
 * +-----------------------+
 * | Command Line Analysis |
 * +-----------------------+
 * </pre> \endhtmlonly
 * List or delete uploads.
 *  - -h            :: help (print this message), then exit.
 *  - -i            :: Initialize the DB
 *  - -u            :: List uploads IDs.
 *  - -U #          :: Delete upload ID.
 *  - -f            :: List folder IDs.
 *  - -F #          :: Delete folder ID and all uploads under this folder.
 *  - -T            :: TEST -- do not update the DB or delete any files (just pretend).
 *  - -v            :: Verbose (-vv for more verbose).
 *  - -V            :: print the version info, then exit.
 *  - -c SYSCONFDIR :: Specify the directory for the system configuration.
 *  - --user #      :: user name
 *  - --password #  :: password
 *
 * \htmlonly <pre>
 * +----------------------+
 * | Agent Based Analysis |
 * +----------------------+
 * </pre> \endhtmlonly
 *
 * To run the delagent as an agent
 *  - -s :: Run from the scheduler
 *
 *
 * \param argc the number of command line arguments
 * \param argv the command line arguments
 * \return 0 on a successful program execution
 */
int main (int argc, char *argv[])
{
  int  c;
  int  listProj=0, listFolder=0;
  long delUpload=0, delFolder=0, delFolderParent=0;
  int  gotArg=0;
  char *agentDesc = "Deletes upload.  Other list/delete options available from the command line.";
  char *commitHash;
  char *version;
  char agentRev[myBUFSIZ];
  int  optionIndex = 0;
  char *userName = NULL;
  char *password = NULL;
  int  userId = -1;
  int  userPerm = -1;
  int  returnedCode = 0;

  fo_scheduler_connect(&argc, argv, &pgConn);

  static struct option long_options[] =
  {
    {"user", required_argument, 0, 'n'},
    {"password", required_argument, 0, 'p'},
    {0, 0, 0, 0}
  };

  while ((c = getopt_long (argc, argv, "n:p:ifF:lL:sTuU:P:vVc:h",
         long_options, &optionIndex)) != -1)
  {
    switch (c)
    {
      case 'n':
        userName = optarg;
        break;
      case 'p':
        password = optarg;
        break;
      case 'i':
        PQfinish(pgConn);
        return(0);
      case 'f':
        listFolder=1;
        gotArg=1;
        break;
      case 'F':
        delFolder=atol(optarg);
        gotArg=1;
        break;
      case 'P':
        delFolderParent=atol(optarg);
        gotArg=1;
        break;
      case 's':
        Scheduler=1;
        gotArg=1;
        break;
      case 'T':
        Test++;
        break;
      case 'u':
        listProj=1;
        gotArg=1;
        break;
      case 'U':
        delUpload=atol(optarg);
        gotArg=1;
        break;
      case 'v':
        Verbose++;
        break;
      case 'c':
        gotArg=1;
        break; /* handled by fo_scheduler_connect() */
      case 'V':
        printf("%s", BuildVersion);
        PQfinish(pgConn);
        return(0);
      default:
        usage(argv[0]);
        exitNow(-1);
    }
  }

  if (!gotArg)
  {
    usage(argv[0]);
    exitNow(-1);
  }

  if (Scheduler != 1)
  {
    if (0 != authentication(userName, password, &userId, &userPerm))
    {
      LOG_FATAL("User name or password is invalid.\n");
      exitNow(-1);
    }

    commitHash = fo_sysconfig("delagent", "COMMIT_HASH");
    version = fo_sysconfig("delagent", "VERSION");
    sprintf(agentRev, "%s.%s", version, commitHash);
    /* Get the Agent Key from the DB */
    fo_GetAgentKey(pgConn, basename(argv[0]), 0, agentRev, agentDesc);

    if (listProj)
    {
      returnedCode = listUploads(userId, userPerm);
    }
    if (returnedCode < 0)
    {
      return returnedCode;
    }
    if (listFolder)
    {
      returnedCode = listFolders(userId, userPerm);
    }
    if (returnedCode < 0)
    {
      return returnedCode;
    }

    alarm(60);  /* from this point on, handle the alarm */
    if (delUpload)
    {
      returnedCode = deleteUpload(delUpload, userId, userPerm);

      writeMessageAfterDelete("upload", delUpload, userName, returnedCode);
    }
    if (delFolder)
    {
      returnedCode = deleteFolder(delFolder, delFolderParent, userId, userPerm);

      writeMessageAfterDelete("folder", delFolder, userName, returnedCode);
    }
  }
  else
  {
    /* process from the scheduler */
    doSchedulerTasks();
  }

  exitNow(0);
  return(returnedCode);
} /* main() */
