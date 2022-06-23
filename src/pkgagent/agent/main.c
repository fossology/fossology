/*
 SPDX-FileCopyrightText: © 2010-2013 Hewlett-Packard Development Company, L.P.

 SPDX-License-Identifier: GPL-2.0-only
*/
/**
 * \dir
 * \brief Package agent source
 * \file
 * \brief main for pkgagent
 * \page pkgagent Package agent
 * \tableofcontents
 * \section pkgagentabout About
 * The package metadata agent puts data about each package (rpm and deb) into the database.
 *
 * * Pkgagent get RPM package info from rpm files using rpm library,
 * Build pkgagent.c need "rpm" and "librpm-dev", running binary just need "rpm".
 * * Pkgagent get Debian binary package info from .deb binary control file.
 * * Pkgagent get Debian source package info from .dsc file.
 *
 * \section pkgagentuse Ways to use pkgagent
 * There are 2 ways to use the pkgagent agent:
 *  -# <b>Command Line Analysis</b> - test a rpm file from the command line
 *  -# <b>Agent Based Analysis</b>  - run from the scheduler
 *
 * \subsection pkgclianalysis Command Line Analysis
 * To analyze a rpm file from the command line:
 * | CLI argument | Description |
 * | ---: | :--- |
 * | file | If files are rpm package listed, display their meta data |
 * | -v   | Verbose (-vv = more verbose) |
 *
 *   example:
 *     \code $ ./pkgagent rpmfile \endcode
 *
 * \subsection pkgagentanalysis Agent Based Analysis
 *  To run the pkgagent as an agent simply run with no command line args
 * | CLI argument | Description |
 * | ---: | :--- |
 * | no file | Process data from the scheduler |
 * | -i      | Initialize the database, then exit |
 *
 *   example:
 *     \code $ upload_pk | ./pkgagent \endcode
 *
 * \section pkgagentactions Supported actions
 * | Command line flag | Description |
 * | ---: | :--- |
 * | -i   | Initialize the database, then exit |
 * | -v   | Verbose (-vv = more verbose) |
 * | -c   | Specify the directory for the system configuration |
 * | -C   | Run from command line |
 * | file | If files are rpm package listed, display their package information |
 * | no file | Process data from the scheduler |
 * | -V   | Print the version info, then exit |
 * \section pkgagentsource Agent source
 *   - \link src/pkgagent/agent \endlink
 *   - \link src/pkgagent/ui \endlink
 *   - Functional test cases \link src/pkgagent/agent_tests/Functional \endlink
 *   - Unit test cases \link src/pkgagent/agent_tests/Unit \endlink
 */
#include "pkgagent.h"

#ifdef COMMIT_HASH_S
char BuildVersion[]="pkgagent build version: " VERSION_S " r(" COMMIT_HASH_S ").\n";
#else
char BuildVersion[]="pkgagent build version: NULL.\n";
#endif

/**
 * \brief main function for the pkgagent
 * \param argc the number of command line arguments
 * \param argv the command line arguments
 * \return 0 on a successful program execution
 */
