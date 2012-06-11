/********************************************************
 Copyright (C) 2007-2012 Hewlett-Packard Development Company, L.P.

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

 ********************************************************/
/**
 * \file util.c
 * \brief local function of delagent
 *
 * delagent: Remove an upload from the DB and repository
 *
 */
#include "delagent.h"

int Verbose = 0;
int Test = 0;
PGconn* db_conn = NULL;        // the connection to Database

/**
 * \brief DeleteLicense()
 * 
 *   Given an upload ID, delete all licenses associated with it.
 *   The DoBegin flag determines whether BEGIN/COMMIT should be called.
 *   Do this if you want to reschedule license analysis.
 *
 * \param long UploadId the upload id
 */
void DeleteLicense (long UploadId)
{
  char TempTable[256];
  char SQL[MAXSQL];
  PGresult *result;
  long items=0;

  if (Verbose) { printf("Deleting licenses for upload %ld\n",UploadId); }

  result = PQexec(db_conn, "SET statement_timeout = 0;"); /* no timeout */
  if (fo_checkPQcommand(db_conn, result, "SET statement_timeout = 0;", __FILE__, __LINE__)) exit(-1);
  PQclear(result);  
  result = PQexec(db_conn, "BEGIN;");
  if (fo_checkPQcommand(db_conn, result, "BEGIN;", __FILE__, __LINE__)) exit(-1);
  PQclear(result);
  
  memset(TempTable,'\0',sizeof(TempTable));
  snprintf(TempTable,sizeof(TempTable),"DelLic_%ld",UploadId);

  /* Create the temp table */
  if (Verbose) { printf("# Creating temp table: %s\n",TempTable); }

  /* Get the list of pfiles to process */
  memset(SQL,'\0',sizeof(SQL));
  snprintf(SQL,sizeof(SQL),"SELECT DISTINCT(pfile_fk) FROM uploadtree WHERE upload_fk = '%ld' ;",UploadId);
  result = PQexec(db_conn, SQL);
  if (fo_checkPQresult(db_conn, result, SQL, __FILE__, __LINE__)) exit(-1);
  items = PQntuples(result);
  PQclear(result);
  /***********************************************/
  /* delete pfile licenses */
  if (Verbose) { printf("# Deleting licenses\n"); }
  memset(SQL,'\0',sizeof(SQL));
  snprintf(SQL,sizeof(SQL),"DELETE FROM licterm_name WHERE pfile_fk IN (SELECT pfile_fk FROM uploadtree WHERE upload_fk = '%ld');",UploadId);
  result = PQexec(db_conn, SQL);
  if (fo_checkPQcommand(db_conn, result, SQL, __FILE__, __LINE__)) exit(-1);
  PQclear(result);

  memset(SQL,'\0',sizeof(SQL));
  snprintf(SQL,sizeof(SQL),"DELETE FROM agent_lic_status WHERE pfile_fk IN (SELECT pfile_fk FROM uploadtree WHERE upload_fk = '%ld');",UploadId);
  result = PQexec(db_conn, SQL);
  if (fo_checkPQcommand(db_conn, result, SQL, __FILE__, __LINE__)) exit(-1);
  PQclear(result);

  memset(SQL,'\0',sizeof(SQL));
  snprintf(SQL,sizeof(SQL),"DELETE FROM agent_lic_meta WHERE pfile_fk IN (SELECT pfile_fk FROM uploadtree WHERE upload_fk = '%ld');",UploadId);
  result = PQexec(db_conn, SQL);
  if (fo_checkPQcommand(db_conn, result, SQL, __FILE__, __LINE__)) exit(-1);
  PQclear(result);

  fo_scheduler_heart(items);

  /***********************************************/
  /* Commit the change! */
  if (Verbose) { printf("# Delete completed\n"); }
  if (Test)
  {
    result = PQexec(db_conn, "ROLLBACK;");
    if (fo_checkPQcommand(db_conn, result, "ROLLBACK", __FILE__, __LINE__)) exit(-1);
    PQclear(result);
  }
  else
  {
    result = PQexec(db_conn, "COMMIT;");
    if (fo_checkPQcommand(db_conn, result, "COMMIT", __FILE__, __LINE__)) exit(-1);
    PQclear(result);
#if 0
    /** Disabled: DB will take care of this **/
    if (Verbose) { printf("# Running vacuum and analyze\n"); }
    MyDBaccess(DB,"VACUUM ANALYZE agent_lic_status;");
    MyDBaccess(DB,"VACUUM ANALYZE agent_lic_meta;");
#endif
  }
  result = PQexec(db_conn, "SET statement_timeout = 120000;");
  if (fo_checkPQcommand(db_conn, result, "SET statement_timeout = 120000;", __FILE__, __LINE__)) exit(-1);
  PQclear(result);

  if (Verbose) { printf("Deleted licenses for upload %ld\n",UploadId); }
} /* DeleteLicense() */

