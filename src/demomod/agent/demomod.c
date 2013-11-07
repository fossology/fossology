/***************************************************************
 Copyright (C) 2013 Hewlett-Packard Development Company, L.P.

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
 \file demomod.c
 \brief FOSSology demonstration agent

 This is the agent for the demomod FOSSology module.
 The purpose of this is to show the structure of an agent.  So, by design,
 it does very little.  
 
 Given an upload_pk, this agent simply reads the first 32 bytes of each file and records
 them in the demomod table.

 The way permissions work on this agent is that if it runs from the scheduler, 
 the normal fossology upload permissions are checked.  However, if demomod agent runs from
 the command line, no permissions are checked and the output is to stdout, not the database.
 */

#include "demomod.h"

#ifdef SVN_REV_S
char BuildVersion[]="demomod build version: " VERSION_S " r(" SVN_REV_S ").\n";
#else
char BuildVersion[]="demomod build version: NULL.\n";
#endif

/**********  Globals  *************/
psqlCopy_t psqlcpy = 0;        // fo_sqlCopy struct used for fast data insertion
PGconn    *pgConn = 0;        // database connection


/****************************************************/
int main(int argc, char **argv) 
{
  char *agentDesc = "demomod demonstration module";
  char *AgentARSName = "demomod_ars";
  int cmdopt;
  PGresult *ars_result;
  char sqlbuf[512];
  int agent_pk = 0;
  int user_pk = 0;
  char *SVN_REV;
  char *VERSION;
  char agent_rev[myBUFSIZ];
  int upload_pk = 0;
  int ars_pk = 0;
  int Unused = 0;
  int i;
  FileResult_t FileResult;

  /* connect to the scheduler */
  fo_scheduler_connect(&argc, argv, &pgConn);
  user_pk = fo_scheduler_userID(); /* get user_pk for user who queued the agent */

  /* get agent pk 
   * Note, if GetAgentKey fails, this process will exit.
   */
  SVN_REV = fo_sysconfig("demomod", "SVN_REV");
  VERSION = fo_sysconfig("demomod", "VERSION");
  snprintf(agent_rev, sizeof(agent_rev), "%s.%s", VERSION, SVN_REV);
  agent_pk = fo_GetAgentKey(pgConn, basename(argv[0]), Unused, agent_rev, agentDesc);

  /* Verify that the user has PLUGIN_DB_ADMIN */

  /* command line options */
  while ((cmdopt = getopt(argc, argv, "ivVu:c:")) != -1) 
  {
    switch (cmdopt) 
    {
      case 'i': /* "Initialize" */
            ExitNow(0);
      case 'v': /* verbose output for debugging  */
          agent_verbose++;   // global agent verbose flag.  Can be changed in running agent by the scheduler on each fo_scheduler_next() call
            break;
      case 'V': /* print version info */
            printf("%s", BuildVersion);           
            ExitNow(0);
      case 'c': break; /* handled by fo_scheduler_connect() */
      default:
            Usage(argv[0]);
            ExitNow(-1);
    }
  }

  if (optind >= argc)  // if this is true then files weren't specified on the command line, so use the scheduler
  {
    /* make sure the demomod and demomod_ars tables exists */
    CheckTable(AgentARSName);

    user_pk = fo_scheduler_userID(); /* get user_pk for user who queued the agent */

    /* The following is used for debugging only!
     * Once the user_pk is set, you can test this agent as if it were run from the scheduler by
     *     echo 158 | ./demomod -c /usr/local/etc/fossology/
     * where 158 is the upload_pk
     */
    //user_pk=2; // TODO: REMOVE THIS FROM THE PRODUCTION CODE!

    /* It isn't necessary to use a loop here because this agent only reads one item
     * from the scheduler, the upload_pk.  However, it is possible to queue jobs such
     * that the scheduler will pass multiple data items into an agent, so I'm just 
     * going to use a 'while' even though for demomod it will only run once.
     */
    while(fo_scheduler_next())
    {
      upload_pk = atoi(fo_scheduler_current());
      LOG_VERBOSE("demomod upload_pk is %d\n", upload_pk);
      if (upload_pk ==0) 
      {
        LOG_ERROR("demomod was passed a zero upload_pk.  This is an invalid key.");
        ExitNow(-2);
      }

      /* Check Permissions */
      if (GetUploadPerm(pgConn, upload_pk, user_pk) < PERM_WRITE)
      {
        LOG_ERROR("You do not have write permission on upload %d", upload_pk);
        ExitNow(-3);
      }


      /*
       * check if demomod has already been run on this upload.
       */
      snprintf(sqlbuf, sizeof(sqlbuf),
        "select ars_pk from %s,agent where agent_pk=agent_fk and ars_success=true \
            and upload_fk='%d' and agent_fk='%d'",
        AgentARSName, upload_pk, agent_pk);
      ars_result = PQexec(pgConn, sqlbuf);
      if (fo_checkPQresult(pgConn, ars_result, sqlbuf, __FILE__, __LINE__)) break;
      if (PQntuples(ars_result) > 0)
      {
        LOG_WARNING("Ignoring requested demomod scan of upload %d - Results are already in the database.\n",upload_pk);
        PQclear(ars_result);
        continue;
      }
      PQclear(ars_result);

      /* Record scan start in the agent ars table, this is the agent audit trail. */
      ars_pk = fo_WriteARS(pgConn, ars_pk, upload_pk, agent_pk, AgentARSName, 0, 0);

      /* Create the sql copy structure for the demomod table.
       * The fo_sqlCopy functions are used to batch copy records into the database.  This is
       * much faster than single inserts.
       * This creates a struct with buffer size of 100,000 bytes, and three columns
       */
      psqlcpy = fo_sqlCopyCreate(pgConn, "demomod", 100000, 3, "pfile_fk", "agent_fk", "firstbytes" );
      if (!psqlcpy) ExitNow(-4);

      /* process the upload_pk */
      if(ProcessUpload(upload_pk, agent_pk) != 0) ExitNow(-5);

      /* Flush the sqlCopy buffer */
      fo_sqlCopyDestroy(psqlcpy, 1);

      /* Record scan success in ars table. */
      fo_WriteARS(pgConn, ars_pk, upload_pk, agent_pk, AgentARSName, 0, 1);
    }
  }
  else
  {
    /* loop through files on the command line */
    for (i=optind; i<argc; i++)
    {
      if(ProcessFile(argv[i], &FileResult) != 0) ExitNow(-5);
      printf("%s: %s \n", argv[i], FileResult.HexStr);
    }
  }

  ExitNow(0);  /* success */
  return(0);   /* Never executed but prevents compiler warning */
} /* main() */
