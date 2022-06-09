/*
 libfossagent: Set of generic functions handy for agent development.

 SPDX-FileCopyrightText: © 2009-2013 Hewlett-Packard Development Company, L.P.
 SPDX-FileCopyrightText: © 2015 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

/*!
 * \file
 * \brief libfossagent.c contains general use functions for agents.
 */

#include "libfossology.h"

#define FUNCTION

/**
 * \brief Get the upload tree table name for a given upload
 * \param dbManager The DB manager in use
 * \param uploadId  ID of the upload
 * \return Upload tree table name of the upload.
 */
char* getUploadTreeTableName(fo_dbManager* dbManager, int uploadId)
{
  char* result;
  PGresult* resTableName = fo_dbManager_ExecPrepared(
    fo_dbManager_PrepareStamement(
      dbManager,
      "getUploadTreeTableName",
      "SELECT uploadtree_tablename from upload where upload_pk=$1 limit 1",
      int),
    uploadId
  );
  if (!resTableName)
  {
    result = g_strdup("uploadtree");
    return result;
  }

  if (PQntuples(resTableName) == 0)
  {
    PQclear(resTableName);
    result = g_strdup("uploadtree");
    return result;
  }

  result = g_strdup(PQgetvalue(resTableName, 0, 0));
  PQclear(resTableName);
  return result;
}

/**
 * \brief Get all file IDs (pfile_fk) for a given upload
 * \param dbManager fo_dbManager in use
 * \param uploadId  ID of the upload
 * \param ignoreFilesWithMimeType To ignore Files With MimeType
 * \return File IDs for the given upload
 */
PGresult* queryFileIdsForUpload(fo_dbManager* dbManager, int uploadId, bool ignoreFilesWithMimeType)
{
  PGresult* result;
  char SQL[1024];

  char* uploadtreeTableName = getUploadTreeTableName(dbManager, uploadId);
  char* queryName;

  if (strcmp(uploadtreeTableName, "uploadtree_a") == 0)
  {
    queryName = g_strdup_printf("queryFileIdsForUpload.%s", uploadtreeTableName);
    g_snprintf(SQL, sizeof(SQL), "select distinct(pfile_fk) from %s join pfile on pfile_pk=pfile_fk where upload_fk=$1 and (ufile_mode&x'3C000000'::int)=0",
      uploadtreeTableName);
  }
  else {
    queryName = g_strdup_printf("queryFileIdsForUpload.%s", uploadtreeTableName);
    g_snprintf(SQL, sizeof(SQL), "select distinct(pfile_fk) from %s join pfile on pfile_pk=pfile_fk where (ufile_mode&x'3C000000'::int)=0",
      uploadtreeTableName);
  }

  if (ignoreFilesWithMimeType)
  {
    queryName = g_strdup_printf("%s.%s", queryName, "WithMimeType");
    strcat(SQL, " AND (pfile_mimetypefk not in (SELECT mimetype_pk from mimetype where mimetype_name=any(string_to_array(( \
      SELECT conf_value from sysconfig where variablename='SkipFiles'),','))))");
  }

  if (strcmp(uploadtreeTableName, "uploadtree_a") == 0)
  {
    result = fo_dbManager_ExecPrepared(
      fo_dbManager_PrepareStamement(
        dbManager,
        queryName,
        SQL,
        int),
      uploadId
    );
    g_free(queryName);
  }
  else
  {
    result = fo_dbManager_ExecPrepared(
      fo_dbManager_PrepareStamement(
        dbManager,
        queryName,
        SQL)
    );
    g_free(queryName);
  }

  g_free(uploadtreeTableName);

  return result;
}

/**
 * \brief Get the pfile name for a given file ID
 * \param dbManager fo_dbManager in use
 * \param fileId    File ID (pfile_pk)
 * \return The file name (`SHA1.MD5.SIZE`)
 */
char* queryPFileForFileId(fo_dbManager* dbManager, long fileId)
{
  PGresult* fileNameResult = fo_dbManager_ExecPrepared(
     fo_dbManager_PrepareStamement(
       dbManager,
       "queryPFileForFileId",
       "select pfile_sha1 || '.' || pfile_md5 ||'.'|| pfile_size AS pfilename from pfile where pfile_pk=$1",
       long),
       fileId
    );

  if (PQntuples(fileNameResult) == 0)
  {
    PQclear(fileNameResult);
    return NULL;
  }

  char* pFile = g_strdup(PQgetvalue(fileNameResult, 0, 0));
  PQclear(fileNameResult);
  return pFile;
}

/*!
 \brief Get the latest enabled agent key (agent_pk) from the database.

 \param pgConn Database connection object pointer.
 \param agent_name Name of agent to look up.
 \param Upload_pk is no longer used.
 \param rev Agent revision, if given this is the exact revision of the agent being requested.
 \param agent_desc Description of the agent.  Used to write a new agent record in the
                   case where no enabled agent records exist for this agent_name.
 \return On success return agent_pk. On sql failure, return 0, and the error will be
         written to stdout.
 \todo This function is not checking if the agent is enabled. And it is not setting
       agent version when an agent record is inserted.
 */