/**
 * \brief DeleteUpload()
 *  
 *  Given an upload ID, delete it.
 *
 *  param long UploadId the upload id
 */
void DeleteUpload (long UploadId)
{
  char *S;
  int Row,MaxRow;
  char TempTable[256];
  PGresult *result, *pfile_result;
  char SQL[MAXSQL];

  if (Verbose) { printf("Deleting upload %ld\n",UploadId); }
  result = PQexec(db_conn, "SET statement_timeout = 0;"); /* no timeout */
  if (fo_checkPQcommand(db_conn, result, "SET statement_timeout = 0;", __FILE__, __LINE__)) exit(-1);
  PQclear(result);
  result = PQexec(db_conn, "BEGIN;");
  if (fo_checkPQcommand(db_conn, result, "BEGIN;", __FILE__, __LINE__)) exit(-1);
  PQclear(result);
 
  memset(TempTable,'\0',sizeof(TempTable));
  snprintf(TempTable,sizeof(TempTable),"DelUp_%ld",UploadId);

  /***********************************************/
  /*** Delete everything that impacts the UI ***/
  /***********************************************/

  /***********************************************/
  /* Delete the upload from the folder-contents table */
  memset(SQL,'\0',sizeof(SQL));
  if (Verbose) { printf("# Deleting foldercontents\n"); }
  snprintf(SQL,sizeof(SQL),"DELETE FROM foldercontents WHERE (foldercontents_mode & 2) != 0 AND child_id = %ld;",UploadId);
  result = PQexec(db_conn, SQL);
  if (fo_checkPQcommand(db_conn, result, SQL, __FILE__, __LINE__)) exit(-1);
  PQclear(result);

  if (!Test)
  {
    /* The UI depends on uploadtree and folders for navigation.
	   Delete them now to block timeouts from the UI. */
    if (Verbose) { printf("# COMMIT;\n"); }
    result = PQexec(db_conn, "COMMIT;");
    if (fo_checkPQcommand(db_conn, result, "COMMIT;", __FILE__, __LINE__)) exit(-1);
    PQclear(result);
    if (Verbose) { printf("# BEGIN;\n"); }
    result = PQexec(db_conn, "BEGIN;");
    if (fo_checkPQcommand(db_conn, result, "BEGIN;", __FILE__, __LINE__)) exit(-1);
    PQclear(result);
  }

  /***********************************************/
  /*** Begin complicated stuff ***/
  /***********************************************/

  /* Get the list of pfiles to delete */
  /** These are all pfiles in the upload_fk that only appear once. **/
  memset(SQL,'\0',sizeof(SQL));
  if (Verbose) { printf("# Getting list of pfiles to delete\n"); }
  snprintf(SQL,sizeof(SQL),"SELECT DISTINCT pfile_pk,pfile_sha1 || '.' || pfile_md5 || '.' || pfile_size AS pfile INTO %s_pfile FROM uploadtree INNER JOIN pfile ON upload_fk = %ld AND pfile_fk = pfile_pk;",TempTable,UploadId);
  result = PQexec(db_conn, SQL);
  if (fo_checkPQcommand(db_conn, result, SQL, __FILE__, __LINE__)) exit(-1);
  PQclear(result);

  /* Remove pfiles with reuse */
  memset(SQL,'\0',sizeof(SQL));
  snprintf(SQL,sizeof(SQL),"DELETE FROM %s_pfile USING uploadtree WHERE pfile_pk = uploadtree.pfile_fk AND uploadtree.upload_fk != %ld;",TempTable,UploadId);
  result = PQexec(db_conn, SQL);
  if (fo_checkPQcommand(db_conn, result, SQL, __FILE__, __LINE__)) exit(-1);
  PQclear(result);

  memset(SQL,'\0',sizeof(SQL));
  snprintf(SQL,sizeof(SQL),"SELECT COUNT(*) FROM %s_pfile;",TempTable);
  result = PQexec(db_conn, SQL);
  if (fo_checkPQresult(db_conn, result, SQL, __FILE__, __LINE__)) exit(-1);
  if (Verbose) { printf("# Created pfile table: %ld entries\n",atol(PQgetvalue(result,0,0))); }
  PQclear(result);

  /***********************************************
   This begins the slow part that locks the DB.
   The problem is, we don't want to lock a critical row,
   otherwise the scheduler will lock and/or fail.
   ***********************************************/

  /***********************************************/
  /* delete pfile references from all the pfile dependent tables */
  /* Removed as cascade delete with pfile table
  if (Verbose) { printf("# Deleting from licterm_name\n"); }
  memset(SQL,'\0',sizeof(SQL));
  snprintf(SQL,sizeof(SQL),"DELETE FROM licterm_name USING %s_pfile WHERE pfile_fk = pfile_pk;",TempTable);
  MyDBaccess(DB,SQL);

  if (Verbose) { printf("# Deleting from agent_lic_status\n"); }
  memset(SQL,'\0',sizeof(SQL));
  snprintf(SQL,sizeof(SQL),"DELETE FROM agent_lic_status USING %s_pfile WHERE pfile_fk = pfile_pk;",TempTable);
  MyDBaccess(DB,SQL);

  if (Verbose) { printf("# Deleting from agent_lic_meta\n"); }
  memset(SQL,'\0',sizeof(SQL));
  snprintf(SQL,sizeof(SQL),"DELETE FROM agent_lic_meta USING %s_pfile WHERE pfile_fk = pfile_pk;",TempTable);
  MyDBaccess(DB,SQL);

  if (Verbose) { printf("# Deleting from attrib\n"); }
  memset(SQL,'\0',sizeof(SQL));
  snprintf(SQL,sizeof(SQL),"DELETE FROM attrib USING %s_pfile WHERE pfile_fk = pfile_pk;",TempTable);
  MyDBaccess(DB,SQL);
  
  if (Verbose) { printf("# Deleting pkg_deb_req\n"); }
  memset(SQL,'\0',sizeof(SQL));
  snprintf(SQL,sizeof(SQL),"DELETE FROM pkg_deb_req USING pkg_deb,%s_pfile WHERE pkg_fk = pkg_pk AND pfile_fk = pfile_pk;",TempTable);
  MyDBaccess(DB,SQL);

  if (Verbose) { printf("# Deleting pkg_deb\n"); }
  memset(SQL,'\0',sizeof(SQL));
  snprintf(SQL,sizeof(SQL),"DELETE FROM pkg_deb USING %s_pfile WHERE pfile_fk = pfile_pk;",TempTable);
  MyDBaccess(DB,SQL);

  if (Verbose) { printf("# Deleting pkg_rpm_req\n"); }
  memset(SQL,'\0',sizeof(SQL));
  snprintf(SQL,sizeof(SQL),"DELETE FROM pkg_rpm_req USING pkg_rpm,%s_pfile WHERE pkg_fk = pkg_pk AND pfile_fk = pfile_pk;",TempTable);
  MyDBaccess(DB,SQL);

  if (Verbose) { printf("# Deleting pkg_rpm\n"); }
  memset(SQL,'\0',sizeof(SQL));
  snprintf(SQL,sizeof(SQL),"DELETE FROM pkg_rpm USING %s_pfile WHERE pfile_fk = pfile_pk;",TempTable);
  MyDBaccess(DB,SQL);

  if (Verbose) { printf("# Deleting from licese_file\n"); }
  memset(SQL,'\0',sizeof(SQL));
  snprintf(SQL,sizeof(SQL),"DELETE FROM license_file USING %s_pfile "
      "WHERE pfile_fk = pfile_pk;",TempTable);
  MyDBaccess(DB,SQL);

  */
  /* These table cascade deleted with upload table
  
  memset(SQL,'\0',sizeof(SQL));
  if (Verbose) { printf("# Deleting nomos_ars\n"); }
  snprintf(SQL,sizeof(SQL),"DELETE FROM nomos_ars WHERE upload_fk = %ld;",UploadId);
  MyDBaccess(DB,SQL);

  memset(SQL,'\0',sizeof(SQL));
  if (Verbose) { printf("# Deleting bucket_ars\n"); }
  snprintf(SQL,sizeof(SQL),"DELETE FROM bucket_ars WHERE upload_fk = %ld;",UploadId);
  MyDBaccess(DB,SQL);

  memset(SQL,'\0',sizeof(SQL));
  if (Verbose) { printf("# Deleting bucket_container\n"); }
  snprintf(SQL,sizeof(SQL),"DELETE FROM bucket_container USING uploadtree WHERE uploadtree_fk = uploadtree_pk AND upload_fk = %ld;",UploadId);
  MyDBaccess(DB,SQL);

  if (Verbose) { printf("# Deleting bucket_file\n"); }
  memset(SQL,'\0',sizeof(SQL));
  snprintf(SQL,sizeof(SQL),"DELETE FROM bucket_file USING %s_pfile WHERE pfile_fk = pfile_pk;",TempTable);
  MyDBaccess(DB,SQL);

  if (Verbose) { printf("# Deleting copyright\n"); }
  memset(SQL,'\0',sizeof(SQL));
  snprintf(SQL,sizeof(SQL),"DELETE FROM copyright USING %s_pfile WHERE pfile_fk = pfile_pk;",TempTable);
  MyDBaccess(DB,SQL);
  
  */

  /***********************************************/
  /* Blow away jobs */
  /*****
   There is an ordering issue.
   The delete from attrib and pfile can take a long time (hours for
   a source code DVD).
   If we delete from the jobqueue first, then it will hang the scheduler
   as the scheduler tries to update the jobqueue record.  (Row is locked
   by the delete process.)
   The solution is to delete the jobqueue LAST, so the scheduler won't hang.
   *****/
  /* Cascade delete with upload table */ 

  /***********************************************/
  /* Delete the actual upload */
  /* uploadtree Cascade delete with upload table
  memset(SQL,'\0',sizeof(SQL));
  if (Verbose) { printf("# Deleting uploadtree\n"); }
  snprintf(SQL,sizeof(SQL),"DELETE FROM uploadtree WHERE upload_fk = %ld;",UploadId);
  MyDBaccess(DB,SQL);
  */
  memset(SQL,'\0',sizeof(SQL));
  if (Verbose) { printf("# Deleting upload\n"); }
  snprintf(SQL,sizeof(SQL),"DELETE FROM upload WHERE upload_pk = %ld;",UploadId);
  result = PQexec(db_conn, SQL);
  if (fo_checkPQcommand(db_conn, result, SQL, __FILE__, __LINE__)) exit(-1);
  PQclear(result);

  /***********************************************/
  /* Commit the change! */
  /*
  if (Test)
  {
    if (Verbose) { printf("# ROLLBACK\n"); }
    result = PQexec(db_conn, "ROLLBACK;");
    if (fo_checkPQcommand(db_conn, result, SQL, __FILE__, __LINE__)) exit(-1);
    PQclear(result);
  }
  else
  {
    if (Verbose) { printf("# COMMIT\n"); }
    result = PQexec(db_conn, "COMMIT;");
    if (fo_checkPQcommand(db_conn, result, SQL, __FILE__, __LINE__)) exit(-1);
    PQclear(result);
  */
#if 0
    /** Disabled: Database will take care of this **/
    if (Verbose) { printf("# VACUUM and ANALYZE\n"); }
    MyDBaccess(DB,"VACUUM ANALYZE agent_lic_status;");
    MyDBaccess(DB,"VACUUM ANALYZE agent_lic_meta;");
    MyDBaccess(DB,"VACUUM ANALYZE attrib;");
    MyDBaccess(DB,"VACUUM ANALYZE pfile;");
    MyDBaccess(DB,"VACUUM ANALYZE foldercontents;");
    MyDBaccess(DB,"VACUUM ANALYZE upload;");
    MyDBaccess(DB,"VACUUM ANALYZE uploadtree;");
    MyDBaccess(DB,"VACUUM ANALYZE jobdepends;");
    MyDBaccess(DB,"VACUUM ANALYZE jobqueue;");
    MyDBaccess(DB,"VACUUM ANALYZE job;");
#endif
  //}

  /* Get the file listing -- needed for deleting pfiles from the repository. */
  memset(SQL,'\0',sizeof(SQL));
  snprintf(SQL,sizeof(SQL),"SELECT * FROM %s_pfile ORDER BY pfile_pk;",TempTable);
  pfile_result = PQexec(db_conn, SQL);
  if (fo_checkPQresult(db_conn, pfile_result, SQL, __FILE__, __LINE__)) exit(-1);
  MaxRow = PQntuples(pfile_result);

  /***********************************************/
  /* delete from pfile is SLOW due to constraint checking.
     Do it separately. */
  if (Verbose) { printf("# Deleting from pfile\n"); }
  memset(SQL,'\0',sizeof(SQL));
  snprintf(SQL,sizeof(SQL),"DELETE FROM pfile USING %s_pfile WHERE pfile.pfile_pk = %s_pfile.pfile_pk;",TempTable,TempTable);
  result = PQexec(db_conn, SQL);
  if (fo_checkPQcommand(db_conn, result, SQL, __FILE__, __LINE__)) exit(-1);
  PQclear(result);
  memset(SQL,'\0',sizeof(SQL));
  snprintf(SQL,sizeof(SQL),"DROP TABLE %s_pfile;",TempTable);
  result = PQexec(db_conn, SQL);
  if (fo_checkPQcommand(db_conn, result, SQL, __FILE__, __LINE__)) exit(-1);
  PQclear(result);

  result = PQexec(db_conn, "SET statement_timeout = 120000;");
  if (fo_checkPQcommand(db_conn, result, "SET statement_timeout = 120000;", __FILE__, __LINE__)) exit(-1);
  PQclear(result);

  if (Verbose) { printf("Deleted upload %ld from DB, now doing repository.\n",UploadId); }

  if (Test)
  {
    if (Verbose) { printf("# ROLLBACK\n"); }
    result = PQexec(db_conn, "ROLLBACK;");
    if (fo_checkPQcommand(db_conn, result, "ROLLBACK", __FILE__, __LINE__)) exit(-1);
    PQclear(result);
  }
  else
  {
    if (Verbose) { printf("# COMMIT\n"); }
    result = PQexec(db_conn, "COMMIT;");
    if (fo_checkPQcommand(db_conn, result, "COMMIT", __FILE__, __LINE__)) exit(-1);
    PQclear(result);
  }
 /***********************************************/
  /* Whew!  Now to delete the actual pfiles from the repository. */
  /** If someone presses ^C now, then at least the DB is accurate. **/
  if (Test <= 1)
  {
    for(Row=0; Row<MaxRow; Row++)
    {
      memset(SQL,'\0',sizeof(SQL));
      S = PQgetvalue(pfile_result,Row,1); /* sha1.md5.len */
      if (fo_RepExist("license",S))
      {
        if (Test) printf("TEST: Delete %s %s\n","license",S);
        else fo_RepRemove("license",S);
      }
      if (fo_RepExist("files",S))
      {
        if (Test) printf("TEST: Delete %s %s\n","files",S);
        else fo_RepRemove("files",S);
      }
      if (fo_RepExist("gold",S))
      {
        if (Test) printf("TEST: Delete %s %s\n","gold",S);
        else fo_RepRemove("gold",S);
      }
      fo_scheduler_heart(1);
    }
  } /* if Test <= 1 */
  PQclear(pfile_result);
  if (Verbose) { printf("Deleted upload %ld\n",UploadId); }
} /* DeleteUpload() */

