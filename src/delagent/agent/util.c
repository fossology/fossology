/*
 SPDX-FileCopyrightText: © 2007-2013 Hewlett-Packard Development Company, L.P.
 SPDX-FileCopyrightText: © 2015-2019 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/
/**
 * \file
 * \brief local function of delagent
 *
 * delagent: Remove an upload from the DB and repository
 *
 */

#include <crypt.h>

#include "delagent.h"

int Verbose = 0;
int Test = 0;
PGconn* pgConn = NULL;        // the connection to Database

/**
 * \brief If verbose is on, print to stdout
 * \param format printf format to use for printing
 * \param ... Data to be printed
 * \return Number of characters printed
 */
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
 * \brief simple wrapper which includes PQexec and fo_checkPQcommand
 * \param desc description for the SQL command, else NULL
 * \param SQL  SQL command executed
 * \param file source file name
 * \param line source line number
 * \return PQexec query result
 * \see PQexec()
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

  result = PQexec(pgConn, SQL);
  if (fo_checkPQcommand(pgConn, result, SQL, file, line))
  {
    exitNow(-1);
  }
  return result;
}

/**
 * \brief Execute SQL query and create the result
 * \see PQexecCheck()
 */
void PQexecCheckClear(const char *desc, char *SQL, char *file, const int line)
{
  PGresult *result;
  result = PQexecCheck(desc, SQL, file, line);
  PQclear(result);
}

/**
 * \brief if this account is valid
 *
 * \param[in]  user user name
 * \param[in]  password password
 * \param[out] userId will be set to the id of the user
 * \param[out] userPerm will be set to the permission level of the user
 *
 * \return 1: invalid;
 *         0: yes, valid;
 *        -1: failure
 */
