/********************************************************
 Copyright (C) 2007-2013 Hewlett-Packard Development Company, L.P.
 Copyright (C) 2015-2016 Siemens AG

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

int printfInCaseOfVerbosity (const char *format, ...)
{
  va_list arg;
  int done = 0;

  if (Verbose)
  {
    va_start (arg, format);
    done = vprintf(format, arg);
    va_end (arg);
  }
  return done;
}

/**
 * \brief PQexecCheck()
 *
 * simple wrapper which includes PQexec and fo_checkPQcommand
 *
 */
PGresult * PQexecCheck(const char *desc, char *SQL, char *file, const int line)
{
  PGresult *result;

  if(desc == NULL)
  {
    printfInCaseOfVerbosity("# %s:%i: %s\n", file, line, SQL);
  }
  else
  {
    printfInCaseOfVerbosity("# %s:%i: %s (%s)\n", file, line, desc, SQL);
  }

  result = PQexec(db_conn, SQL);
  if (fo_checkPQcommand(db_conn, result, SQL, file, line))
  {
    exit(-1);
  }
  return result;
}

void PQexecCheckClear(const char *desc, char *SQL, char *file, const int line)
{
  PGresult *result;
  result = PQexecCheck(desc, SQL, file, line);
  PQclear(result);
}

/**
 * \brief if this account is valid
 *
 * \param char *user - user name
 * \param char *password - password
 * \param int *user_id - will be set to the id of the user
 * \param int *user_perm - will be set to the permission level of the user
 *
 * \return 1: invalid;
 *         0: yes, valid;
 *        -1: failure
 */
int authentication(char *user, char *password, int *user_id, int *user_perm)
{
  if (NULL == user || NULL == password)
  {
    return 1;
  }
  char SQL[MAXSQL] = {0};
  PGresult *result;
  char user_seed[myBUFSIZ] = {0};
  char pass_hash_valid[41] = {0};
  unsigned char pass_hash_actual_raw[21] = {0};
  char pass_hash_actual[41] = {0};

  /** get user_seed, user_pass on one specified user */
  snprintf(SQL,MAXSQL,"SELECT user_seed, user_pass, user_perm, user_pk from users where user_name=$1;");
  const char *values[1] = {user};
  int lengths[1] = {strlen(user)};
  int binary[1] = {0};
  result = PQexecParams(db_conn, SQL, 1, NULL, values, lengths, binary, 0);
  if (fo_checkPQresult(db_conn, result, SQL, __FILE__, __LINE__))
  {
    return -1;
  }
  if (!PQntuples(result))
  {
    return 1;
  }
  strcpy(user_seed, PQgetvalue(result, 0, 0));
  strcpy(pass_hash_valid, PQgetvalue(result, 0, 1));
  *user_perm = atoi(PQgetvalue(result, 0, 2));
  *user_id = atoi(PQgetvalue(result, 0, 3));
  PQclear(result);
  if (user_seed[0] && pass_hash_valid[0])
  {
    strcat(user_seed, password);  // get the hash code on seed+pass
    SHA1((unsigned char *)user_seed, strlen(user_seed), pass_hash_actual_raw);
  }
  else
  {
    return -1;
  }
  int i = 0;
  char temp[256] = {0};
  for (i = 0; i < 20; i++)
  {
    snprintf(temp, 256, "%02x", pass_hash_actual_raw[i]);
    strcat(pass_hash_actual, temp);
  }
  return (strcmp(pass_hash_valid, pass_hash_actual) == 0) ? 0 : 1;
}

/**
 * \brief check if the upload can be deleted, that is the user have
 * the permission to delete this upload
 *
 * \param long upload_id - upload id
 * \param char *user_name - user name
 *
 * \return 0: yes, you have the needed permissions;
 *         1: no;
 *        -1: failure;
 *        -2: does not exist
 */