/**
 * \brief ListFoldersRecurse(): Draw folder tree.
 *  
 *   if DelFlag is set, then all child uploads are
 *   deleted and the folders are deleted.
 *
 * \param long Parent the parent folder id
 * \param int Depth
 * \param int Row
 * \param int DelFlag
 *
 */
void ListFoldersRecurse	(long Parent, int Depth,
    int Row, int DelFlag)
{
  int r,MaxRow;
  long Fid;
  int i;
  char *Desc;
  PGresult *result;
  char SQL[MAXSQL];

  /* Find all folders with this parent and recurse */
  memset(SQL,'\0',sizeof(SQL));
  snprintf(SQL,sizeof(SQL),"SELECT folder_pk,parent,name,description,upload_pk FROM folderlist ORDER BY name,parent,folder_pk;");
  result = PQexec(db_conn, SQL);
  if (fo_checkPQresult(db_conn, result, SQL, __FILE__, __LINE__)) exit(-1);
  MaxRow = PQntuples(result);
  for(r=0; r < MaxRow; r++)
  {
    if (r == Row) continue; /* skip self-loops */
    /* NOTE: There can be an infinite loop if two rows point to each other.
       A->parent == B and B->parent == A  */
    if (atol(PQgetvalue(result,r,1)) == Parent)
    {
      if (!DelFlag)
      {
        for(i=0; i<Depth; i++) fputs("   ",stdout);
      }
      Fid = atol(PQgetvalue(result,r,0));
      if (Fid != 0)
      {
        if (!DelFlag)
        {
          printf("%4ld :: %s",Fid,PQgetvalue(result,r,2));
          Desc = PQgetvalue(result,r,3);
          if (Desc && Desc[0]) printf(" (%s)",Desc);
          printf("\n");
        }
        ListFoldersRecurse(Fid,Depth+1,r,DelFlag);
      }
      else
      {
        if (DelFlag) DeleteUpload(atol(PQgetvalue(result,r,4)));
        else printf("%4s :: Contains: %s\n","--",PQgetvalue(result,r,2));
      }
    }
  }
  PQclear(result);

  /* if we're deleting folders, do it now */
  if (DelFlag)
  {
    switch(Parent)
    {
      case 1:	/* skip default parent */
        printf("INFO: Default folder not deleted.\n");
        break;
      case 0:	/* it's an upload */
        break;
      default:	/* it's a folder */
        memset(SQL,'\0',sizeof(SQL));
        snprintf(SQL,sizeof(SQL),"DELETE FROM foldercontents WHERE foldercontents_mode = 1 AND child_id = '%ld';",Parent);
        if (Test) printf("TEST: %s\n",SQL);
        else
        {
          result = PQexec(db_conn, SQL);
          if (fo_checkPQcommand(db_conn, result, SQL, __FILE__, __LINE__)) exit(-1);
          PQclear(result);
        }

        memset(SQL,'\0',sizeof(SQL));
        snprintf(SQL,sizeof(SQL),"DELETE FROM folder WHERE folder_pk = '%ld';",Parent);
        if (Test) printf("TEST: %s\n",SQL);
        else
        {
          result = PQexec(db_conn, SQL);
          if (fo_checkPQcommand(db_conn, result, SQL, __FILE__, __LINE__)) exit(-1);
          PQclear(result);
        }
        break;
    } /* switch() */
  }
} /* ListFoldersRecurse() */