FUNCTION int fo_GetAgentKey(PGconn* pgConn, const char* agent_name, long Upload_pk, const char* rev, const char* agent_desc)
{
  int Agent_pk = -1;    /* agent identifier */
  char sql[256];
  char sqlselect[256];
  char sqlupdate[256];
  PGresult* result;

  /* get the exact agent rec requested */
  sprintf(sqlselect, "SELECT agent_pk,agent_desc FROM agent WHERE agent_name ='%s' order by agent_ts desc limit 1",
    agent_name);
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
  /* Compare agent_desc */
  if(!(strcmp(PQgetvalue(result, 0, 1),agent_desc) == 0)){
    PQclear(result);
    sprintf(sqlupdate, "UPDATE agent SET agent_desc = E'%s' where agent_pk = '%d'",agent_desc, Agent_pk);
    result = PQexec(pgConn, sqlupdate);
  }
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
\param upload_pk  ID of the upload
\param agent_pk   Agents should get this from fo_GetAgentKey()
\param tableName  ars table name
\param ars_status   Status to update ars_status.  May be null.
\param ars_success  Automatically set to false if ars_pk is zero.

\return On success write the ars record and return the ars_pk.
On sql failure, return 0, and the error will be written to stdout.
*/
FUNCTION int fo_WriteARS(PGconn* pgConn, int ars_pk, int upload_pk, int agent_pk,
  const char* tableName, const char* ars_status, int ars_success)
{
  char sql[1024];
  PGresult* result;

  /* does ars table exist?  If not, create it.  */
  if (!fo_CreateARSTable(pgConn, tableName)) return (0);

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
    result = PQexec(pgConn, sql);
    if (fo_checkPQresult(pgConn, result, sql, __FILE__, __LINE__))
      return (0);
    ars_pk = atoi(PQgetvalue(result, 0, 0));
    PQclear(result);
  }
  else
  {
    /* If ars_pk is not null, update success, status and endtime */
    if (ars_status)
    {
      snprintf(sql, sizeof(sql), "update %s set ars_success=%s, ars_status='%s',ars_endtime=now() where ars_pk = %d",
        tableName, ars_success ? "True" : "False", ars_status, ars_pk);
    }
    else
    {
      snprintf(sql, sizeof(sql), "update %s set ars_success=%s, ars_endtime=now() where ars_pk = %d",
        tableName, ars_success ? "True" : "False", ars_pk);
    }
    result = PQexec(pgConn, sql);
    if (fo_checkPQcommand(pgConn, result, sql, __FILE__, __LINE__)) return 0;
  }
  return (ars_pk);
}  /* fo_WriteARS() */