int check_permission_upload(int wanted_permissions, long upload_id, int user_id, int user_perm)
{
  int perms = getEffectivePermissionOnUpload(db_conn, upload_id, user_id, user_perm);
  if (perms > 0)
  {
    if (perms < wanted_permissions)
    {
      return 1;
    }
    else
    {
      return 0;
    }
  }
  return perms;
}

int check_read_permission_upload(long upload_id, int user_id, int user_perm)
{
  return check_permission_upload(PERM_READ, upload_id, user_id, user_perm);
}

int check_write_permission_upload(long upload_id, int user_id, int user_perm)
{
  return check_permission_upload(PERM_WRITE, upload_id, user_id, user_perm);
}

/**
 * \brief check if the upload can be deleted, that is the user have
 * the permission to delete this upload
 *
 * \param long upload_id - upload id
 * \param char *user_name - user name
 *
 * \return 0: yes, can be deleted;
 *         1: can not be deleted;
 *        -1: failure;
 */
int check_write_permission_folder(long folder_id, int user_id, int user_perm)
{
  char SQL[MAXSQL];
  PGresult *result;
  int count = 0;

  if (user_perm < PERM_WRITE)
  {
    return 1; // can not be deleted
  }

  snprintf(SQL,MAXSQL,"SELECT count(*) FROM folder join users on (users.user_pk = folder.user_fk or users.user_perm = 10) where folder_pk = %ld and users.user_pk = %d;",folder_id,user_id);
  result = PQexec(db_conn, SQL);
  if (fo_checkPQresult(db_conn, result, SQL, __FILE__, __LINE__))
  {
    return -1;
  }
  count = atol(PQgetvalue(result,0,0));
  if(count == 0)
  {
    return 1; // can not be deleted
  }
  return 0; // can be deleted
}

/**
 * \brief check if the upload can be deleted, that is the user have
 * the permissoin to delete this upload
 *
 * \param long upload_id - upload id
 * \param char *user_name - user name
 *
 * \return 0: yes, can be deleted;
 *         1: can not be deleted;
 */
int check_write_permission_license(long license_id, int user_perm)
{
  if (user_perm != PERM_ADMIN)
  {
    printfInCaseOfVerbosity("only admin is allowed to delete licenses\n");
    return 0; // can not be deleted
  }
  return 1; // can be deleted
}


/**
 * \brief deleteLicense()
 *
 *   Given an upload ID, delete all licenses associated with it.
 *   The DoBegin flag determines whether BEGIN/COMMIT should be called.
 *   Do this if you want to reschedule license analysis.
 *
 * \param long UploadId the upload id
 *
 * \return 0: yes, success;
 *         1: can not be deleted;
 *        -1: failure;
 *        -2: does not exist
 */
