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

#define lstat64(x,y) lstat(x,y)
#define stat64(x,y) stat(x,y)
typedef struct stat stat_t;

#define FUNCTION


/**********************************************
 ReadLine(): Read a command from a stream.
 If the line is empty, then try again.
 Returns line length, or -1 of EOF.
 **********************************************/
FUNCTION int fo_ReadLine (FILE *Fin, char *Line, int MaxLine)
{
  int C;
  int i;

  if (!Fin) return(-1);
  if (feof(Fin)) return(-1);
  memset(Line,'\0',MaxLine);
  i=0;
  C=fgetc(Fin);
  if (C<0) return(-1);
  while(!feof(Fin) && (C>=0) && (i<MaxLine))
    {
    if (C=='\n')
        {
        if (i > 0) return(i);
        /* if it is a blank line, then ignore it. */
        }
    else
        {
        Line[i]=C;
        i++;
        }
    C=fgetc(Fin);
    }
  return(i);
} /* fo_ReadLine() */


/***************************************************
 IsFile(): Given a filename, is it a file?
 Link: should it follow symbolic links?
 Returns 1=yes, 0=no.
 ***************************************************/
FUNCTION int fo_IsFile  (char *Fname, int Link)
{
  stat_t Stat;
  int rc;
  if (!Fname || (Fname[0]=='\0')) return(0);  /* not a directory */
  if (Link) rc = stat64(Fname,&Stat);
  else rc = lstat64(Fname,&Stat);
  if (rc != 0) return(0); /* bad name */
  return(S_ISREG(Stat.st_mode));
} /* fo_IsFile() */


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
