/***************************************************************
 Copyright (C) 2010-2011 Hewlett-Packard Development Company, L.P.

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

 ***************************************************************/
/**
 * \file main.c
 * \brief main for pkgagent
 *
 * The package metadata agent puts data about each package (rpm and deb) into the database.
 * 
 * Pkgagent get RPM package info from rpm files using rpm library,
 * Build pkgagent.c need "rpm" and "librpm-dev", running binary just need "rpm".
 *
 * Pkgagent get Debian binary package info from .deb binary control file.
 *
 * Pkgagent get Debian source package info from .dsc file.
 */
#include "pkgagent.h"

/**
 * \brief main function for the pkgagent
 *
 * There are 2 ways to use the pkgagent agent:
 *   1. Command Line Analysis :: test a rpm file from the command line
 *   2. Agent Based Analysis  :: run from the scheduler
 *
 * +-----------------------+
 * | Command Line Analysis |
 * +-----------------------+
 *
 * To analyze a rpm file from the command line:
 *   file :: if files are rpm package listed, display their meta data
 *   -v   :: verbose (-vv = more verbose)
 *
 *   example:
 *     $ ./pkgagent rpmfile
 *
 * +----------------------+
 * | Agent Based Analysis |
 * +----------------------+
 *
 * To run the pkgagent as an agent simply run with no command line args
 *   no file :: process data from the scheduler
 *   -i      :: initialize the database, then exit
 *
 *   example:
 *     $ upload_pk | ./pkgagent
 *
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
  char *AgentARSName = "pkgagent_ars";
  PGresult *ars_result;
  char sqlbuf[1024]; 
  char *DBConfFile = NULL;  /* use default Db.conf */
  char *ErrorBuf;
  char *SVN_REV;
  char *VERSION;
  char agent_rev[MAXCMD];
  int CmdlineFlag = 0; /* run from command line flag, 1 yes, 0 not */

  fo_scheduler_connect(&argc, argv);

  //glb_rpmpi = (struct rpmpkginfo *)malloc(sizeof(struct rpmpkginfo));
  //glb_debpi = (struct debpkginfo *)malloc(sizeof(struct debpkginfo));

  db_conn = fo_dbconnect(DBConfFile, &ErrorBuf);
  if (!db_conn)
  {
    LOG_FATAL("Unable to connect to database. Error: %s.\n", ErrorBuf);
    exit(-1);
  }

  SVN_REV = fo_sysconfig("pkgagent", "SVN_REV");
  VERSION = fo_sysconfig("pkgagent", "VERSION");
  sprintf(agent_rev, "%s.%s", VERSION, SVN_REV);
  Agent_pk = fo_GetAgentKey(db_conn, basename(argv[0]), 0, agent_rev, agent_desc);

  /* Process command-line */
  while((c = getopt(argc,argv,"ic:Cvh")) != -1)
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
      default:
        Usage(argv[0]);
        PQfinish(db_conn);
        exit(-1);
    }
  }
  /* If no args, run from scheduler! */
  if (CmdlineFlag == 0)
  {
    while(fo_scheduler_next())
    {
      upload_pk = atoi(fo_scheduler_current());

      if (Verbose) { printf("PKG: pkgagent read %d\n", upload_pk);}
      if (upload_pk ==0) continue;

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
#ifdef _RPM_4_4_COMPAT
      rpmFreeCrypto();
      int i;
      for(i=0; i< rpmpi->req_size;i++)
        free(rpmpi->requires[i]);
#endif /* After RPM4.4 version*/
      free(rpmpi->requires);
      free(rpmpi);
      rpmFreeMacros(NULL);
    }
  }

  PQfinish(db_conn);
  fo_scheduler_disconnect(0);
  return(0);
} /* main() */
