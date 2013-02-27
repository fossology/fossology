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

/*  Permission, Group struct */
struct PermGroup_struct
{
  char  *user_name;          /* from users table */
  int    user_pk;            /* from users table */
  int    group_pk;           /* from groups table */
  int    new_upload_group_fk;/* from users table */
  int    new_upload_perm;    /* from users table */
};
typedef struct PermGroup_struct PermGroup_t, *pPermGroup_t;

int  fo_GetAgentKey   (PGconn *pgConn, char *agent_name, long unused, char *cpunused, char *agent_desc);
int fo_WriteARS(PGconn *pgConn, int ars_pk, int upload_pk, int agent_pk,
                         char *tableName, char *ars_status, int ars_success);
int fo_CreateARSTable(PGconn *pgConn, char *table_name);

pPermGroup_t GetUserGroup(long UploadPk);
int GetUploadPerm(long UploadPk, pPermGroup_t pPG, int user_pk);

#endif
