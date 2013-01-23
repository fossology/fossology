<?php
/***********************************************************
 Copyright (C) 2011-2013 Hewlett-Packard Development Company, L.P.

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
 ***********************************************************/

/*************************************************
 Library of common functions for permissions and groups.
 *************************************************/

  /***********************************************************
   Function: GetUploadsFromFolder()

   @param int $folder_pk 
   @Return array of upload_pks for all the uploads in this
           folder (and subfolders).
   ***********************************************************/
   function GetUploadsFromFolder($folder_pk)
   {
     $uploads = array();
     if (empty($folder_pk)) return $uploads;
     GetUploadsFromFolder_recurse($folder_pk, $uploads);
     return $uploads;
   }  /* GetUploadsFromFolder */

  /***********************************************************
   Function: GetUploadsFromFolder_recurse()

   @param int $folder_pk 
   @param array $uploads  array of upload_pk's.  Caller must
   @Return array of upload_pks for all the uploads in this
           folder (and subfolders).
   ***********************************************************/
   function GetUploadsFromFolder_recurse($folder_pk, &$uploads)
   {
     global $PG_CONN;

     $sql = "select * from foldercontents where parent_fk=$folder_pk";
     $result = pg_query($PG_CONN, $sql);
     DBCheckResult($result, $sql, __FILE__, __LINE__);
     while ($row = pg_fetch_assoc($result)) 
     {
       switch ($row["foldercontents_mode"])
       {
         case 1:  // Child is folder
           GetUploadsFromFolder_recurse($row["child_id"], $uploads);
           break;
         case 2:  // Child is upload
           $uploads[] = $row["child_id"];
           break;
         default:
           // Other modes not used at this time
       }
     }
     pg_free_result($result);
   }  /* GetUploadsFromFolder_recurse */


  /***********************************************************
   Function: GetFolderOwners()

   Get owners of $folder_pk uploads

   @param int $folder_pk 

   @Return array of user_pks of the owners of the uploads
           in this folder (and subfolders).
   ***********************************************************/
   function GetFolderOwners($folder_pk)
   {
     global $PG_CONN;

     /* get all the upload_pk's for all the uploads in this
      * folder and its subfolders.
      */
     $upload_pkArray = GetUploadsFromFolder($folder_pk);

     /* no uploads */
     if (empty($upload_pkArray)) return array();

     /* get all the unique user_pks for these uploads */
     $sql = "select distinct user_fk as user_pk from upload where (user_fk is not NULL) and (";
     $uploadCount = 0;
     foreach($upload_pkArray as $upload_pk)
     {
       if ($uploadCount++) $sql .= " or ";
       $sql .= "(upload_pk=$upload_pk)";
     }
     $sql .= ")";
     $result = pg_query($PG_CONN, $sql);
     DBCheckResult($result, $sql, __FILE__, __LINE__);
     $owner_pkArray = array();
     while ($row = pg_fetch_assoc($result)) $owner_pkArray[] = $row["user_pk"];
     pg_free_result($result);
     return $owner_pkArray;
   }  /* GetFolderOwners() */


  /***********************************************************
   Function: GetValidFolder()

   @param int $RequestedFolder_pk Folder user is requesting.

   @Return the folder to browse.  If the user has access to 
           the requested folder, then return that.  If not,
           or $RequestedFolder is empty, return the users
           root folder.

   If GlobalBrowse is true, anyone can browse any folder.
   If GlobalBrowse is false, the user can only browse their folder.
   If an illegal folder is requested, return the users root folder.
  
   NOTE: This implementation is a kludge until permissions are 
         implemented on folders, uploads, and items.
   NOTE: This function doesn't check if the $RequestedFolder exists.
   ***********************************************************/
  function GetValidFolder($RequestedFolder)
  {
    /* Nothing asked for?  Then return their root folder */
    if (empty($RequestedFolder)) return GetUserRootFolder();

    /* If there are no restrictions on browsing, then we are done */
    if (IsRestrictedTo() === false) return $RequestedFolder;

    /* Since there are restrictions, make sure the user has an upload in
     * the requested folder.
     */
    $FolderOwnersArray = GetFolderOwners($RequestedFolder);
    if (false === array_search($_SESSION['UserId'], $FolderOwnersArray))
      return GetUserRootFolder();
    return $RequestedFolder;
  }  /* GetValidFolder() */


  /***********************************************************
   Function: HaveFolderPerm()

   @param int $RequestedFolder_pk Folder user is requesting.

   @Return true if the user has permission to browse this folder.
                or $RequestedFolder is empty.
           false if user does not hav permission to browse this folder.

   NOTE: This implementation is a kludge until permissions are 
         implemented on folders, uploads, and items.
   ***********************************************************/
  function HaveFolderPerm($RequestedFolder)
  {
    /* Nothing asked for?  Then permission granted. */
    if (empty($RequestedFolder)) return true;

    /* If there are no restrictions on browsing, then we are done */
    if (IsRestrictedTo() === false) return true;

    /* Since there are restrictions, make sure the user has an upload in
     * the requested folder.
     */
    $FolderOwnersArray = GetFolderOwners($RequestedFolder);
    if (false === array_search($_SESSION['UserId'], $FolderOwnersArray))
      return false;
    return true;
  }  /* HaveFolderPerm() */


  /***********************************************************
   Function: HaveUploadPerm()

   @param int $upload_pk
   @Return true if user has permission to browse this upload
                or $upload_pk is empty.
   ***********************************************************/
  function HaveUploadPerm($upload_pk)
  {
    global $PG_CONN;
     
    /* Nothing asked for?  Then return true */
    if (empty($upload_pk)) return true;

    /* If there are no restrictions on browsing, then we are done */
    if (IsRestrictedTo() === false) return true;

    /* upload is permission controlled so make sure the owner is the requestor */
    $sql = "select user_fk from upload where upload_pk=$upload_pk and user_fk='$_SESSION[UserId]'";
     $result = pg_query($PG_CONN, $sql);
     DBCheckResult($result, $sql, __FILE__, __LINE__);
     $found = pg_num_rows($result);
     pg_free_result($result);
     if ($found > 0) return true;
     return false;
  }  /* HaveUploadPerm() */


  /***********************************************************
   Function: HaveItemPerm()

   @param int $uploadtree_pk
   @Return true if user has permission to browse this item
                or $uploadtree_pk is empty.
   ***********************************************************/
  function HaveItemPerm($uploadtree_pk)
  {
    global $PG_CONN;
     
    /* Nothing asked for?  Then return true */
    if (empty($upload_pk)) return true;

    /* If there are no restrictions on browsing, then we are done */
    if (IsRestrictedTo() === false) return true;

    /* upload is permission controlled so make sure the owner is the requestor 
     * First find what upload this item came from.
     */
    $sql = "select upload_fk from uploadtree where uploadtree_pk=$uploadtree_pk";
     $result = pg_query($PG_CONN, $sql);
     DBCheckResult($result, $sql, __FILE__, __LINE__);
     $row = pg_fetch_assoc($result);
     pg_free_result($result);
     return HaveUploadPerm($row["upload_fk"]);
  }  /* HaveItemPerm() */


  /***********************************************************
   Function: IsRestrictedTo()

   @Return If the user has browsing restricted, return their userId.
           If not, return false, there are no browsing restrictions.

   If GlobalBrowse is true, anyone can browse anything.
   If GlobalBrowse is false, the user can only browse their folder, 
                             uploads, items.
  
   NOTE: This implementation is a kludge until permissions are 
         implemented on folders, uploads, and items.
   ***********************************************************/
  function IsRestrictedTo()
  {
    global $SysConf;

    if ((strcasecmp(@$SysConf["SYSCONFIG"]["GlobalBrowse"],"true") == 0) 
      or (@$_SESSION['UserLevel'] == PLUGIN_DB_ADMIN)) return false;
    return @$_SESSION['UserId'];
  }


  /**
   *  @brief Get all users in a group
   *  @param $group_pk
   *  @return array of user_pk's and their permission (group_perm)
   *  @todo currently this function is not implemented
   **/
  function GetUsersInGroup($group_pk)
  {
  }

  /**
   *  @brief Check if User is already in the $GroupArray.
   *  If not, add them.  If they are, update their record with the
   *  highest permission granted to them.
   *  
   *  @param $GroupRow
   *  @param $GroupArray
   *  @return $GroupArray is updated.
   **/
  function AddUserToGroupArray($GroupRow, &$GroupArray)
  {
    /* loop throught $GroupArray to see if the user is already present */
    $found = false;
    foreach($GroupArray as &$Grec)
    {
      if ($Grec['user_pk'] == $GroupRow['user_fk'])
      {
        /* user already exists in $GroupArray, so make sure they have the highest
         * permission granted to them.
         */
        if ($Grec['group_perm'] < $GroupRow['group_perm'])
          $Grec['group_perm'] = $GroupRow['group_perm'];
        $found = true;
        break;
      }
    }

    if (!$found)
    {
      $NewGroup['user_pk'] = $GroupRow['user_fk'];
      $NewGroup['group_pk'] = $GroupRow['group_pk'];
      $NewGroup['group_name'] = $GroupRow['group_name'];
      $NewGroup['group_perm'] = $GroupRow['group_perm'];
      $GroupArray[] = $NewGroup;
    }
  }

  /**
   *  @brief Get all the users users of this group.
   *  @param $user_pk optional, if specified limit to single user
   *  @param $group_pk
   *  @return array of groups the and the user's permission (group_perm) in each group
   *  -  [user_pk]
   *  -  [group_pk]
   *  -  [group_name]
   *  -  [group_perm]
   **/
  function GetGroupUsers($user_pk, $group_pk, &$GroupArray)
  {
    global $PG_CONN;
    $GroupArray = array();

    $user_pk = GetArrayVal("UserId", $_SESSION);
    if (empty($user_pk)) return $GroupArray;

    /****** For this group, get its users ******/
    if (empty($user_pk))
      $UserCondition = "";
    else
      $UserCondition = " and user_fk=$user_pk ";

    $sql = "select group_pk, group_name, group_perm, user_fk from group_user_member, groups where group_pk=$group_pk and group_pk=group_fk $UserCondition";
    $result = pg_query($PG_CONN, $sql);
    DBCheckResult($result, $sql, __FILE__, __LINE__);
    while ($row = pg_fetch_assoc($result)) 
    {
      /* Add the user(s) to $GroupArray */
      AddUserToGroupArray($row, $GroupArray);
    }
  }

  /**
   *  @brief Find all the groups a user belongs to.
   *  @param $user_pk optional, defaults to current user
   *  @return array of groups 
   *  each group is itself an array with the following elements
   *  -  [user_pk]
   *  -  [group_pk]
   *  -  [group_name]
   *  -  [group_perm]
   **/
  function GetUsersGroups($user_pk='')
  {
    global $PG_CONN;

    $GroupArray = array();

    if (empty($user_pk)) $user_pk = GetArrayVal("UserId", $_SESSION);
    if (empty($user_pk)) return $GroupArray;  /* user has no groups */

    /* find all groups with this user */
    $sql = "select group_fk as group_pk from group_user_member where user_fk=$user_pk";
    $result = pg_query($PG_CONN, $sql);
    DBCheckResult($result, $sql, __FILE__, __LINE__);
    while ($row = pg_fetch_assoc($result)) 
    {
      /* Now find all the groups that contain this group */
      GetGroupUsers($user_pk, $row['group_pk'], $GroupArray);
    }
    pg_free_result($result);
    return $GroupArray;
  }


  /**
   *  @brief Get the upload permission for a user
   *  @param $upload_pk
   *  @param $user_pk
   *  @return hightest permission level a user has for an upload
   **/
  function GetUploadPerm($upload_pk, $user_pk)
  {
    $sql = "select max(perm) as perm from perm_upload, group_user_member where perm_upload.upload_fk=$upload_pk and user_fk=$user_pk and group_user_member.group_fk=perm_upload.group_fk";
    $result = pg_query($PG_CONN, $sql);
    DBCheckResult($result, $sql, __FILE__, __LINE__);
    if (pg_num_rows($result) < 1) 
      $perm = PERM_NONE;
    else
    {
      $row = pg_fetch_assoc($result);
      $perm = $row['perm'];
    }
    pg_free_result($result);
    return $perm;
  }
?>