int authentication(char *user, char *password, int *userId, int *userPerm)
{
  if (NULL == user || NULL == password)
  {
    return 1;
  }
  char SQL[MAXSQL] = {0};
  PGresult *result;
  char user_seed[myBUFSIZ] = {0};
  char pass_hash_valid[myBUFSIZ] = {0};
  unsigned char pass_hash_actual_raw[21] = {0};
  char pass_hash_actual[41] = {0};

  /** get user_seed, user_pass on one specified user */
  snprintf(SQL,MAXSQL,"SELECT user_seed, user_pass, user_perm, user_pk from users where user_name=$1;");
  const char *values[1] = {user};
  int lengths[1] = {strlen(user)};
  int binary[1] = {0};
  result = PQexecParams(pgConn, SQL, 1, NULL, values, lengths, binary, 0);
  if (fo_checkPQresult(pgConn, result, SQL, __FILE__, __LINE__))
  {
    return -1;
  }
  if (!PQntuples(result))
  {
    return 1;
  }
  strcpy(user_seed, PQgetvalue(result, 0, 0));
  strcpy(pass_hash_valid, PQgetvalue(result, 0, 1));
  *userPerm = atoi(PQgetvalue(result, 0, 2));
  *userId = atoi(PQgetvalue(result, 0, 3));
  PQclear(result);
  if (pass_hash_valid[0] &&
    strcmp(crypt(password, pass_hash_valid), pass_hash_valid) == 0)
  {
    return 0;
  }
  if (user_seed[0] && pass_hash_valid[0])
  {
    strcat(user_seed, password);  // get the hash code on seed+pass
    gcry_md_hash_buffer(GCRY_MD_SHA1, pass_hash_actual_raw, user_seed,
      strlen(user_seed));
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
 * \param uploadId upload id
 * \param user_name user name
 *
 * \return 0: yes, you have the needed permissions;
 *         1: no;
 *        -1: failure;
 *        -2: does not exist
 */
int check_permission_upload(int wanted_permissions, long uploadId, int userId, int userPerm)
{
  int perms = getEffectivePermissionOnUpload(pgConn, uploadId, userId, userPerm);
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
  else if (perms == 0)
  {
    return 1;
  }
  return perms;
}

/**
 * \brief check if the user has read permission on the given upload
 * \param uploadId
 * \param userId
 * \param userPerm Permission requested by user
 * \return 0: yes, you have the needed permissions;
 *         1: no;
 *        -1: failure;
 *        -2: does not exist
 */
int check_read_permission_upload(long uploadId, int userId, int userPerm)
{
  return check_permission_upload(PERM_READ, uploadId, userId, userPerm);
}

/**
 * \brief check if the user has read permission on the given upload
 * \param uploadId
 * \param userId
 * \param userPerm Permission requested by user
 * \return 0: yes, you have the needed permissions;
 *         1: no;
 *        -1: failure;
 *        -2: does not exist
 */
int check_write_permission_upload(long uploadId, int userId, int userPerm)
{
  return check_permission_upload(PERM_WRITE, uploadId, userId, userPerm);
}

/**
 * \brief check if the upload can be deleted, that is the user have
 * the permission to delete this upload
 *
 * \param uploadId upload id
 * \param user_name user name
 * \param userPerm Permission requested by user
 *
 * \return 0: yes, can be deleted;
 *         1: can not be deleted;
 *        -1: failure;
 */
int check_write_permission_folder(long folder_id, int userId, int userPerm)
{
  char SQL[MAXSQL];
  PGresult *result;
  int count = 0;

  if (userPerm < PERM_WRITE)
  {
    return 1; // can not be deleted
  }

  snprintf(SQL,MAXSQL,"SELECT count(*) FROM folder JOIN users ON (users.user_pk = folder.user_fk OR users.user_perm = 10) WHERE folder_pk = %ld AND users.user_pk = %d;",folder_id,userId);
  result = PQexec(pgConn, SQL);
  if (fo_checkPQresult(pgConn, result, SQL, __FILE__, __LINE__))
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
 * \brief check if the license can be deleted, that is the user have
 * the permission to delete this license
 *
 * \param license_id license id
 * \param userPerm Permission requested by user
 *
 * \return 0: yes, can be deleted;
 *         1: can not be deleted;
 */
int check_write_permission_license(long license_id, int userPerm)
{
  if (userPerm != PERM_ADMIN)
  {
    printfInCaseOfVerbosity("only admin is allowed to delete licenses\n");
    return 0; // can not be deleted
  }
  return 1; // can be deleted
}

/**
 * \brief Given an upload ID, delete it.
 *
 * \param uploadId the upload id
 * \param userId
 * \param userPerm permission level the user has
 *
 * \return 0: yes, can is deleted;
 *         1: can not be deleted;
 *        -1: failure;
 *        -2: does not exist
 */
int deleteUpload (long uploadId, int userId, int userPerm)
{
  char *S;
  int Row,maxRow;
  char tempTable[256];
  PGresult *result, *pfileResult;
  char SQL[MAXSQL], desc[myBUFSIZ];

  int permission_upload = check_write_permission_upload(uploadId, userId, userPerm);
  if(0 != permission_upload) {
    return permission_upload;
  }

  snprintf(tempTable,sizeof(tempTable),"delup_%ld_pfile",uploadId);
  snprintf(SQL,MAXSQL,"DROP TABLE IF EXISTS %s;",tempTable);
  PQexecCheckClear(NULL, SQL, __FILE__, __LINE__);

  snprintf(desc, myBUFSIZ, "Deleting upload %ld",uploadId);
  PQexecCheckClear(desc, "SET statement_timeout = 0;", __FILE__, __LINE__);
  PQexecCheckClear(NULL, "BEGIN;", __FILE__, __LINE__);

  /* Delete everything that impacts the UI */
  if (!Test) {
    /* The UI depends on uploadtree and folders for navigation.
     Delete them now to block timeouts from the UI. */
    PQexecCheckClear(NULL, "COMMIT;", __FILE__, __LINE__);
  }

  /* Begin complicated stuff */
  /* Get the list of pfiles to delete */
  /* These are all pfiles in the upload_fk that only appear once. */
  snprintf(SQL,MAXSQL,"SELECT DISTINCT pfile_pk,pfile_sha1 || '.' || pfile_md5 || '.' || pfile_size AS pfile INTO %s FROM uploadtree INNER JOIN pfile ON upload_fk = %ld AND pfile_fk = pfile_pk;",tempTable,uploadId);
  PQexecCheckClear("Getting list of pfiles to delete", SQL, __FILE__, __LINE__);

  /* Remove pfiles which are reused by other uploads */
  snprintf(SQL, MAXSQL, "DELETE FROM %s WHERE pfile_pk IN (SELECT pfile_pk FROM %s INNER JOIN uploadtree ON pfile_pk = pfile_fk WHERE upload_fk != %ld)", tempTable, tempTable, uploadId);
  PQexecCheckClear(NULL, SQL, __FILE__, __LINE__);

  if (Verbose) {
    snprintf(SQL,MAXSQL,"SELECT COUNT(*) FROM %s;",tempTable);
    result = PQexec(pgConn, SQL);
    if (fo_checkPQresult(pgConn, result, SQL, __FILE__, __LINE__)) {
      return -1;
    }
    printf("# Created pfile table %s with %ld entries\n", tempTable, atol(PQgetvalue(result,0,0)));
    PQclear(result);
  }

  /* Now to delete the actual pfiles from the repository before remove the DB. */
  /* Get the file listing -- needed for deleting pfiles from the repository. */
  snprintf(SQL,MAXSQL,"SELECT pfile FROM %s ORDER BY pfile_pk;",tempTable);
  pfileResult = PQexec(pgConn, SQL);
  if (fo_checkPQresult(pgConn, pfileResult, SQL, __FILE__, __LINE__)) {
    return -1;
  }

  if (Test <= 1) {
    maxRow = PQntuples(pfileResult);
    for(Row=0; Row<maxRow; Row++) {
      S = PQgetvalue(pfileResult,Row,0); /* sha1.md5.len */
      if (fo_RepExist("files",S)) {
        if (Test) {
          printf("TEST: Delete %s %s\n","files",S);
        } else {
          fo_RepRemove("files",S);
        }
      }
      if (fo_RepExist("gold",S)) {
        if (Test) {
          printf("TEST: Delete %s %s\n","gold",S);
        } else {
          fo_RepRemove("gold",S);
        }
      }
      fo_scheduler_heart(1);
    }
  }
  PQclear(pfileResult);

  /*
   This begins the slow part that locks the DB.
   The problem is, we don't want to lock a critical row,
   otherwise the scheduler will lock and/or fail.
  */
  if (!Test) {
    PQexecCheckClear(NULL, "BEGIN;", __FILE__, __LINE__);
  }
  /* Delete the upload from the folder-contents table */
  snprintf(SQL,MAXSQL,"DELETE FROM foldercontents WHERE (foldercontents_mode & 2) != 0 AND child_id = %ld;",uploadId);
  PQexecCheckClear("Deleting foldercontents", SQL, __FILE__, __LINE__);

  /* Deleting the actual upload contents*/
  /* Delete the bucket_container record as it can't be cascade delete with upload table */
  snprintf(SQL,MAXSQL,"DELETE FROM bucket_container USING uploadtree WHERE uploadtree_fk = uploadtree_pk AND upload_fk = %ld;",uploadId);
  PQexecCheckClear("Deleting bucket_container", SQL, __FILE__, __LINE__);

  /* Delete the tag_uploadtree record as it can't be cascade delete with upload table */
  snprintf(SQL,MAXSQL,"DELETE FROM tag_uploadtree USING uploadtree WHERE uploadtree_fk = uploadtree_pk AND upload_fk = %ld;",uploadId);
  PQexecCheckClear("Deleting tag_uploadtree", SQL, __FILE__, __LINE__);

  char uploadtree_tablename[1000];
  snprintf(SQL,MAXSQL,"SELECT uploadtree_tablename FROM upload WHERE upload_pk = %ld;",uploadId);
  result = PQexec(pgConn, SQL);
  if (fo_checkPQresult(pgConn, result, SQL, __FILE__, __LINE__)) {
    return -1;
  }
  if (PQntuples(result)) {
    strcpy(uploadtree_tablename, PQgetvalue(result, 0, 0));
    PQclear(result);
  }

  printfInCaseOfVerbosity("Deleting local license decisions for upload %ld\n",
    uploadId);
  /* delete from clearing_event table. */
  snprintf(SQL, MAXSQL, "WITH alld AS ("
      "SELECT *, ROW_NUMBER() OVER "
        "(PARTITION BY clearing_event_pk ORDER BY scope DESC) rnum "
      "FROM clearing_event ce "
      "INNER JOIN clearing_decision_event cde "
        "ON cde.clearing_event_fk = ce.clearing_event_pk "
      "INNER JOIN clearing_decision cd "
        "ON cd.clearing_decision_pk = cde.clearing_decision_fk "
        "AND cd.uploadtree_fk IN "
        "(SELECT uploadtree_pk FROM %s WHERE upload_fk = %ld)) "
    "DELETE FROM clearing_event ce USING alld AS ad "
    "WHERE ad.rnum = 1 AND ad.scope = 0 " // Make sure not to delete global decisions
    "AND ce.clearing_event_pk = ad.clearing_event_pk;",
    uploadtree_tablename, uploadId);
  PQexecCheckClear("Deleting from clearing_event", SQL, __FILE__, __LINE__);

  /* delete from clearing_decision_event table. */
  snprintf(SQL, MAXSQL, "DELETE FROM clearing_decision_event AS cde "
    "USING clearing_decision AS cd "
      "WHERE cd.scope = 0 " // Make sure not to delete global decisions
      "AND cd.uploadtree_fk IN "
      "(SELECT uploadtree_pk FROM %s WHERE upload_fk = %ld) "
    "AND cd.clearing_decision_pk = cde.clearing_decision_fk;",
    uploadtree_tablename, uploadId);
  PQexecCheckClear("Deleting from clearing_decision_event", SQL, __FILE__, __LINE__);

  /* delete from clearing_decision table. */
  snprintf(SQL, MAXSQL, "DELETE FROM clearing_decision "
    "WHERE scope = 0 AND uploadtree_fk IN "
      "(SELECT uploadtree_pk FROM %s WHERE upload_fk = %ld);",
    uploadtree_tablename, uploadId);
  PQexecCheckClear("Deleting from clearing_decision", SQL, __FILE__, __LINE__);

  /* delete from license_ref_bulk table. */
  snprintf(SQL, MAXSQL, "DELETE FROM license_ref_bulk "
    "WHERE uploadtree_fk IN "
      "(SELECT uploadtree_pk FROM %s WHERE upload_fk = %ld);",
    uploadtree_tablename, uploadId);
  PQexecCheckClear("Deleting from license_ref_bulk", SQL, __FILE__, __LINE__);

  /* delete from uploadtree table. */
  snprintf(SQL, MAXSQL, "DELETE FROM %s WHERE upload_fk = %ld;",
      uploadtree_tablename, uploadId);
  PQexecCheckClear("Deleting from uploadtree", SQL, __FILE__, __LINE__);

  /* Delete uploadtree_nnn table */
  if (strcasecmp(uploadtree_tablename,"uploadtree_a")) {
    snprintf(SQL,MAXSQL,"DROP TABLE %s;", uploadtree_tablename);
    PQexecCheckClear(NULL, SQL, __FILE__, __LINE__);
  }

  /* delete from pfile is SLOW due to constraint checking. Do it separately. */
  snprintf(SQL,MAXSQL,"DELETE FROM pfile USING %s WHERE pfile.pfile_pk = %s.pfile_pk;",tempTable,tempTable);
  PQexecCheckClear("Deleting from pfile", SQL, __FILE__, __LINE__);

  snprintf(SQL,MAXSQL,"DROP TABLE %s;",tempTable);
  PQexecCheckClear(NULL, SQL, __FILE__, __LINE__);

  /* Mark upload deleted in upload table */
  snprintf(SQL,MAXSQL,"UPDATE upload SET expire_action = 'd', "
      "expire_date = now(), pfile_fk = NULL WHERE upload_pk = %ld;", uploadId);
  PQexecCheckClear("Marking upload as deleted", SQL, __FILE__, __LINE__);

  PQexecCheckClear(NULL, "SET statement_timeout = 120000;", __FILE__, __LINE__);

  printfInCaseOfVerbosity("Deleted upload %ld from DB, now doing repository.\n",uploadId);

  if (Test) {
    PQexecCheckClear(NULL, "ROLLBACK;", __FILE__, __LINE__);
  } else {
    PQexecCheckClear(NULL, "COMMIT;", __FILE__, __LINE__);
  }

  printfInCaseOfVerbosity("Deleted upload %ld\n",uploadId);

  return 0; /* success */
} /* deleteUpload() */

/**
 * \brief remove link between parent and (child,mode) if there are other parents
 *
 * \param child  id of the child to be unlinked
 * \param parent id of the parent to unlink from
 * \param mode   1<<0 child is folder_fk, 1<<1 child is upload_fk, 1<<2 child is an uploadtree_fk
 * \param userPerm permission level the user has
 *
 * \return 0: successfully deleted link (other link existed);
 *         1: was not able to delete the link (no other link to this upload existed);
 *        -1: failure
 * \todo add permission checks
 */
int unlinkContent (long child, long parent, int mode, int userId, int userPerm)
{
  int cnt, cntUpload;
  char SQL[MAXSQL];
  PGresult *result;

  if(mode == 1){
    snprintf(SQL,MAXSQL,"SELECT COUNT(DISTINCT parent_fk) FROM foldercontents WHERE foldercontents_mode=%d AND child_id=%ld",mode,child);
  }
  else{
    snprintf(SQL,MAXSQL,"SELECT COUNT(parent_fk) FROM foldercontents WHERE foldercontents_mode=%d AND"
                        " child_id in (SELECT upload_pk FROM folderlist WHERE pfile_fk="
                        "(SELECT pfile_fk FROM folderlist WHERE upload_pk=%ld limit 1))",
                        mode,child);
  }
  result = PQexec(pgConn, SQL);
  if (fo_checkPQresult(pgConn, result, SQL, __FILE__, __LINE__))
  {
    return -1;
  }
  cnt = atoi(PQgetvalue(result,0,0));
  PQclear(result);
  if(cnt>1 && !Test)
  {
    if(mode == 2){
      snprintf(SQL,MAXSQL,"SELECT COUNT(DISTINCT parent_fk) FROM foldercontents WHERE foldercontents_mode=1 AND child_id=%ld",parent);
      result = PQexec(pgConn, SQL);
      if (fo_checkPQresult(pgConn, result, SQL, __FILE__, __LINE__))
      {
        return -1;
      }
      cntUpload = atoi(PQgetvalue(result,0,0));
      PQclear(result);
      if(cntUpload > 1){     // check for copied/duplicate folder
        return 0;
      }
    }
    snprintf(SQL,MAXSQL,"DELETE FROM foldercontents WHERE foldercontents_mode=%d AND child_id =%ld AND parent_fk=%ld",mode,child,parent);
    PQexecCheckClear(NULL, SQL, __FILE__, __LINE__);
    return 0;
  }
  return 1;
}

/**
 * \brief Draw folder tree.
 *
 *   if DelFlag is set, then all child uploads are
 *   deleted and the folders are deleted.
 *
 * \param Parent the parent folder id
 * \param Depth
 * \param row grandparent (used to unlink if multiple grandparents)
 * \param DelFlag 0=no del, 1=del if unique parent, 2=del unconditional
 * \param userId
 * \param userPerm permission level the user has
 *
 * \return 0: success;
 *         1: fail;
 *        -1: failure
 *
 */
int listFoldersRecurse (long Parent, int Depth, long Row, int DelFlag, int userId, int userPerm)
{
  int r, i, rc, maxRow;
  int count, resultUploadCount;
  long Fid;
  char *Desc;
  char SQL[MAXSQL], SQLUpload[MAXSQL];
  char SQLFolder[MAXSQLFolder];
  PGresult *result, *resultUpload, *resultFolder;

  rc = check_write_permission_folder(Parent, userId, userPerm);
  if(rc < 0)
  {
    return rc;
  }
  if(DelFlag && rc > 0){
    return 1;
  }

  snprintf(SQLFolder, MAXSQLFolder,"SELECT COUNT(*) FROM folderlist WHERE folder_pk=%ld",Parent);
  resultFolder = PQexec(pgConn, SQLFolder);
  count= atoi(PQgetvalue(resultFolder,0,0));
  PQclear(resultFolder);

  /* Find all folders with this parent and recurse, but don't show uploads, if they also exist in other directories */
  snprintf(SQL,MAXSQL,"SELECT folder_pk,foldercontents_mode,name,description,upload_pk,pfile_fk FROM folderlist WHERE parent=%ld"
                      " ORDER BY name,parent,folder_pk ", Parent);
  result = PQexec(pgConn, SQL);
  if (fo_checkPQresult(pgConn, result, SQL, __FILE__, __LINE__))
  {
    return -1;
  }
  maxRow = PQntuples(result);
  for(r=0; r < maxRow; r++)
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
      rc = listFoldersRecurse(Fid,Depth+1,Parent,DelFlag,userId,userPerm);
      if (rc < 0)
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
      if (DelFlag==1 && unlinkContent(Parent,Row,1,userId,userPerm)==0)
      {
        continue;
      }
      if (rc < 0)
      {
        return rc;
      }
      if (DelFlag)
      {
        snprintf(SQLUpload, MAXSQL,"SELECT COUNT(*) FROM folderlist WHERE pfile_fk=%ld", atol(PQgetvalue(result,r,5)));
        resultUpload = PQexec(pgConn, SQLUpload);
        resultUploadCount = atoi(PQgetvalue(resultUpload,0,0));
        if(count < 2 && resultUploadCount < 2)
        {
          rc = deleteUpload(atol(PQgetvalue(result,r,4)),userId, userPerm);
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
        else{
          rc = unlinkContent(atol(PQgetvalue(result,r,4)),Parent,2,userId,userPerm);
          if(rc < 0){
            return rc;
          }
        }
      }
      else
      {
        rc = check_read_permission_upload(atol(PQgetvalue(result,r,4)),userId,userPerm);
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
        rc = unlinkContent(Parent,Row,1,userId,userPerm);
        if (rc == 0)
        {
          break;
        }
        if (rc < 0)
        {
          return rc;
        }
      }
      if(Row > 0)
        snprintf(SQL,MAXSQL,"DELETE FROM foldercontents WHERE foldercontents_mode=1 AND parent_fk=%ld AND child_id=%ld",Row,Parent);
      else
        snprintf(SQL,MAXSQL,"DELETE FROM foldercontents WHERE foldercontents_mode=1 AND child_id=%ld",Parent);
      if (Test)
      {
        printf("TEST: %s\n",SQL);
      }
      else
      {
        PQexecCheckClear(NULL, SQL, __FILE__, __LINE__);
      }
      if(Row > 0)
        snprintf(SQL,MAXSQL,"DELETE FROM folder f USING foldercontents fc WHERE  f.folder_pk = fc.child_id AND fc.parent_fk='%ld' AND f.folder_pk = '%ld';",Row,Parent);
      else
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

/**
 * \brief Given a PGresult, find detached folders
 * \param result PGresult from a query
 * \param userId
 * \param userPerm permission level the user has
 * \return 0: success;
 *         1: fail;
 *        -1: failure
 */
int listFoldersFindDetatchedFolders(PGresult *result, int userId, int userPerm)
{
  int DetachFlag=0;
  int i,j;
  int maxRow = PQntuples(result);
  long Fid; /* folder ids */
  int Match;
  char *Desc;
  int rc;

  /* Find detached folders */
  for(i=0; i < maxRow; i++)
  {
    Fid = atol(PQgetvalue(result,i,1));
    if (Fid == 1)
    {
      continue; /* skip default parent */
    }
    Match=0;
    for(j=0; (j<maxRow) && !Match; j++)
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
      rc = listFoldersRecurse(Fid,1,i,0,userId,userPerm);
      if (rc < 0)
      {
        return rc;
      }
    }
  }
  return 0;
}

/**
 * \brief Given a PGresult, find detached uploads
 * \param result PGresult from a query
 * \param userId
 * \param userPerm permission level the user has
 * \return 0: success
 */
int listFoldersFindDetatchedUploads(PGresult *result, int userId, int userPerm)
{
  int DetachFlag=0;
  int i,j;
  int maxRow = PQntuples(result);
  long Fid; /* folder ids */
  int Match;
  char *Desc;
  /* Find detached uploads */
  for(i=0; i < maxRow; i++)
  {
    Fid = atol(PQgetvalue(result,i,1));
    if (Fid == 1)
    {
      continue; /* skip default parent */
    }
    Match=0;
    for(j=0; (j<maxRow) && !Match; j++)
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

/**
 * \brief Given a user id, find detached folders and uploads
 * \param userId
 * \param userPerm permission level the user has
 * \return 0: success;
 *         1: fail;
 *        -1: failure
 */
int listFoldersFindDetatched(int userId, int userPerm)
{
  char SQL[MAXSQL];
  PGresult *result;
  int rc;

  snprintf(SQL,MAXSQL,"SELECT folder_pk,parent,name,description,upload_pk FROM folderlist ORDER BY name,parent,folder_pk;");
  result = PQexec(pgConn, SQL);
  if (fo_checkPQresult(pgConn, result, SQL, __FILE__, __LINE__))
  {
    return -1;
  }
  rc = listFoldersFindDetatchedFolders(result, userId, userPerm);
  if (rc < 0 )
  {
    PQclear(result);
    return rc;
  }
  rc = listFoldersFindDetatchedUploads(result, userId, userPerm);
  PQclear(result);
  if (rc < 0 )
  {
    return rc;
  }
  return 0;
}

/**
 * \brief List every folder.
 * \param userId
 * \param userPerm permission level the user has
 */
int listFolders (int userId, int userPerm)
{
  char SQL[MAXSQL];
  PGresult *result;
  int rc;

  if(userPerm == 0){
    printf("you do not have the permsssion to view the folder list.\n");
    return 1;
  }

  printf("# Folders\n");
  snprintf(SQL,MAXSQL,"SELECT folder_name from folder where folder_pk =1;");
  result = PQexec(pgConn, SQL);
  if (fo_checkPQresult(pgConn, result, SQL, __FILE__, __LINE__))
  {
    return -1;
  }

  printf("%4d :: %s\n", 1, PQgetvalue(result,0,0));
  PQclear(result);

  rc = listFoldersRecurse(1,1,-1,0,userId,userPerm);
  if (rc < 0)
  {
    return rc;
  }

  rc = listFoldersFindDetatched(userId, userPerm);
  if (rc < 0)
  {
    return rc;
  }
  return 0;
} /* listFolders() */

/**
 * \brief List every upload ID.
 *
 * \param userId user id
 * \param userPerm permission level the user has
 * \return 0 on success; -1 on failure
 */
int listUploads (int userId, int userPerm)
{
  int Row,maxRow;
  long NewPid;
  PGresult *result;
  int rc;
  char *SQL = "SELECT upload_pk,upload_desc,upload_filename FROM upload ORDER BY upload_pk;";
  printf("# Uploads\n");
  result = PQexec(pgConn, SQL);
  if (fo_checkPQresult(pgConn, result, SQL, __FILE__, __LINE__))
  {
    exitNow(-1);
  }

  /* list each value */
  maxRow = PQntuples(result);
  for(Row=0; Row < maxRow; Row++)
  {
    NewPid = atol(PQgetvalue(result,Row,0));
    rc = check_read_permission_upload(NewPid, userId, userPerm);
    if (rc < 0)
    {
      PQclear(result);
      return rc;
    }
    if (NewPid >= 0 && (userPerm == PERM_ADMIN || rc  == 0))
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
 * \brief recursively delete a folder
 *
 *  Given a folder ID, delete it AND recursively delete everything below it!
 *  This includes upload deletion!
 *
 * \param cFolder the folder id to delete
 * \param pFolder parent of the current folder
 * \param userId
 * \param userPerm permission level the user has
 *
 * \return 0: success;
 *         1: fail
 *        -1: failure
 *
 **/
int deleteFolder(long cFolder, long pFolder,  int userId, int userPerm)
{
  if(pFolder == 0) pFolder= -1 ;
  return listFoldersRecurse(cFolder, 0,pFolder,2,userId,userPerm);
} /* deleteFolder() */

/**********************************************************************/

/**
 * \brief Parse parameters
 *
 *  Read Parameter from scheduler.
 *  Process line elements.
 *
 * \param Parm the parameter string
 * \param userId
 * \param userPerm permission level the user has
 *
 * \return 0: yes, can is deleted;
 *         1: can not be deleted;
 *        -1: failure;
 *        -2: does not exist
 *
 **/
int readAndProcessParameter (char *Parm, int userId, int userPerm)
{
  char *L;
  int rc=0;     /* assume no data */
  int Type=0; /* 0=undefined; 1=delete; 2=list */
  int Target=0; /* 0=undefined; 1=upload; 2=license; 3=folder */
  const char s[2] = " ";
  char *token;
  char a[15];
  long fd[2];
  int i = 0, len = 0;

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

  len = strlen(L);
  memcpy(a, L,len);
  token = strtok(a, s);

  while( token != NULL )
  {
    fd[i] = atol(token);
    token = strtok(NULL, s);
    i++;
  }

  /* Handle the request */
  if ((Type==1) && (Target==1))
  {
    rc = deleteUpload(fd[0], userId, userPerm);
  }
  else if ((Type==1) && (Target==3))
  {
    rc = deleteFolder(fd[1],fd[0], userId, userPerm);
  }
  else if (((Type==2) && (Target==1)) || ((Type==2) && (Target==2)))
  {
    rc = listUploads(0, PERM_ADMIN);
  }
  else if ((Type==2) && (Target==3))
  {
    rc = listFolders(userId, userPerm);
  }
  else
  {
    LOG_ERROR("Unknown command: '%s'\n",Parm);
  }

  return rc;
} /* readAndProcessParameter() */

/**
 * \brief process the jobs from scheduler
 *
 * -# Read the jobs from the scheduler using fo_scheduler_next().
 * -# Get the permission level of the current user.
 * -# Parse the parameters and process
 * \see fo_scheduler_next()
 * \see readAndProcessParameter()
 */
void doSchedulerTasks()
{
  char *Parm = NULL;
  char SQL[MAXSQL];
  PGresult *result;
  int userId = -1;
  int userPerm = -1;

  while(fo_scheduler_next())
  {
    Parm = fo_scheduler_current();
    userId = fo_scheduler_userID();

    /* get perm level of user */
    snprintf(SQL,MAXSQL,"SELECT user_perm FROM users WHERE user_pk='%d';", userId);
    result = PQexec(pgConn, SQL);
    if (fo_checkPQresult(pgConn, result, SQL, __FILE__, __LINE__) || !PQntuples(result))
    {
      exitNow(0);
    }
    userPerm = atoi(PQgetvalue(result, 0, 0));
    PQclear(result);

    int returnCode = readAndProcessParameter(Parm, userId, userPerm);
    if (returnCode != 0)
    {
      /* Loglevel is to high, but scheduler expects FATAL log message before exit */
      LOG_FATAL("Due to permission problems, the delagent was not able to list or delete the requested objects or they did not exist.");
      exitNow(returnCode);
    }
  }
}
/**
 * @brief Exit function.  This does all cleanup and should be used
 *        instead of calling exit() or main() return.
 *
 * @param ExitVal Exit value
 * @returns void Calls exit()
 */
void exitNow(int exitVal)
{
  if (pgConn) PQfinish(pgConn);

  if (exitVal) LOG_ERROR("Exiting with status %d", exitVal);

  fo_scheduler_disconnect(exitVal);
  exit(exitVal);
} /* exitNow() */