/**
\brief Create ars table if it doesn't already exist.

\param pgConn Database connection object pointer.
\param tableName  ars table name

\return 0 on failure
*/
FUNCTION int fo_CreateARSTable(PGconn* pgConn, const char* tableName)
{
  char sql[1024];
  PGresult* result;

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
 * \brief Get the maximum group privilege
 * \param permGroup   Permission level of the group
 * \param permPublic  Public permission on the upload
 * \return The maximum privilege allowed
 */
FUNCTION int max(int permGroup, int permPublic)
{
  return ( permGroup > permPublic ) ? permGroup : permPublic;
}

/**
 * \brief Get the minimum permission level required
 * \param user_perm     User level permission on the upload
 * \param permExternal  External permission level on the upload
 * \return The minimum permission required
 */
FUNCTION int min(int user_perm, int permExternal)
{
  return ( user_perm < permExternal ) ? user_perm: permExternal;
}

/**
* \brief Get users permission to this upload
*
* \param pgConn Database connection object pointer.
* \param upload_pk  Upload ID
* \param user_pk    User ID
* \param user_perm  Privilege of user
*
* \return permission (PERM_) this user has for UploadPk
*/
FUNCTION int getEffectivePermissionOnUpload(PGconn* pgConn, long UploadPk, int user_pk, int user_perm)
{
  PGresult* result;
  char SQL[1024];
  int permGroup=0, permPublic=0;


  /* Get the user permission level for this upload */
  snprintf(SQL, sizeof(SQL),
           "select max(perm) as perm \
            from perm_upload, group_user_member \
            where perm_upload.upload_fk=%ld \
              and user_fk=%d \
              and group_user_member.group_fk=perm_upload.group_fk",
           UploadPk, user_pk);
  result = PQexec(pgConn, SQL);
  if (!fo_checkPQresult(pgConn, result, SQL, __FILE__, __LINE__)
    && PQntuples(result) > 0)
  {
    permGroup = atoi(PQgetvalue(result, 0, 0));
  }
  PQclear(result);

  /* Get the public permission level */
  snprintf(SQL, sizeof(SQL),
           "select public_perm \
            from upload \
            where upload_pk=%ld",
           UploadPk);
  result = PQexec(pgConn, SQL);
  fo_checkPQresult(pgConn, result, SQL, __FILE__, __LINE__);
  if (!fo_checkPQresult(pgConn, result, SQL, __FILE__, __LINE__)
    && PQntuples(result) > 0)
  {
    permPublic = atoi(PQgetvalue(result, 0, 0));
  }
  PQclear(result);

  if (user_perm >= PLUGIN_DB_ADMIN)
  {
    return PERM_ADMIN;
  }
  else
  {
    return min(user_perm, max(permGroup, permPublic));
  }
}
 
/**
* \brief Get users permission to this upload
*
* \param pgConn Database connection object pointer.
* \param upload_pk  Upload ID
* \param user_pk    User ID
*
* \return permission (PERM_) this user has for UploadPk
*/
FUNCTION int GetUploadPerm(PGconn* pgConn, long UploadPk, int user_pk)
{
  PGresult* result;
  char SQL[1024];
  int user_perm;

  /* Check the users PLUGIN_DB level.  PLUGIN_DB_ADMIN are superusers. */
  snprintf(SQL, sizeof(SQL), "select user_perm from users where user_pk='%d'", user_pk);
  result = PQexec(pgConn, SQL);
  fo_checkPQresult(pgConn, result, SQL, __FILE__, __LINE__);
  if (PQntuples(result) < 1)
  {
    LOG_ERROR("No records returned in %s", SQL);
    return PERM_NONE;
  }
  user_perm = atoi(PQgetvalue(result, 0, 0));
  PQclear(result);
  if (user_perm >= PLUGIN_DB_ADMIN)
  {
    return PERM_ADMIN;
  }

  return getEffectivePermissionOnUpload(pgConn, UploadPk, user_pk, user_perm);
}


/**
* \brief Get the uploadtree table name for this upload_pk
*        If upload_pk does not exist, return "uploadtree".
*
* \param pgConn Database connection object pointer.
* \param upload_pk  Upload ID
*
* \return uploadtree table name, or null if upload_pk does not exist.
* \note Caller must free the (non-null) returned value.
*/
FUNCTION char* GetUploadtreeTableName(PGconn* pgConn, int upload_pk)
{
  PGresult* result;
  char* uploadtree_tablename = 0;
  char SQL[1024];

  /* Get the uploadtree table name from the upload table */
  snprintf(SQL, sizeof(SQL), "select uploadtree_tablename from upload where upload_pk='%d'", upload_pk);
  result = PQexec(pgConn, SQL);
  fo_checkPQresult(pgConn, result, SQL, __FILE__, __LINE__);
  if (PQntuples(result) == 1) uploadtree_tablename = g_strdup(PQgetvalue(result, 0, 0));
  PQclear(result);

  return (uploadtree_tablename);
}


/**
* \brief Get the upload_pk and agent_pk
*        to find out the agent has already scanned the package.
*
* \param pgConn Database connection object pointer.
* \param upload_pk  Upload ID
* \param agent_pk  agentPk
*
* \return result, ars_pk if the agent has already scanned the package.
* \note Caller must free the (non-null) returned value.
*/
PGresult* checkDuplicateReq(PGconn* pgConn, int uploadPk, int agentPk)
{
  PGresult* result;
  char SQL[1024];
    /* if it is duplicate request (same upload_pk, sameagent_fk), then do not repeat */
    snprintf(SQL, sizeof(SQL),
        "select ars_pk from ars_master,agent \
                where agent_pk=agent_fk and ars_success=true \
                  and upload_fk='%d' and agent_fk='%d'",
        uploadPk, agentPk);
    result = PQexec(pgConn, SQL);
    return result;
}


/**
* \brief Get the upload_pk, agent_pk and ignoreFilesWithMimeType
*        to get all the file Ids for nomos.
*
* \param pgConn Database connection object pointer.
* \param upload_pk  uploadPk
* \param agent_pk  agentPk
* \param ignoreFilesWithMimeType To ignore Files With MimeType
*
* \return the result, the list of pfiles, require to be scan by nomos.
* \note Caller must free the (non-null) returned value.
*/
PGresult* getSelectedPFiles(PGconn* pgConn, int uploadPk, int agentPk, bool ignoreFilesWithMimeType)
{
  PGresult* result;
  char SQL[1024];

  snprintf(SQL, sizeof(SQL),
      "SELECT pfile_pk, pfile_sha1 || '.' || pfile_md5 || '.' || pfile_size AS pfilename \
          FROM (SELECT distinct(pfile_fk) AS PF FROM uploadtree WHERE upload_fk='%d' and (ufile_mode&x'3C000000'::int)=0) as SS \
          left outer join license_file on (PF=pfile_fk and agent_fk='%d') inner join pfile on PF=pfile_pk \
         WHERE (fl_pk IS null or agent_fk <>'%d')",
         uploadPk, agentPk, agentPk);
  
  if (ignoreFilesWithMimeType)
  {
        strcat(SQL, " AND (pfile_mimetypefk not in ( \
            SELECT mimetype_pk from mimetype where mimetype_name=any(string_to_array(( \
            SELECT conf_value from sysconfig where variablename='SkipFiles'),','))))");
  }
  result = PQexec(pgConn, SQL);
  return result;
}
