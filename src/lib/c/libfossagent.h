/*
 SPDX-FileCopyrightText: © 2011 Hewlett-Packard Development Company, L.P.
 SPDX-FileCopyrightText: © 2015 Siemens AG

 SPDX-License-Identifier: LGPL-2.1-only
*/

#ifndef LIBFOSSAGENT_H
#define LIBFOSSAGENT_H

#include <stdlib.h>
#include <stdio.h>
#include <stdbool.h>
#include <libpq-fe.h>

#include "libfossdbmanager.h"

char* getUploadTreeTableName(fo_dbManager* dbManager, int uploadId);
PGresult* queryFileIdsForUpload(fo_dbManager* dbManager, int uploadId, bool ignoreFilesWithMimeType);
char* queryPFileForFileId(fo_dbManager* dbManager, long int fileId);
int fo_GetAgentKey(PGconn* pgConn, const char* agent_name, long unused, const char* cpunused, const char* agent_desc);
int fo_WriteARS(PGconn* pgConn, int ars_pk, int upload_pk, int agent_pk,
  const char* tableName, const char* ars_status, int ars_success);
int fo_CreateARSTable(PGconn* pgConn, const char* table_name);
int getEffectivePermissionOnUpload(PGconn* pgConn, long UploadPk, int user_pk, int user_perm);
int GetUploadPerm(PGconn* pgConn, long UploadPk, int user_pk);
char* GetUploadtreeTableName(PGconn* pgConn, int upload_pk);
PGresult* checkDuplicateReq(PGconn* pgConn, int uploadPk, int agentPk);
PGresult* getSelectedPFiles(PGconn* pgConn, int uploadPk, int agentPk, bool ignoreFilesWithMimeType);

#endif
