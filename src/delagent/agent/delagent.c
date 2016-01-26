/********************************************************
 delagent: Remove an upload from the DB and repository

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
 ********************************************************/
/**
 * \file delagent.c
 * \brief main for delagent
 *
 * delagent: Remove an upload from the DB and repository
 */
#include "delagent.h"

#ifdef COMMIT_HASH_S
char BuildVersion[]="delagent build version: " VERSION_S " r(" COMMIT_HASH_S ").\n";
#else
char BuildVersion[]="delagent build version: NULL.\n";
#endif


/**
 * \brief main function for the delagent
 *
 * There are 2 ways to use the delagent agent:
 *   1. Command Line :: delete/list upload from the command line
 *   2. Agent Based  :: run from the scheduler
 *
 * +-----------------------+
 * | Command Line Analysis |
 * +-----------------------+
 *
 * List or delete uploads.
 *   -h   :: help (print this message), then exit.
 *   -i   :: Initialize the DB
 *   -u   :: List uploads IDs.
 *   -U # :: Delete upload ID.
 *   -L # :: Delete ALL licenses associated with upload ID.
 *   -f   :: List folder IDs.
 *   -F # :: Delete folder ID and all uploads under this folder.
 *   -T   :: TEST -- do not update the DB or delete any files (just pretend).
 *   -v   :: Verbose (-vv for more verbose).
 *   -V   :: print the version info, then exit.
 *   -c SYSCONFDIR :: Specify the directory for the system configuration.
 *   --user #  :: user name
 *   --password #  :: password
 *
 * +----------------------+
 * | Agent Based Analysis |
 * +----------------------+
 *
 * To run the delagent as an agent
 *   -s :: Run from the scheduler
 *
 *
 * \param argc the number of command line arguments
 * \param argv the command line arguments
 * \return 0 on a successful program execution
 */
int main (int argc, char *argv[])
{
  int c;
  int ListProj=0, ListFolder=0;
  long DelUpload=0, DelFolder=0, DelLicense=0;
  int Scheduler=0; /* should it run from the scheduler? */
  int GotArg=0;
  char *agent_desc = "Deletes upload.  Other list/delete options available from the command line.";
  char *Parm = NULL;
  //int Agent_pk = 0;
  char *COMMIT_HASH;
  char *VERSION;
  char agent_rev[myBUFSIZ];
  int option_index = 0;
  char *user_name = NULL;
  char *password = NULL;
  int user_id = -1;
  int user_perm = -1;

  fo_scheduler_connect(&argc, argv, &db_conn);

  static struct option long_options[] =
  {
    {"user", required_argument, 0, 'n'},
    {"password", required_argument, 0, 'p'},
    {0, 0, 0, 0}
  };
   
  while ((c = getopt_long (argc, argv, "n:p:ifF:lL:sTuU:vVc:h",
         long_options, &option_index)) != -1)
  {
    switch (c)
    {
      case 'n': 
        user_name = optarg;
        break; 
      case 'p':
        password = optarg;
        break;
      case 'i':
        PQfinish(db_conn);
        return(0);
      case 'f': ListFolder=1; GotArg=1; break;
      case 'F': DelFolder=atol(optarg); GotArg=1; break;
      case 'L': DelLicense=atol(optarg); GotArg=1; break;
      case 's': Scheduler=1; GotArg=1; break;
      case 'T': Test++; break;
      case 'u': ListProj=1; GotArg=1; break;
      case 'U': DelUpload=atol(optarg); GotArg=1; break;
      case 'v': Verbose++; break;
      case 'c': GotArg=1; break; /* handled by fo_scheduler_connect() */
      case 'V': printf("%s", BuildVersion); PQfinish(db_conn); return(0);
      default:	Usage(argv[0]); exit(-1);
    }
  }

  if (!GotArg)
  {
    Usage(argv[0]);
    exit(-1);
  }

  if (Scheduler != 1 && 1 != authentication(user_name, password, &user_id, &user_perm)) 
  {
    LOG_FATAL("User name or password is invalid.\n");
    exit(-1);
  }

  COMMIT_HASH = fo_sysconfig("delagent", "COMMIT_HASH");
  VERSION = fo_sysconfig("delagent", "VERSION");
  sprintf(agent_rev, "%s.%s", VERSION, COMMIT_HASH);
  /* Get the Agent Key from the DB */
  fo_GetAgentKey(db_conn, basename(argv[0]), 0, agent_rev, agent_desc);

  if(Scheduler == 1 && user_id == -1){
    /* get user_id for user who queued the agent */
    user_id = fo_scheduler_userID();
  }
  
  if (ListProj) ListUploads(user_id, user_perm);
  if (ListFolder) ListFolders(user_id);

  alarm(60);  /* from this point on, handle the alarm */
  if (DelUpload) 
  {
    if (1 != check_permission_del(DelUpload, user_id, user_perm))
    {
      LOG_FATAL("You '%s' does not have the permsssion to delete the upload '%ld', or the upload '%ld' does not exist.\n", user_name, DelUpload, DelUpload);
      exit(-1);
    }
    DeleteUpload(DelUpload); 
    fprintf(stdout, "The upload '%ld' is deleted by the user '%s'.\n", DelUpload, user_name);
  }
  if (DelFolder) { DeleteFolder(DelFolder, user_id); }
  if (DelLicense) { DeleteLicense(DelLicense); }

  /* process from the scheduler */
  if (Scheduler)
  {
    while(fo_scheduler_next())
    {
      Parm = fo_scheduler_current();
      
      if (ReadParameter(Parm, user_id) < 0)
        exit(-1);
    }
  }

  PQfinish(db_conn);
  fo_scheduler_disconnect(0);
  return(0);
} /* main() */

