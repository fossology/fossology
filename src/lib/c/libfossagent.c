/***************************************************************
 libfossagent: Set of generic functions handy for agent development.

 Copyright (C) 2009-2013 Hewlett-Packard Development Company, L.P.

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
 \brief Get the latest enabled agent key (agent_pk) from the database.

 \param pgConn Database connection object pointer.
 \param agent_name Name of agent to look up.
 \param Upload_pk is no longer used.
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
    sprintf(sql, "INSERT INTO agent (agent_name,agent_desc,agent_enabled,agent_rev) VALUES ('%s',E'%s','%d', '%s')",
            agent_name, agent_desc, 1, rev);
    result = PQexec(pgConn, sql);
    if (fo_checkPQcommand(pgConn, result, sqlselect, __FILE__, __LINE__)) return 0;

    result = PQexec(pgConn, sqlselect);
    if (fo_checkPQresult(pgConn, result, sqlselect, __FILE__, __LINE__)) return 0;
  }

  Agent_pk = atol(PQgetvalue(result, 0, 0));
  PQclear(result);
  return Agent_pk;
} /* fo_GetAgentKey() */


/**
 \brief Write ars record
  If the ars table does not exist, one is created by inheriting the ars_master table.
  The new table is called {tableName}.  For example, "unpack_ars".
  If ars_pk is zero a new ars record will be created. Otherwise, it is updated.

 \param pgConn Database connection object pointer.
 \param ars_pk  If zero, a new record will be created.
 \param upload_pk
 \parm  agent_pk   Agents should get this from fo_GetAgentKey()
 \param tableName  ars table name
 \parm  ars_status   Status to update ars_status.  May be null.
 \parm  ars_success  Automatically set to false if ars_pk is zero.

 \return On success write the ars record and return the ars_pk.  
         On sql failure, return 0, and the error will be written to stdout.
 */
FUNCTION int fo_WriteARS(PGconn *pgConn, int ars_pk, int upload_pk, int agent_pk,
                         char *tableName, char *ars_status, int ars_success)
{
  char sql[1024];
  PGresult *result;

  /* does ars table exist?  If not, create it.  */
    if (! fo_CreateARSTable(pgConn, tableName)) return(0);

  /* If ars_pk is null, 
   * write the ars_status=false record 
   * and return the ars_pk.
   */
  if (!ars_pk)
  {
    snprintf(sql, sizeof(sql), "insert into %s (agent_fk, upload_fk) values(%d,%d)",
             tableName, agent_pk, upload_pk);
    result = PQexec(pgConn, sql);
    if (fo_checkPQcommand(pgConn, result, sql, __FILE__, __LINE__)) return 0;

    /* get primary key */
    snprintf(sql, sizeof(sql), "SELECT currval('nomos_ars_ars_pk_seq')");
    result =  PQexec(pgConn, sql);
    if (fo_checkPQresult(pgConn, result, sql, __FILE__, __LINE__))
    {
      PQclear(result);
      return(0);
    }
    ars_pk = atoi(PQgetvalue(result, 0, 0));
    PQclear(result);
  }
  else
  {
    /* If ars_pk is not null, update success, status and endtime */
    if (ars_status)
    {
      snprintf(sql, sizeof(sql), "update %s set ars_success=%s, ars_status='%s',ars_endtime=now()",
             tableName, ars_success?"True":"False", ars_status);
    }
    else
    {
      snprintf(sql, sizeof(sql), "update %s set ars_success=%s, ars_endtime=now()",
             tableName, ars_success?"True":"False");
    }
    result = PQexec(pgConn, sql);
    if (fo_checkPQcommand(pgConn, result, sql, __FILE__, __LINE__)) return 0;
  }
  return(ars_pk);
}  /* fo_WriteARS() */


/**
 \brief Create ars table if it doesn't already exist.
  
 \param pgConn Database connection object pointer.
 \param tableName  ars table name

 \return 0 on failure
 */
FUNCTION int fo_CreateARSTable(PGconn *pgConn, char *tableName)
{
  char sql[1024];
  PGresult *result;

  if (fo_tableExists(pgConn, tableName)) return 1;  // table already exists

  snprintf(sql, sizeof(sql), "create table %s() inherits(ars_master);\
  ALTER TABLE ONLY %s ADD CONSTRAINT %s_agent_fk_fkc FOREIGN KEY (agent_fk) REFERENCES agent(agent_pk);\
  ALTER TABLE ONLY %s ADD CONSTRAINT %s_upload_fk_fkc FOREIGN KEY (upload_fk) REFERENCES upload(upload_pk) ON DELETE CASCADE;", 
  tableName, tableName, tableName, tableName, tableName);
/*  ALTER TABLE ONLY %s ADD CONSTRAINT %s_pkey1 PRIMARY KEY (ars_pk); \  */


  result = PQexec(pgConn, sql);
  if (fo_checkPQcommand(pgConn, result, sql, __FILE__, __LINE__)) return 0;
  return 1;  /* success */
}  /* fo_CreateARSTable() */


/**
 * \brief Get users permission to this upload
 *
 * \param pgConn Database connection object pointer.
 * \param long upload_pk
 * \param user_pk  
 *
 * \return permission (PERM_) this user has for UploadPk
 */
FUNCTION int GetUploadPerm(PGconn *pgConn, long UploadPk, int user_pk)
{
  PGresult *result;
  char SQL[1024];
  int perm;

  /* Check the users PLUGIN_DB level.  PLUGIN_DB_ADMIN are superusers. */
  snprintf(SQL, sizeof(SQL), "select user_perm from users where user_pk='%d'", user_pk);
  result =  PQexec(pgConn, SQL);
  fo_checkPQresult(pgConn, result, SQL, __FILE__ ,__LINE__);
  if (PQntuples(result) < 1)
  {
    LOG_ERROR("No records returned in %s", SQL);
    return 0;
  }
  perm = atoi(PQgetvalue(result, 0, 0));
  PQclear(result);
  if (perm >= PLUGIN_DB_ADMIN) return(PERM_ADMIN);

  /* Get the user permission level */
  snprintf(SQL, sizeof(SQL), "select max(perm) as perm from perm_upload, group_user_member where perm_upload.upload_fk=%ld and user_fk=%d and group_user_member.group_fk=perm_upload.group_fk", UploadPk, user_pk);
  result = PQexec(pgConn, SQL);
  fo_checkPQresult(pgConn, result, SQL, __FILE__ ,__LINE__);
  perm = atoi(PQgetvalue(result, 0, 0));
  return (PERM_ADMIN);
}


/**
 * \brief Get the uploadtree table name for this upload_pk
 *        If upload_pk does not exist, return "uploadtree".
 *
 * \param pgConn Database connection object pointer.
 * \param upload_pk
 * 
 * \return uploadtree table name, or null if upload_pk does not exist.
 * Caller must free the (non-null) returned value.
 */
FUNCTION char *GetUploadtreeTableName(PGconn *pgConn, int upload_pk)
{
  PGresult *result;
  char *uploadtree_tablename = 0;
  char SQL[1024];

  /* Get the uploadtree table name from the upload table */
  snprintf(SQL, sizeof(SQL), "select uploadtree_tablename from upload where upload_pk='%d'", upload_pk);
  result =  PQexec(pgConn, SQL);
  fo_checkPQresult(pgConn, result, SQL, __FILE__ ,__LINE__);
  if (PQntuples(result) == 1) uploadtree_tablename = strdup(PQgetvalue(result, 0, 0));
  PQclear(result);

  return (uploadtree_tablename);
}
