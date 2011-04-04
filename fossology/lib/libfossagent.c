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
#include "libfossology.h"

#define FUNCTION


/*********************************************************
 GetAgentKey(): Get the Agent Key from the database.
 Upload_pk is only used for error reporting.
 *********************************************************/
FUNCTION int fo_GetAgentKey(PGconn *pgConn, char * agent_name, long Upload_pk, char *svn_rev, char *agent_desc)
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
  rc = PQntuples(result);
  PQclear(result);
  if (rc == 0)
  {
    /* no match, so add an agent rec */
    sprintf(sql, "INSERT INTO agent (agent_name,agent_desc,agent_enabled) VALUES ('%s',E'%s','%d')",
            agent_name, agent_desc, 1);
    result = PQexec(pgConn, sql);
    if (fo_checkPQcommand(pgConn, result, sqlselect, __FILE__, __LINE__)) return 0;

    result = PQexec(pgConn, sqlselect);
    if (fo_checkPQresult(pgConn, result, sqlselect, __FILE__, __LINE__)) return 0;
    rc = PQntuples(result);
    PQclear(result);
  }

  Agent_pk = atol(PQgetvalue(result, 0, 0));
  return Agent_pk;
} /* fo_GetAgentKey() */