int	main	(int argc, char *argv[])
{
  int c;
  char *agent_desc = "Pulls metadata out of RPM or DEBIAN packages";
  //struct rpmpkginfo *glb_rpmpi;
  //struct debpkginfo *glb_debpi;
  int Agent_pk;
  int ars_pk = 0;

  int upload_pk = 0;           // the upload primary key
  int user_pk = 0;           // the upload primary key
  char *AgentARSName = "pkgagent_ars";
  int rv;
  PGresult *ars_result;
  char sqlbuf[1024];
  char *COMMIT_HASH;
  char *VERSION;
  char agent_rev[MAXCMD];
  int CmdlineFlag = 0; /* run from command line flag, 1 yes, 0 not */

  fo_scheduler_connect(&argc, argv, &db_conn);

  //glb_rpmpi = (struct rpmpkginfo *)malloc(sizeof(struct rpmpkginfo));
  //glb_debpi = (struct debpkginfo *)malloc(sizeof(struct debpkginfo));

  COMMIT_HASH = fo_sysconfig("pkgagent", "COMMIT_HASH");
  VERSION = fo_sysconfig("pkgagent", "VERSION");
  sprintf(agent_rev, "%s.%s", VERSION, COMMIT_HASH);
  Agent_pk = fo_GetAgentKey(db_conn, basename(argv[0]), 0, agent_rev, agent_desc);

  /* Process command-line */
  while((c = getopt(argc,argv,"ic:CvVh")) != -1)
  {
    switch(c)
    {
      case 'i':
        PQfinish(db_conn);  /* DB was opened above, now close it and exit */
        exit(0);
      case 'v':
        Verbose++;
        break;
      case 'c':
        break; /* handled by fo_scheduler_connect() */
      case 'C':
        CmdlineFlag = 1;
        break;
      case 'V':
        printf("%s", BuildVersion);
        PQfinish(db_conn);
        return(0);
      default:
        Usage(argv[0]);
        PQfinish(db_conn);
        exit(-1);
    }
  }
  /* If no args, run from scheduler! */
  if (CmdlineFlag == 0)
  {
    user_pk = fo_scheduler_userID(); /* get user_pk for user who queued the agent */

    while(fo_scheduler_next())
    {
      upload_pk = atoi(fo_scheduler_current());

      /* Check Permissions */
      if (GetUploadPerm(db_conn, upload_pk, user_pk) < PERM_WRITE)
      {
        LOG_ERROR("You have no update permissions on upload %d", upload_pk);
        continue;
      }

      if (Verbose) { printf("PKG: pkgagent read %d\n", upload_pk);}
      if (upload_pk ==0) continue;

      /* check if pkgagent ars table exist?
       * if exist, check duplicate request
       * if not exist, don't check duplicate request
       */
      rv = fo_tableExists(db_conn, AgentARSName);
      if (rv)
      {
        /* check ars table to see if this is duplicate request*/
        snprintf(sqlbuf, sizeof(sqlbuf),
          "select ars_pk from pkgagent_ars,agent \
          where agent_pk=agent_fk and ars_success=true \
          and upload_fk='%d' and agent_fk='%d'",
          upload_pk, Agent_pk);
        ars_result = PQexec(db_conn, sqlbuf);
        if (fo_checkPQresult(db_conn, ars_result, sqlbuf, __FILE__, __LINE__)) exit(-1);
        if (PQntuples(ars_result) > 0)
        {
          PQclear(ars_result);
          LOG_WARNING("Ignoring requested pkgagent analysis of upload %d - Results are already in database.\n",upload_pk);
          continue;
        }
        PQclear(ars_result);
      }
      /* Record analysis start in pkgagent_ars, the pkgagent audit trail. */
      ars_pk = fo_WriteARS(db_conn, ars_pk, upload_pk, Agent_pk, AgentARSName, 0, 0);

      /* process the upload_pk pkgagent */
      if(ProcessUpload(upload_pk) != 0) return -1;

      /* Record analysis success in pkgagent_ars. */
      if (ars_pk) fo_WriteARS(db_conn, ars_pk, upload_pk, Agent_pk, AgentARSName, 0, 1);
    }
  }
  else
  {
    if (Verbose) { printf("DEBUG: running in cli mode, processing file(s)\n");}
    for (; optind < argc; optind++)
    {
      struct rpmpkginfo *rpmpi;
      rpmpi = (struct rpmpkginfo *)malloc(sizeof(struct rpmpkginfo));
      rpmReadConfigFiles(NULL, NULL);
      //if(ProcessUpload(atoi(argv[optind])) == 0)
      if(GetMetadata(argv[optind],rpmpi) != -1)
        printf("OK\n");
      else
        printf("Fail\n");
      rpmFreeCrypto();
      int i;
      for(i=0; i< rpmpi->req_size;i++)
        free(rpmpi->requires[i]);
      free(rpmpi->requires);
      free(rpmpi);
      rpmFreeMacros(NULL);
    }
  }

  PQfinish(db_conn);
  fo_scheduler_disconnect(0);
  return(0);
} /* main() */