/**
 * \brief ListFolders(): List every folder.
 */
void ListFolders ()
{
  int i,j,MaxRow;
  long Fid;	/* folder ids */
  int DetachFlag=0;
  int Match;
  char *Desc;
  char SQL[MAXSQL];
  PGresult *result;

  printf("# Folders\n");
  memset(SQL,'\0',sizeof(SQL));
  snprintf(SQL,sizeof(SQL),"SELECT folder_name from folder where folder_pk =1;");
  result = PQexec(db_conn, SQL);
  if (fo_checkPQresult(db_conn, result, SQL, __FILE__, __LINE__)) exit(-1);

  printf("%4d :: %s\n", 1, PQgetvalue(result,0,0));
  PQclear(result);

  memset(SQL,'\0',sizeof(SQL));
  snprintf(SQL,sizeof(SQL),"SELECT folder_pk,parent,name,description,upload_pk FROM folderlist ORDER BY name,parent,folder_pk;");
  result = PQexec(db_conn, SQL);
  if (fo_checkPQresult(db_conn, result, SQL, __FILE__, __LINE__)) exit(-1);
  ListFoldersRecurse(1,1,-1,0);

  /* Find detached folders */
  MaxRow = PQntuples(result);
  DetachFlag=0;
  for(i=0; i < MaxRow; i++)
  {
    Fid = atol(PQgetvalue(result,i,1));
    if (Fid == 1) continue;	/* skip default parent */
    Match=0;
    for(j=0; (j<MaxRow) && !Match; j++)
    {
      if ((i!=j) && (atol(PQgetvalue(result,j,0)) == Fid)) Match=1;
    }
    if (!Match && !atol(PQgetvalue(result,i,4)))
    {
      if (!DetachFlag) { printf("# Unlinked folders\n"); DetachFlag=1; }
      printf("%4ld :: %s",Fid,PQgetvalue(result,i,2));
      Desc = PQgetvalue(result,i,3);
      if (Desc && Desc[0]) printf(" (%s)",Desc);
      printf("\n");
      ListFoldersRecurse(Fid,1,i,0);
    }
  }

  /* Find detached uploads */
  DetachFlag=0;
  for(i=0; i < MaxRow; i++)
  {
    Fid = atol(PQgetvalue(result,i,1));
    if (Fid == 1) continue;	/* skip default parent */
    Match=0;
    for(j=0; (j<MaxRow) && !Match; j++)
    {
      if ((i!=j) && (atol(PQgetvalue(result,j,0)) == Fid)) Match=1;
    }
    if (!Match && atol(PQgetvalue(result,i,4)))
    {
      if (!DetachFlag) { printf("# Unlinked uploads (uploads without folders)\n"); DetachFlag=1; }
      printf("%4s",PQgetvalue(result,i,4));
      printf(" :: %s",PQgetvalue(result,i,2));
      Desc = PQgetvalue(result,i,3);
      if (Desc && Desc[0]) printf(" (%s)",Desc);
      printf("\n");
    }
  }

  PQclear(result);
} /* ListFolders() */

