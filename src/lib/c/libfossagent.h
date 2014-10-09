/**************************************************************
 Copyright (C) 20011 Hewlett-Packard Development Company, L.P.
  
 This library is free software; you can redistribute it and/or
 modify it under the terms of the GNU Lesser General Public
 License version 2.1 as published by the Free Software Foundation.

 This library is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU
 Lesser General Public License for more details.

 You should have received a copy of the GNU Lesser General Public License
 along with this library; if not, write to the Free Software Foundation, Inc.0
 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 **************************************************************/
#ifndef LIBFOSSAGENT_H
#define LIBFOSSAGENT_H

#include <stdlib.h>
#include <stdio.h>
#include <libpq-fe.h>

char* getUploadTreeTableName (fo_dbManager* dbManager, int uploadId);
PGresult* queryFileIdsForUpload(fo_dbManager* dbManager, int uploadId);
char* queryPFileForFileId(fo_dbManager* dbManager, long int fileId);
int  fo_GetAgentKey   (PGconn *pgConn, const char *agent_name, long unused, const char *cpunused, const char *agent_desc);
int fo_WriteARS       (PGconn *pgConn, int ars_pk, int upload_pk, int agent_pk,
                         const char *tableName, const char *ars_status, int ars_success);
int fo_CreateARSTable (PGconn *pgConn, const char *table_name);
int GetUploadPerm     (PGconn *pgConn, long UploadPk, int user_pk);
char *GetUploadtreeTableName(PGconn *pgConn, int upload_pk);

#endif
