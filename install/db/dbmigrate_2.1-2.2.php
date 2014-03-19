<?php
/***********************************************************
 Copyright (C) 2013-2014 Hewlett-Packard Development Company, L.P.

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
 ***********************************************************/

/**
 * @file dbmigrate_2.1-2.2.php
 * @brief This file is called by fossinit.php to migrate from
 *        a 2.1 database to 2.2.  
 *        Specifically, this is to create
 *        new groups, group_user_member, perm_upload and perm_folder records
 *        to support 2.2 permissions.
 *
 * This should be called after fossinit calls apply_schema and can be run
 * multiple times without harm.
 **/


/**
 * \brief Create
 *        new groups, group_user_member, perm_upload and perm_folder records
 *        to support 2.2 permissions.
 *
 * \return 0 on success, 1 on failure
 **/
function Migrate_21_22($Verbose)
{
  global $PG_CONN;

  // Before adding all the user groups, make sure there is a "Default User" user
  $user_name = "Default User";
  $sql = "select user_pk from users where user_name='$user_name'";
  $result = pg_query($PG_CONN, $sql);
  DBCheckResult($result, $sql, __FILE__, __LINE__);
  if (pg_num_rows($result) == 0)
  {
    // No Default User, so create one
    $Perm = 0; //PLUGIN_DB_NONE;
    $sql = "INSERT INTO users (user_name,user_desc,user_seed,user_pass,user_perm,user_email,root_folder_fk)
        VALUES ('$user_name','Default User when nobody is logged in','Seed','Pass',$Perm,NULL,1);";
    $result = pg_query($PG_CONN, $sql);
    DBCheckResult($result, $sql, __FILE__, __LINE__);
  }
  pg_free_result($result);

  // Before adding all the user groups, make sure there is a "fossy" user
  $user_name = "fossy";
  $sql = "select user_pk from users where user_name='$user_name'";
  $result = pg_query($PG_CONN, $sql);
  DBCheckResult($result, $sql, __FILE__, __LINE__);
  if (pg_num_rows($result) == 0)
  {
    // No Default User, so create one
    $Perm = 10;  //PLUGIN_DB_ADMIN;
    $Seed = rand() . rand();
    $Hash = sha1($Seed . $user_name);
    $sql = "INSERT INTO users (user_name,user_desc,user_seed,user_pass,user_perm,user_email,root_folder_fk)
        VALUES ('$user_name','Default Administrator','$Seed','$Hash',$Perm,'y',1);";
    $result = pg_query($PG_CONN, $sql);
    DBCheckResult($result, $sql, __FILE__, __LINE__);
  }
  pg_free_result($result);


  $sql = "select user_pk, user_name, root_folder_fk from users";
  $UserResult = pg_query($PG_CONN, $sql);
  DBCheckResult($UserResult, $sql, __FILE__, __LINE__);

  /* Loop through all user records making sure there is a group with the same name,
   * the group has the user with PERM_WRITE, the user is PERM_ADMIN of their own uploads
   * and the user has PERM_ADMIN to their root folder (PERM_WRITE if their root folder is
   * Software Repository.
   */
  while($UserRow = pg_fetch_assoc($UserResult))
  {
    $user_name = $UserRow['user_name'];
    $user_pk = $UserRow['user_pk'];
    $root_folder_fk = $UserRow['root_folder_fk'];
    $sql = "select group_pk from groups where group_name='$user_name'";
    $GroupResult = pg_query($PG_CONN, $sql);
    DBCheckResult($GroupResult, $sql, __FILE__, __LINE__);
    if (pg_num_rows($GroupResult) == 0)
    {
      pg_free_result($GroupResult);
      /* No group with same name as user, so create one */
      $sql = "insert into groups(group_name) values ('$user_name')";
      $result = pg_query($PG_CONN, $sql);
      DBCheckResult($result, $sql, __FILE__, __LINE__);
      /* Get new group_pk */
      $sql = "select group_pk from groups where group_name='$user_name'";
      $GroupResult = pg_query($PG_CONN, $sql);
      DBCheckResult($GroupResult, $sql, __FILE__, __LINE__);
    }
    $GroupRow = pg_fetch_assoc($GroupResult);
    $group_pk = $GroupRow['group_pk'];
    pg_free_result($GroupResult);

    /* Make sure the user is a member of this group */
    $sql = "select * from group_user_member where group_fk='$group_pk' and user_fk='$user_pk'";
    $GroupUserResult = pg_query($PG_CONN, $sql);
    DBCheckResult($GroupUserResult, $sql, __FILE__, __LINE__);
    if (pg_num_rows($GroupUserResult) == 0)
    {
      /* user is not a member of their own group, so insert them and make them an admin */
      $sql = "insert into group_user_member(group_fk, user_fk, group_perm) values($group_pk, $user_pk, 1)";
      $result = pg_query($PG_CONN, $sql);
      DBCheckResult($result, $sql, __FILE__, __LINE__);
    }
    pg_free_result($GroupUserResult);

    /* Loop through all the uploads for this user and make sure they have permission.
     * If not, give them PERM_ADMIN 
     */
    $sql = "select upload_pk from upload where user_fk='$user_pk'";
    $UploadResult = pg_query($PG_CONN, $sql);
    DBCheckResult($UploadResult, $sql, __FILE__, __LINE__);
    while($UploadRow = pg_fetch_assoc($UploadResult))
    {
      $upload_pk = $UploadRow['upload_pk'];
      $sql = "select perm_upload_pk from perm_upload where group_fk='$group_pk' and upload_fk='$upload_pk'";
      $PermUploadResult = pg_query($PG_CONN, $sql);
      DBCheckResult($PermUploadResult, $sql, __FILE__, __LINE__);
      if (pg_num_rows($PermUploadResult) == 0)
      {
        $perm_admin = PERM_ADMIN;
        $sql = "insert into perm_upload(perm, upload_fk, group_fk) values($perm_admin, $upload_pk, $group_pk)";
        $result = pg_query($PG_CONN, $sql);
        DBCheckResult($result, $sql, __FILE__, __LINE__);
      }
      pg_free_result($PermUploadResult);
    }
    pg_free_result($UploadResult);

  }
  pg_free_result($UserResult);
                    

  /** delete GlobalBrowse field if exist */
  $sql = "delete from sysconfig where variablename = 'GlobalBrowse'";
  $result = pg_query($PG_CONN, $sql);
  DBCheckResult($result, $sql, __FILE__, __LINE__);
  pg_free_result($result);

  /** add UNIQUE CONSTRAINT on rf_shortname column of licenser_ref table when not exist */
  $sql = "SELECT conname from pg_constraint where conname= 'license_ref_rf_shortname_key';";
  $conresult = pg_query($PG_CONN, $sql);
  DBCheckResult($conresult, $sql, __FILE__, __LINE__);
  $tt = pg_num_rows($conresult);
  if (pg_num_rows($conresult) == 0) {
    $sql = "ALTER TABLE license_ref ADD CONSTRAINT  license_ref_rf_shortname_key UNIQUE (rf_shortname); ";
    $result = pg_query($PG_CONN, $sql);
    DBCheckResult($result, $sql, __FILE__, __LINE__);
    pg_free_result($result);
  }
  pg_free_result($conresult);

  /** Clean uploadtree_a table  */
  $sql = "CREATE VIEW uploadtree_a_upload AS SELECT uploadtree_pk,upload_fk,upload_pk FROM uploadtree_a LEFT OUTER JOIN upload ON upload_fk=upload_pk;
          DELETE FROM uploadtree_a WHERE uploadtree_pk IN (SELECT uploadtree_pk FROM uploadtree_a_upload WHERE upload_pk IS NULL);
          DROP VIEW uploadtree_a_upload;";
  $result = pg_query($PG_CONN, $sql);
  DBCheckResult($result, $sql, __FILE__, __LINE__);
  pg_free_result($result);
  /** add foreign key CONSTRAINT on upload_fk of uploadtree_a table when not exist */
  $sql = "SELECT conname from pg_constraint where conname= 'uploadtree_a_upload_fk_fkey';";
  $conresult = pg_query($PG_CONN, $sql);
  DBCheckResult($conresult, $sql, __FILE__, __LINE__);
  if (pg_num_rows($conresult) == 0) {
    $sql = "ALTER TABLE uploadtree_a ADD CONSTRAINT uploadtree_a_upload_fk_fkey FOREIGN KEY (upload_fk) REFERENCES upload (upload_pk) ON DELETE CASCADE; ";
    $result = pg_query($PG_CONN, $sql);
    DBCheckResult($result, $sql, __FILE__, __LINE__);
    pg_free_result($result);
  }
  pg_free_result($conresult);


  /** Run program to rename licenses **/
  global $LIBEXECDIR;
  require_once("$LIBEXECDIR/fo_mapping_license.php");
  if($Verbose)
    print "Rename license in $LIBEXECDIR\n";
  Rename_Licenses($Verbose);
  /** Clear out the report cache **/
  if($Verbose)
    print "Clear out the report cache.\n";
  $sql = "DELETE FROM report_cache;";
  $result = pg_query($PG_CONN, $sql);
  DBCheckResult($result, $sql, __FILE__, __LINE__);
  pg_free_result($result);  

  return 0;  // success
} // Migrate_21_22

?>