/**
 * \brief ListUploads(): List every upload ID.
 *
 * \char *user_name - user name
 */
void ListUploads (int user_id, int user_perm)
{
  int Row,MaxRow;
  long NewPid;
  char SQL[MAXSQL];
  char sub_SQL[MAXSQL];
  PGresult *result;

  printf("# Uploads\n");
  memset(SQL,'\0',sizeof(SQL));
  memset(sub_SQL,'\0',sizeof(sub_SQL));
  if (user_perm != ADMIN_PERM)
  {
    snprintf(sub_SQL, sizeof(sub_SQL), "where user_fk=%d", user_id);
  }
  snprintf(SQL,sizeof(SQL),"SELECT upload_pk,upload_desc,upload_filename FROM upload %s ORDER BY upload_pk;", sub_SQL);
  result = PQexec(db_conn, SQL);
  if (fo_checkPQresult(db_conn, result, SQL, __FILE__, __LINE__)) exit(-1);

  /* list each value */
  MaxRow = PQntuples(result);
  for(Row=0; Row < MaxRow; Row++)
  {
    NewPid = atol(PQgetvalue(result,Row,0));
    if (NewPid >= 0)
    {
      char *S;
      printf("%ld :: %s",NewPid,PQgetvalue(result,Row,2));
      S = PQgetvalue(result,Row,1);
      if (S && S[0]) printf(" (%s)",S);
      printf("\n");
    }
  }
  PQclear(result);
} /* ListUploads() */

