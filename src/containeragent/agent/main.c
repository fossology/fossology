/*
 SPDX-FileCopyrightText: © 2026 Fossology contributors

 SPDX-License-Identifier: GPL-2.0-only
*/
/**
 * \file
 * \brief main for containeragent
 * \page containeragent Container Analysis Agent
 * \tableofcontents
 * \section containeragentabout About
 * The container analysis agent extracts metadata from Docker and OCI container
 * images after they have been unpacked by the ununpack agent.
 *
 * Extracted data includes:
 *   - Image name, tag, ID
 *   - OS, architecture, variant
 *   - Entrypoint, CMD, WorkingDir, User
 *   - Environment variables
 *   - Exposed ports
 *   - Labels
 *   - Per-layer history (created_by command, empty flag)
 *
 * \section containeragentuse Ways to use containeragent
 *  -# <b>Agent Based Analysis</b> - run from the FOSSology scheduler
 *  -# <b>Command Line</b>         - not yet supported (agent mode only)
 *
 * \section containeragentsource Agent source
 *   - \link src/containeragent/agent \endlink
 *   - \link src/containeragent/ui \endlink
 *   - Unit test cases \link src/containeragent/agent_tests/Unit \endlink
 */

#include "containeragent.h"

#ifdef COMMIT_HASH_S
char BuildVersion[] = "containeragent build version: " VERSION_S
                      " r(" COMMIT_HASH_S ").\n";
#else
char BuildVersion[] = "containeragent build version: NULL.\n";
#endif

/**
 * \brief main function for containeragent
 * \param argc  Number of command-line arguments
 * \param argv  Command-line argument vector
 * \return 0 on success
 */
int main(int argc, char *argv[])
{
  int   c;
  char *agent_desc   = "Extracts metadata from Docker and OCI container images";
  int   Agent_pk;
  int   ars_pk       = 0;
  int   upload_pk    = 0;
  int   user_pk      = 0;
  char *AgentARSName = "containeragent_ars";
  int   rv;
  PGresult *ars_result;
  char  sqlbuf[1024];
  char *COMMIT_HASH;
  char *VERSION;
  char  agent_rev[MAXCMD];
  int   CmdlineFlag  = 0;

  fo_scheduler_connect(&argc, argv, &db_conn);

  COMMIT_HASH = fo_sysconfig("containeragent", "COMMIT_HASH");
  VERSION     = fo_sysconfig("containeragent", "VERSION");
  snprintf(agent_rev, sizeof(agent_rev), "%s.%s", VERSION, COMMIT_HASH);

  Agent_pk = fo_GetAgentKey(db_conn, basename(argv[0]), 0,
                            agent_rev, agent_desc);

  /* ---- Parse command-line options ---- */
  while ((c = getopt(argc, argv, "ic:CvVh")) != -1)
  {
    switch (c)
    {
      case 'i':
        PQfinish(db_conn);
        exit(0);
      case 'v':
        Verbose++;
        break;
      case 'c':
        break;   /* handled by fo_scheduler_connect() */
      case 'C':
        CmdlineFlag = 1;
        break;
      case 'V':
        printf("%s", BuildVersion);
        PQfinish(db_conn);
        return 0;
      default:
        Usage(argv[0]);
        PQfinish(db_conn);
        exit(-1);
    }
  }

  /* ---- Scheduler mode ---- */
  if (CmdlineFlag == 0)
  {
    user_pk = fo_scheduler_userID();

    while (fo_scheduler_next())
    {
      upload_pk = atoi(fo_scheduler_current());

      /* Permission check */
      if (GetUploadPerm(db_conn, upload_pk, user_pk) < PERM_WRITE)
      {
        LOG_ERROR("containeragent: no write permission on upload %d",
                  upload_pk);
        continue;
      }

      if (Verbose)
        printf("containeragent: processing upload %d\n", upload_pk);

      if (upload_pk == 0) continue;

      /* Duplicate-result check via ARS table */
      rv = fo_tableExists(db_conn, AgentARSName);
      if (rv)
      {
        snprintf(sqlbuf, sizeof(sqlbuf),
          "SELECT ars_pk FROM containeragent_ars, agent "
          "WHERE agent_pk = agent_fk "
          "AND   ars_success = true "
          "AND   upload_fk = '%d' "
          "AND   agent_fk  = '%d'",
          upload_pk, Agent_pk);
        ars_result = PQexec(db_conn, sqlbuf);
        if (fo_checkPQresult(db_conn, ars_result, sqlbuf,
                             __FILE__, __LINE__)) exit(-1);
        if (PQntuples(ars_result) > 0)
        {
          PQclear(ars_result);
          LOG_WARNING("containeragent: skipping upload %d — "
                      "results already in database.\n", upload_pk);
          continue;
        }
        PQclear(ars_result);
      }

      ars_pk = fo_WriteARS(db_conn, ars_pk, upload_pk, Agent_pk,
                           AgentARSName, 0, 0);

      if (ProcessUpload(upload_pk) != 0) return -1;

      fo_WriteARS(db_conn, ars_pk, upload_pk, Agent_pk,
                  AgentARSName, 0, 1);
    }
  }
  else
  {
    /* CLI mode — agent requires ununpack output, so CLI is informational only */
    fprintf(stderr,
      "containeragent: CLI mode is not supported.\n"
      "Run as a FOSSology scheduler agent after ununpack completes.\n");
    PQfinish(db_conn);
    fo_scheduler_disconnect(0);
    return 1;
  }

  PQfinish(db_conn);
  fo_scheduler_disconnect(0);
  return 0;
}