int deleteLicense (long UploadId, int user_perm)
{
  char SQL[MAXSQL];
  PGresult *result;
  long items=0;

  int permission_license = check_write_permission_license(UploadId, user_perm);
  if (0 != permission_license)
  {
    return permission_license;
  }

  printfInCaseOfVerbosity("Deleting licenses for upload %ld\n",UploadId);
  PQexecCheckClear(NULL, "SET statement_timeout = 0;", __FILE__, __LINE__);
  PQexecCheckClear(NULL, "BEGIN;", __FILE__, __LINE__);

  /* Get the list of pfiles to process */
  snprintf(SQL,MAXSQL,"SELECT DISTINCT(pfile_fk) FROM uploadtree WHERE upload_fk = '%ld' ;",UploadId);
  result = PQexec(db_conn, SQL);
  if (fo_checkPQresult(db_conn, result, SQL, __FILE__, __LINE__))
  {
    return -1;
  }
  items = PQntuples(result);
  PQclear(result);
  /***********************************************/
  /* delete pfile licenses */
  printfInCaseOfVerbosity("# Deleting licenses\n");
  snprintf(SQL,MAXSQL,"DELETE FROM licterm_name WHERE pfile_fk IN (SELECT pfile_fk FROM uploadtree WHERE upload_fk = '%ld');",UploadId);
  PQexecCheckClear(NULL, SQL, __FILE__, __LINE__);

  snprintf(SQL,MAXSQL,"DELETE FROM agent_lic_status WHERE pfile_fk IN (SELECT pfile_fk FROM uploadtree WHERE upload_fk = '%ld');",UploadId);
  PQexecCheckClear(NULL, SQL, __FILE__, __LINE__);

  snprintf(SQL,MAXSQL,"DELETE FROM agent_lic_meta WHERE pfile_fk IN (SELECT pfile_fk FROM uploadtree WHERE upload_fk = '%ld');",UploadId);
  PQexecCheckClear(NULL, SQL, __FILE__, __LINE__);

  fo_scheduler_heart(items);

  /***********************************************/
  /* Commit the change! */
  printfInCaseOfVerbosity("# Delete completed\n");
  if (Test)
  {
    PQexecCheckClear(NULL, "ROLLBACK;", __FILE__, __LINE__);
  }
  else
  {
    PQexecCheckClear(NULL, "COMMIT;", __FILE__, __LINE__);
  }
  PQexecCheckClear(NULL, "SET statement_timeout = 120000;", __FILE__, __LINE__);

  printfInCaseOfVerbosity("Deleted licenses for upload %ld\n",UploadId);

  return 0; /* success */
} /* deleteLicense() */

/**
 * \brief deleteUpload()
 *
*  Given an upload ID, delete it.
 *
 *  param long UploadId the upload id
 *
 * \return 0: yes, can is deleted;
 *         1: can not be deleted;
 *        -1: failure;
 *        -2: does not exist
 */