/**
 * \brief DeleteFolder()
 *
 *  Given a folder ID, delete it AND recursively delete everything below it!
 *  This includes upload deletion!
 * 
 * \param long FolderId the fold id to delete
 **/
void DeleteFolder (long FolderId)
{
  ListFoldersRecurse(FolderId,0,-1,1);
#if 0
  /** Disabled: Database will take care of this **/
  MyDBaccess(DB,"VACUUM ANALYZE foldercontents;");
  MyDBaccess(DB,"VACUUM ANALYZE folder;");
#endif
} /* DeleteFolder() */

/**********************************************************************/

/**
 * \brief ReadParameter()
 * 
 *  Read Parameter from scheduler.
 *  Process line elements.
 *
 * \param char *Parm the parameter string
 * 
 * \return 0 on OK, -1 on failure.
 *
 **/
int ReadParameter (char *Parm)
{
  char FullLine[MAXLINE];
  char *L;
  int rc=0;     /* assume no data */
  int Type=0;	/* 0=undefined; 1=delete; 2=list */
  int Target=0;	/* 0=undefined; 1=upload; 2=license; 3=folder */
  long Id;

  memset(FullLine,0,MAXLINE);

  if (!Parm)
  {
    return(-1);
  }
  if (Verbose > 1) fprintf(stderr,"DEBUG: Line='%s'\n",Parm);

  /* process the string. */
  L = Parm;
  while(isspace(L[0])) L++;

  /** Get the type of command: delete or list **/
  if (!strncasecmp(L,"DELETE",6) && isspace(L[6]))
  {
    Type=1; /* delete */
    L+=6;
  }
  else if (!strncasecmp(L,"LIST",4) && isspace(L[4]))
  {
    Type=2; /* list */
    L+=4;
  }
  while(isspace(L[0])) L++;
  /** Get the target **/
  if (!strncasecmp(L,"UPLOAD",6) && (isspace(L[6]) || !L[6]))
  {
    Target=1; /* upload */
    L+=6;
  }
  else if (!strncasecmp(L,"LICENSE",7) && (isspace(L[7]) || !L[7]))
  {
    Target=2; /* license */
    L+=7;
  }
  else if (!strncasecmp(L,"FOLDER",6) && (isspace(L[6]) || !L[6]))
  {
    Target=3; /* folder */
    L+=6;
  }
  while(isspace(L[0])) L++;
  Id = atol(L);

  /* Handle the request */
  if ((Type==1) && (Target==1))	{ DeleteUpload(Id); rc=1; }
  else if ((Type==1) && (Target==2))	{ DeleteLicense(Id); rc=1; }
  else if ((Type==1) && (Target==3))	{ DeleteFolder(Id); rc=1; }
  else if ((Type==2) && (Target==1))	{ ListUploads(0, ADMIN_PERM); rc=1; }
  else if ((Type==2) && (Target==2))	{ ListUploads(0, ADMIN_PERM); rc=1; }
  else if ((Type==2) && (Target==3))	{ ListFolders(0, ADMIN_PERM); rc=1; }
  else
  {
    LOG_FATAL("Unknown command: '%s'\n",Parm);
  }

  return(rc);
} /* ReadParameter() */

