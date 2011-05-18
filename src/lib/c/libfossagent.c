/***************************************************************
 libfossagent: Set of generic functions handy for agent development.

 Copyright (C) 2009-2011 Hewlett-Packard Development Company, L.P.

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

/*!
 * \file libfossagent.c
 * \brief libfossagent.c contains general use functions for agents.
 */

#include "libfossology.h"

#define FUNCTION


/*!
 GetAgentKey() 
 \brief Get the latest enabled agent key (agent_pk) from the database.

 \param pgConn Database connection object pointer.
 \param agent_name Name of agent to look up.
 \param Upload_pk is only used for error reporting.
 \param rev agent revision, if given this is the exact revision of the agent being requested.
 \param agent_desc Description of the agent.  Used to write a new agent record in the
                   case where no enabled agent records exist for this agent_name.
 \return On success return agent_pk.  On sql failure, return 0, and the error will be
         written to stdout.
 \todo This function is not checking if the agent is enabled.  And it is not setting 
       agent version when an agent record is inserted.
 */
FUNCTION int fo_GetAgentKey(PGconn *pgConn, char * agent_name, long Upload_pk, char *rev, char *agent_desc)
{
  int rc;
  int Agent_pk=-1;    /* agent identifier */
  char sql[256];
  char sqlselect[256];
  PGresult *result;

  /* get the exact agent rec requested */
  sprintf(sqlselect, "SELECT agent_pk FROM agent WHERE agent_name ='%s' order by agent_ts desc limit 1",
		  agent_name );
  result = PQexec(pgConn, sqlselect);
  if (fo_checkPQresult(pgConn, result, sqlselect, __FILE__, __LINE__)) return 0;
  if (PQntuples(result) == 0)
  {
    PQclear(result);
    /* no match, so add an agent rec */
    sprintf(sql, "INSERT INTO agent (agent_name,agent_desc,agent_enabled) VALUES ('%s',E'%s','%d')",
            agent_name, agent_desc, 1);
    result = PQexec(pgConn, sql);
    if (fo_checkPQcommand(pgConn, result, sqlselect, __FILE__, __LINE__)) return 0;

    result = PQexec(pgConn, sqlselect);
    if (fo_checkPQresult(pgConn, result, sqlselect, __FILE__, __LINE__)) return 0;
    rc = PQntuples(result);
  }

  Agent_pk = atol(PQgetvalue(result, 0, 0));
  PQclear(result);
  return Agent_pk;
} /* fo_GetAgentKey() */