int deleteUpload (long UploadId, int user_id, int user_perm)
{
  char *S;
  int Row,MaxRow;
  char TempTable[256];
  PGresult *result, *pfile_result;
  char SQL[MAXSQL], desc[myBUFSIZ];

  int permission_upload = check_write_permission_upload(UploadId, user_id, user_perm);
  if(0 != permission_upload)
  {
    return permission_upload;
  }

  snprintf(TempTable,sizeof(TempTable),"DelUp_%ld_pfile",UploadId);
  snprintf(SQL,MAXSQL,"DROP TABLE IF EXISTS %s;",TempTable);
  PQexecCheckClear(NULL, SQL, __FILE__, __LINE__);

  snprintf(desc, myBUFSIZ, "Deleting upload %ld",UploadId);
  PQexecCheckClear(desc, "SET statement_timeout = 0;", __FILE__, __LINE__);
  PQexecCheckClear(NULL, "BEGIN;", __FILE__, __LINE__);

  /***********************************************/
  /*** Delete everything that impacts the UI ***/
  /***********************************************/

  if (!Test)
  {
    /* The UI depends on uploadtree and folders for navigation.
     Delete them now to block timeouts from the UI. */
    PQexecCheckClear(NULL, "COMMIT;", __FILE__, __LINE__);
  }

  /***********************************************/
  /*** Begin complicated stuff ***/
  /***********************************************/

  /* Get the list of pfiles to delete */
  /** These are all pfiles in the upload_fk that only appear once. **/
  snprintf(SQL,MAXSQL,"SELECT DISTINCT pfile_pk,pfile_sha1 || '.' || pfile_md5 || '.' || pfile_size AS pfile INTO %s FROM uploadtree INNER JOIN pfile ON upload_fk = %ld AND pfile_fk = pfile_pk;",TempTable,UploadId);
  PQexecCheckClear("Getting list of pfiles to delete",
                 SQL, __FILE__, __LINE__);

  /* Remove pfiles with reuse */
  snprintf(SQL,MAXSQL,"DELETE FROM %s USING uploadtree WHERE pfile_pk = uploadtree.pfile_fk AND uploadtree.upload_fk != %ld;",TempTable,UploadId);
  PQexecCheckClear(NULL, SQL, __FILE__, __LINE__);

  if (Verbose)
  {
    snprintf(SQL,MAXSQL,"SELECT COUNT(*) FROM %s;",TempTable);
    result = PQexec(db_conn, SQL);
    if (fo_checkPQresult(db_conn, result, SQL, __FILE__, __LINE__))
    {
      return -1;
    }
    printf("# Created pfile table %s with %ld entries\n", TempTable, atol(PQgetvalue(result,0,0)));
    PQclear(result);
  }

  /***********************************************/
  /* Now to delete the actual pfiles from the repository before remove the DB. */

  /* Get the file listing -- needed for deleting pfiles from the repository. */
  snprintf(SQL,MAXSQL,"SELECT * FROM %s ORDER BY pfile_pk;",TempTable);
  pfile_result = PQexec(db_conn, SQL);
  if (fo_checkPQresult(db_conn, pfile_result, SQL, __FILE__, __LINE__))
  {
    return -1;
  }

  if (Test <= 1)
  {
    MaxRow = PQntuples(pfile_result);
    for(Row=0; Row<MaxRow; Row++)
    {
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

  /***********************************************
   This begins the slow part that locks the DB.
   The problem is, we don't want to lock a critical row,
   otherwise the scheduler will lock and/or fail.
   ***********************************************/
  if (!Test)
  {
    PQexecCheckClear(NULL, "BEGIN;", __FILE__, __LINE__);
  }
  /* Delete the upload from the folder-contents table */
  snprintf(SQL,MAXSQL,"DELETE FROM foldercontents WHERE (foldercontents_mode & 2) != 0 AND child_id = %ld;",UploadId);
  PQexecCheckClear("Deleting foldercontents", SQL, __FILE__, __LINE__);

  /***********************************************/
  /* Delete the actual upload */

  /* Delete the bucket_container record as it can't be cascade delete with upload table */
  snprintf(SQL,MAXSQL,"DELETE FROM bucket_container USING uploadtree WHERE uploadtree_fk = uploadtree_pk AND upload_fk = %ld;",UploadId);
  PQexecCheckClear("Deleting bucket_container", SQL, __FILE__, __LINE__);

  /* Delete the tag_uploadtree record as it can't be cascade delete with upload table */
  snprintf(SQL,MAXSQL,"DELETE FROM tag_uploadtree USING uploadtree WHERE uploadtree_fk = uploadtree_pk AND upload_fk = %ld;",UploadId);
  PQexecCheckClear("Deleting tag_uploadtree", SQL, __FILE__, __LINE__);

  /* Delete uploadtree_nnn table */
  char uploadtree_tablename[1024];
  snprintf(SQL,MAXSQL,"SELECT uploadtree_tablename FROM upload WHERE upload_pk = %ld;",UploadId);
  result = PQexec(db_conn, SQL);
  if (fo_checkPQresult(db_conn, result, SQL, __FILE__, __LINE__))
  {
    return -1;
  }
  if (PQntuples(result))
  {
    strcpy(uploadtree_tablename, PQgetvalue(result, 0, 0));
    PQclear(result);
    if (strcasecmp(uploadtree_tablename,"uploadtree_a"))
    {
      snprintf(SQL,MAXSQL,"DROP TABLE %s;", uploadtree_tablename);
      PQexecCheckClear(NULL, SQL, __FILE__, __LINE__);
    }
  }

  snprintf(SQL,MAXSQL,"DELETE FROM upload WHERE upload_pk = %ld;",UploadId);
  PQexecCheckClear("Deleting upload", SQL, __FILE__, __LINE__);

  /***********************************************/
  /* delete from pfile is SLOW due to constraint checking.
     Do it separately. */
  snprintf(SQL,MAXSQL,"DELETE FROM pfile USING %s WHERE pfile.pfile_pk = %s.pfile_pk;",TempTable,TempTable);
  PQexecCheckClear("Deleting from pfile", SQL, __FILE__, __LINE__);

  snprintf(SQL,MAXSQL,"DROP TABLE %s;",TempTable);
  PQexecCheckClear(NULL, SQL, __FILE__, __LINE__);

  PQexecCheckClear(NULL, "SET statement_timeout = 120000;", __FILE__, __LINE__);

  printfInCaseOfVerbosity("Deleted upload %ld from DB, now doing repository.\n",UploadId);

  if (Test)
  {
    PQexecCheckClear(NULL, "ROLLBACK", __FILE__, __LINE__);

    snprintf(SQL,MAXSQL,"DROP TABLE %s;",TempTable);
    PQexecCheckClear(NULL, SQL, __FILE__, __LINE__);
  }
  else
  {
    PQexecCheckClear(NULL, "COMMIT", __FILE__, __LINE__);
  }

  printfInCaseOfVerbosity("Deleted upload %ld\n",UploadId);

  return 0; /* success */
} /* deleteUpload() */

/**
 * \brief remove link between parent and (child,mode) if there are other parents
 *
 * \return 0: successfully deleted link (other link existed);
 *         1: was not able to delete the link (no other link to this upload existed);
 *        -1: failure
 *
 */
int unlinkContent (long child, long parent, int mode, int user_id, int user_perm)
{
  PGresult *result;
  char SQL[MAXSQL];
  int cnt;

  // TODO: add permission checks

  snprintf(SQL,MAXSQL,"SELECT COUNT(DISTINCT parent_fk) FROM foldercontents WHERE foldercontents_mode=%d AND child_id=%ld",mode,child);
  result = PQexec(db_conn, SQL);
  if (fo_checkPQresult(db_conn, result, SQL, __FILE__, __LINE__))
  {
    return -1;
  }
  cnt = atol(PQgetvalue(result,0,0));
  PQclear(result);
  if(cnt>1 && !Test)
  {
    snprintf(SQL,MAXSQL,"DELETE FROM foldercontents WHERE foldercontents_mode=%d AND child_id =%ld AND parent_fk=%ld",mode,child,parent);
    PQexecCheckClear(NULL, SQL, __FILE__, __LINE__);
    return 0;
  }
  return 1;
}

/**
 * \brief listFoldersRecurse(): Draw folder tree.
 *
 *   if DelFlag is set, then all child uploads are
 *   deleted and the folders are deleted.
 *
 * \param long Parent the parent folder id
 * \param int Depth
 * \param long Row grandparent (used to unlink if multiple grandparents)
 * \param int DelFlag 0=no del, 1=del if unique parent, 2=del unconditional
 *
 * \return 0: success;
 *         1: fail;
 *        -1: failure
 *
 */
int listFoldersRecurse (long Parent, int Depth, long Row, int DelFlag, int user_id, int user_perm)
{
  int r,MaxRow;
  long Fid;
  int i;
  char *Desc;
  PGresult *result;
  char SQL[MAXSQL];
  int rc;

  rc = check_write_permission_folder(Parent, user_id, user_perm);
  if(rc < 0)
  {
    return rc;
  }
  if(DelFlag && rc > 0){
    return 1;
  }

  /* Find all folders with this parent and recurse */
  snprintf(SQL,MAXSQL,"SELECT folder_pk,foldercontents_mode,name,description,upload_pk FROM folderlist "
          "WHERE parent=%ld "
          "ORDER BY name,parent,folder_pk",Parent);
  result = PQexec(db_conn, SQL);
  if (fo_checkPQresult(db_conn, result, SQL, __FILE__, __LINE__))
  {
    return -1;
  }
  MaxRow = PQntuples(result);
  for(r=0; r < MaxRow; r++)
  {
    if (atol(PQgetvalue(result,r,0)) == Parent)
    {
      continue;
    }

    Fid = atol(PQgetvalue(result,r,0));
    if (Fid != 0)
    {
      if (!DelFlag)
      {
        for(i=0; i<Depth; i++)
        {
          fputs("   ",stdout);
        }
        printf("%4ld :: %s",Fid,PQgetvalue(result,r,2));
        Desc = PQgetvalue(result,r,3);
        if (Desc && Desc[0])
        {
          printf(" (%s)",Desc);
        }
        printf("\n");
      }
      rc = listFoldersRecurse(Fid,Depth+1,Parent,DelFlag,user_id,user_perm);
      if (rc < 1)
      {
        if (DelFlag)
        {
          printf("Deleting the folder failed.");
        }
        return 1;
      }
    }
    else
    {
      if (DelFlag==1 && unlinkContent(Parent,Row,1,user_id,user_perm)==0)
      {
        continue;
      }
      if (rc < 0)
      {
        return rc;
      }
      if (DelFlag)
      {
        rc = deleteUpload(atol(PQgetvalue(result,r,4)),user_id, user_perm);
        if (rc < 0)
        {
          return rc;
        }
        if (rc != 0)
        {
          printf("Deleting the folder failed since it contains uploads you can't delete.");
          return rc;
        }
      }
      else
      {
        rc = check_read_permission_upload(atol(PQgetvalue(result,r,4)),user_id,user_perm);
        if (rc < 0)
        {
          return rc;
        }
        if (rc == 0)
        {
          for(i=0; i<Depth; i++)
          {
            fputs("   ",stdout);
          }
          printf("%4s :: Contains: %s\n","--",PQgetvalue(result,r,2));
        }
      }
    }
  }
  PQclear(result);

  switch(Parent)
  {
    case 1: /* skip default parent */
      if (DelFlag != 0)
      {
        printf("INFO: Default folder not deleted.\n");
      }
      break;
    case 0: /* it's an upload */
      break;
    default:  /* it's a folder */
      if (DelFlag == 0)
      {
        break;
      }
      printf("INFO: folder id=%ld will be deleted with flag %d\n",Parent,DelFlag);
      if (DelFlag==1)
      {
        rc = unlinkContent(Parent,Row,1,user_id,user_perm);
        if (rc == 0)
        {
          break;
        }
        if (rc < 0)
        {
          return rc;
        }
      }
      snprintf(SQL,MAXSQL,"DELETE FROM foldercontents WHERE foldercontents_mode=1 AND child_id=%ld",Parent);
      if (Test)
      {
        printf("TEST: %s\n",SQL);
      }
      else
      {
        PQexecCheckClear(NULL, SQL, __FILE__, __LINE__);
      }

      snprintf(SQL,MAXSQL,"DELETE FROM folder WHERE folder_pk = '%ld';",Parent);
      if (Test)
      {
        printf("TEST: %s\n",SQL);
      }
      else
      {
        PQexecCheckClear(NULL, SQL, __FILE__, __LINE__);
      }
  } /* switch() */

  return 0; /* success */
} /* listFoldersRecurse() */

int listFoldersFindDetatchedFolders(PGresult *result, int user_id, int user_perm)
{
  int DetachFlag=0;
  int i,j;
  int MaxRow = PQntuples(result);
  long Fid; /* folder ids */
  int Match;
  char *Desc;
  int rc;

  /* Find detached folders */
  for(i=0; i < MaxRow; i++)
  {
    Fid = atol(PQgetvalue(result,i,1));
    if (Fid == 1)
    {
      continue; /* skip default parent */
    }
    Match=0;
    for(j=0; (j<MaxRow) && !Match; j++)
    {
      if ((i!=j) && (atol(PQgetvalue(result,j,0)) == Fid)) Match=1;
    }
    if (!Match && !atol(PQgetvalue(result,i,4)))
    {
      if (!DetachFlag)
      {
        printf("# Unlinked folders\n");
        DetachFlag=1;
      }
      printf("%4ld :: %s",Fid,PQgetvalue(result,i,2));
      Desc = PQgetvalue(result,i,3);
      if (Desc && Desc[0])
      {
        printf(" (%s)",Desc);
      }
      printf("\n");
      rc = listFoldersRecurse(Fid,1,i,0,user_id,user_perm);
      if (rc < 0)
      {
        return rc;
      }
    }
  }
  return 0;
}

int listFoldersFindDetatchedUploads(PGresult *result, int user_id, int user_perm)
{
  int DetachFlag=0;
  int i,j;
  int MaxRow = PQntuples(result);
  long Fid; /* folder ids */
  int Match;
  char *Desc;
  /* Find detached uploads */
  for(i=0; i < MaxRow; i++)
  {
    Fid = atol(PQgetvalue(result,i,1));
    if (Fid == 1)
    {
      continue; /* skip default parent */
    }
    Match=0;
    for(j=0; (j<MaxRow) && !Match; j++)
    {
      if ((i!=j) && (atol(PQgetvalue(result,j,0)) == Fid)) Match=1;
    }
    if (!Match && atol(PQgetvalue(result,i,4)))
    {
      if (!DetachFlag)
      {
        printf("# Unlinked uploads (uploads without folders)\n");
        DetachFlag=1;
      }
      printf("%4s",PQgetvalue(result,i,4));
      printf(" :: %s",PQgetvalue(result,i,2));
      Desc = PQgetvalue(result,i,3);
      if (Desc && Desc[0])
      {
        printf(" (%s)",Desc);
      }
      printf("\n");
    }
  }
  return 0;
}

int listFoldersFindDetatched(int user_id, int user_perm)
{
  char SQL[MAXSQL];
  PGresult *result;
  int rc;

  snprintf(SQL,MAXSQL,"SELECT folder_pk,parent,name,description,upload_pk FROM folderlist ORDER BY name,parent,folder_pk;");
  result = PQexec(db_conn, SQL);
  if (fo_checkPQresult(db_conn, result, SQL, __FILE__, __LINE__))
  {
    return -1;
  }
  rc = listFoldersFindDetatchedFolders(result, user_id, user_perm);
  if (rc < 0 )
  {
    PQclear(result);
    return rc;
  }
  rc = listFoldersFindDetatchedUploads(result, user_id, user_perm);
  PQclear(result);
  if (rc < 0 )
  {
    return rc;
  }
  return 0;
}

/**
 * \brief listFolders(): List every folder.
 */
int listFolders (int user_id, int user_perm)
{
  char SQL[MAXSQL];
  PGresult *result;
  int rc;

  if(user_perm == 0){
    printf("you do not have the permsssion to view the folder list.\n");
    return 1;
  }

  printf("# Folders\n");
  snprintf(SQL,MAXSQL,"SELECT folder_name from folder where folder_pk =1;");
  result = PQexec(db_conn, SQL);
  if (fo_checkPQresult(db_conn, result, SQL, __FILE__, __LINE__))
  {
    return -1;
  }

  printf("%4d :: %s\n", 1, PQgetvalue(result,0,0));
  PQclear(result);

  rc = listFoldersRecurse(1,1,-1,0,user_id,user_perm);
  if (rc < 0)
  {
    return rc;
  }

  rc = listFoldersFindDetatched(user_id, user_perm);
  if (rc < 0)
  {
    return rc;
  }
  return 0;
} /* listFolders() */

/**
 * \brief listUploads(): List every upload ID.
 *
 * \char *user_name - user name
 */
int listUploads (int user_id, int user_perm)
{
  int Row,MaxRow;
  long NewPid;
  PGresult *result;
  int rc;
  char *SQL = "SELECT upload_pk,upload_desc,upload_filename FROM upload ORDER BY upload_pk;";
  printf("# Uploads\n");
  result = PQexec(db_conn, SQL);
  if (fo_checkPQresult(db_conn, result, SQL, __FILE__, __LINE__))
  {
    exit(-1);
  }

  /* list each value */
  MaxRow = PQntuples(result);
  for(Row=0; Row < MaxRow; Row++)
  {
    NewPid = atol(PQgetvalue(result,Row,0));
    rc = check_read_permission_upload(NewPid, user_id, user_perm);
    if (rc < 0)
    {
      PQclear(result);
      return rc;
    }
    if (NewPid >= 0 && (user_perm == PERM_ADMIN || rc  == 0))
    {
      char *S;
      printf("%ld :: %s",NewPid,PQgetvalue(result,Row,2));
      S = PQgetvalue(result,Row,1);
      if (S && S[0]) printf(" (%s)",S);
      printf("\n");
    }
  }
  PQclear(result);
  return 0;
} /* listUploads() */

/**
 * \brief deleteFolder()
 *
 *  Given a folder ID, delete it AND recursively delete everything below it!
 *  This includes upload deletion!
 *
 * \param long FolderId the fold id to delete
 *
 * \return 0: success;
 *         1: fail
 *        -1: failure
 *
 **/
int deleteFolder(long FolderId, int user_id, int user_perm)
{
  return listFoldersRecurse(FolderId,0,-1,2,user_id,user_perm);
} /* deleteFolder() */

/**********************************************************************/

/**
 * \brief readAndProcessParameter()
 *
 *  Read Parameter from scheduler.
 *  Process line elements.
 *
 * \param char *Parm the parameter string
 *
 * \return 0: yes, can is deleted;
 *         1: can not be deleted;
 *        -1: failure;
 *        -2: does not exist
 *
 **/
int readAndProcessParameter (char *Parm, int user_id, int user_perm)
{
  char *L;
  int rc=0;     /* assume no data */
  int Type=0; /* 0=undefined; 1=delete; 2=list */
  int Target=0; /* 0=undefined; 1=upload; 2=license; 3=folder */
  long Id;

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
  if ((Type==1) && (Target==1))
  {
    rc = deleteUpload(Id, user_id, user_perm);
  }
  else if ((Type==1) && (Target==2))
  {
    rc = deleteLicense(Id, user_perm);
  }
  else if ((Type==1) && (Target==3))
  {
    rc = deleteFolder(Id, user_id, user_perm);
  }
  else if (((Type==2) && (Target==1)) || ((Type==2) && (Target==2)))
  {
    rc = listUploads(0, PERM_ADMIN);
  }
  else if ((Type==2) && (Target==3))
  {
    rc = listFolders(user_id, user_perm);
  }
  else
  {
    LOG_ERROR("Unknown command: '%s'\n",Parm);
  }

  return rc;
} /* readAndProcessParameter() */

void doSchedulerTasks()
{
  char *Parm = NULL;
  char SQL[MAXSQL];
  PGresult *result;
  int user_id = -1;
  int user_perm = -1;

  while(fo_scheduler_next())
  {
    Parm = fo_scheduler_current();
    user_id = fo_scheduler_userID();

    /* get perm level of user */
    snprintf(SQL,MAXSQL,"SELECT user_perm from users where user_pk='%d';", user_id);
    result = PQexec(db_conn, SQL);
    if (fo_checkPQresult(db_conn, result, SQL, __FILE__, __LINE__) || !PQntuples(result))
    {
      exit(0);
    }
    user_perm = atoi(PQgetvalue(result, 0, 0));
    PQclear(result);

    int returnCode = readAndProcessParameter(Parm, user_id, user_perm);
    if (returnCode != 0)
    {
      /* Loglevel is to high, but scheduler expects FATAL log message before exit */
      LOG_FATAL("Due to permission problems, the delagent was not able to list or delete the requested objects or they did not exist.");
      exit(returnCode);
    }
  }
}