/**
 * \brief check if the upload can be deleted, that is the user have
 * the permissin to delte this upload
 * 
 * \param long upload_id - upload id
 * \param char *user_name - user name
 * 
 * \return 1: yes, can be deleted; -1: failure; 0: can not be deleted
 */
int check_permission_del(long upload_id, int user_id, int user_perm) 
{
  char SQL[MAXSQL] = {0};;
  PGresult *result = NULL;
  int count = 0;
  snprintf(SQL,sizeof(SQL),"SELECT count(*) FROM upload where upload_pk = %ld;", upload_id);
  result = PQexec(db_conn, SQL);
  if (fo_checkPQresult(db_conn, result, SQL, __FILE__, __LINE__)) return -1;
  count = atoi(PQgetvalue(result, 0, 0)); 
  if (count == 0) return -2; // this upload does not exist

  if (ADMIN_PERM == user_perm ) return 1; // admin can do anything
  if (user_perm < 7) return 0; // permission is less than 7, no delete permmission

  memset(SQL,'\0',sizeof(SQL));
  snprintf(SQL,sizeof(SQL),"SELECT count(*) FROM upload where upload_pk = %ld and user_fk = %d;", upload_id, user_id);
  result = PQexec(db_conn, SQL);
  if (fo_checkPQresult(db_conn, result, SQL, __FILE__, __LINE__)) return -1;
  count = 0;
  count = atoi(PQgetvalue(result, 0, 0)); 
  PQclear(result);
  if (count > 0) return 1; // can be deleted, above delete permiss(delete, debug and admin)
  else return -1; // have no the permission or the upload does not exist
}

/**
 * \brief if this account is valid
 * 
 * \param char *user - ussr name 
 * \param char *password - password
 *
 * \return 1: yes, valid; -1: failure; 0: invalid
 */
int authentication(char *user, char * password, int *user_id, int *user_perm)
{
  if (NULL == user || NULL == password)   return 0;
  char SQL[MAXSQL] = {0};
  char CMD[myBUFSIZ] = {0};
  PGresult *result;
  char user_seed[myBUFSIZ] = {0};
  char pass_hash_valid[myBUFSIZ] = {0};
  char pass_hash_actual[myBUFSIZ] = {0};
  FILE *file_hash = NULL;

  /** get user_seed, user_pass on one specified user */
  snprintf(SQL,sizeof(SQL),"SELECT user_seed, user_pass, user_perm, user_pk from users where user_name='%s';", user);
  result = PQexec(db_conn, SQL);
  if (fo_checkPQresult(db_conn, result, SQL, __FILE__, __LINE__)) return -1;
  strcpy(user_seed, PQgetvalue(result, 0, 0));
  strcpy(pass_hash_valid, PQgetvalue(result, 0, 1));
  *user_perm = atoi(PQgetvalue(result, 0, 2));
  *user_id = atoi(PQgetvalue(result, 0, 3));
  PQclear(result);
  if (user_seed[0] && pass_hash_valid[0]) 
  {
    snprintf(CMD, sizeof(CMD), "echo -n %s%s | openssl sha1", user_seed, password);  // get the hash code on seed+pass
    file_hash = popen(CMD,"r");
    if (!file_hash)
    {
      LOG_FATAL("ERROR, failed to get sha1 value\n");
      return -1;
    }
  }
  else return -1;

  fgets(pass_hash_actual, sizeof(pass_hash_actual), file_hash);
  if (pass_hash_actual[0] && pass_hash_actual[strlen(pass_hash_actual) - 1] == '\n')
  {
    pass_hash_actual[strlen(pass_hash_actual) - 1] = '\0'; // get rid of the new line character
  }
  pclose(file_hash);
  if (strcmp(pass_hash_valid, pass_hash_actual) == 0)
  {
    return 1;
  } 
  else return -1;
}

/***********************************************
 Usage():
 Command line options allow you to write the agent so it works
 stand alone, in addition to working with the scheduler.
 This simplifies code development and testing.
 So if you have options, have a Usage().
 Here are some suggested options (in addition to the program
 specific options you may already have).
 ***********************************************/
void Usage (char *Name)
{
  fprintf(stderr,"Usage: %s [options]\n",Name);
  fprintf(stderr,"  List or delete uploads.\n");
  fprintf(stderr,"  Options\n");
  fprintf(stderr,"  -i   :: Initialize the DB, then exit.\n");
  fprintf(stderr,"  -u   :: List uploads IDs.\n");
  fprintf(stderr,"  -U # :: Delete upload ID.\n");
  fprintf(stderr,"  -L # :: Delete ALL licenses associated with upload ID.\n");
  fprintf(stderr,"  -f   :: List folder IDs.\n");
  fprintf(stderr,"  -F # :: Delete folder ID and all uploads under this folder.\n");
  fprintf(stderr,"          Folder '1' is the default folder.  '-F 1' will delete\n");
  fprintf(stderr,"          every upload and folder in the navigation tree.\n");
  fprintf(stderr,"  -s   :: Run from the scheduler.\n");
  fprintf(stderr,"  -T   :: TEST -- do not update the DB or delete any files (just pretend)\n");
  fprintf(stderr,"  -v   :: Verbose (-vv for more verbose)\n");
  fprintf(stderr,"  -c # :: Specify the directory for the system configuration\n");
  fprintf(stderr,"  --user|-n # :: user name\n");
  fprintf(stderr,"  --password|-p # :: password\n");
} /* Usage() */
