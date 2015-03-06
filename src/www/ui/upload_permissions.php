<?php
/***********************************************************
 Copyright (C) 2013 Hewlett-Packard Development Company, L.P.

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
 * @file upload_permissions.php
 * @brief edit upload permissions
 **/

define("TITLE_upload_permissions", _("Edit Uploaded File Permissions"));

class upload_permissions extends FO_Plugin 
{
  function __construct()
  {
    $this->Name = "upload_permissions";
    $this->Title = TITLE_upload_permissions;
    $this->Version = "1.0";
    $this->MenuList = "Admin::Upload Permissions";
    $this->Dependency = array();
    $this->DBaccess = PLUGIN_DB_WRITE;
    parent::__construct();
  }


  /* @brief Display group membership
   * @return html
   **/
  function DisplayGroupMembership()
  {
    global $SysConf;
    global $PG_CONN;

    $group_pk = GetParm('group_pk', PARM_INTEGER);
    $user_pk = $SysConf['auth']['UserId'];
    $V = "";

    $text = _("To edit group memberships ");
    $text2 = _("click here");
    $V .= "<p>$text" . "<a href='" . Traceback_uri() . "?mod=group_manage_users'>" . $text2 . "</a>";
    $text = _("Look up who is a member of group ");

    /* Get array of groups that this user is an admin of */
    $GroupArray = GetGroupArray($user_pk);
    if (empty($GroupArray))
    {
      $text = _("You have no permission to manage any group.");
      echo "<p>$text<p>";
      return;
    }
    reset($GroupArray);
    if (empty($group_pk)) $group_pk = key($GroupArray);

    $text = _("To list the users in a group, select the group:  \n");
    $V.= "<p>$text";

    /*** Display group select list, on change request new page with group= in url ***/
    $url = preg_replace('/&group_pk=[0-9]*/',"",Traceback()) . "&group_pk=";

    $onchange = "onchange=\"js_url(this.value, '$url')\"";
    $V .= Array2SingleSelect($GroupArray, "groupuserselect", $group_pk, false, false, $onchange);

    /* Select all the user members of this group */
    $sql = "select group_user_member_pk, user_fk, group_perm, user_name from group_user_member GUM, users
              where GUM.group_fk=$group_pk and user_fk=user_pk";
    $result = pg_query($PG_CONN, $sql);
    DBCheckResult($result, $sql, __FILE__, __LINE__);
    $GroupMembersArray = pg_fetch_all($result);
    pg_free_result($result);

    /* Permissions Table */
    $V .= "<p><table border=1>";
    $UserText = _("User");
    $V .= "<tr><th>$UserText</th></tr>";
    foreach ($GroupMembersArray as $GroupMember)
    {
      $V .= "<tr>";
      $V .= "<td>";  // user
      $V .= $GroupMember['user_name'];
      $V .= "</td>";
      $V .= "</tr>";
    }

    $V .= "</table>";

    return $V;
  }


  public function Output()
  {
    global $PG_CONN;
    global $PERM_NAMES;

    /* GET parameters */
    $folder_pk = GetParm('folder', PARM_INTEGER);
    $upload_pk = GetParm('upload', PARM_INTEGER);
    $users_group_pk = GetParm('group_pk', PARM_INTEGER);
    $group_pk = GetParm('group', PARM_INTEGER);
    $perm_upload_pk = GetParm('permupk', PARM_INTEGER);
    $perm = GetParm('perm', PARM_INTEGER);
    $newgroup = GetParm('newgroup', PARM_INTEGER);
    $newperm = GetParm('newperm', PARM_INTEGER);
    
    $public_perm = GetArrayVal('public', $_GET);
    if ($public_perm == "") $public_perm = -1;

    $V = "";

    /* If perm_upload_pk is passed in, update either the perm or group_pk */
    $sql = "";
    if (!empty($perm_upload_pk))
    { 
      if ($perm === 0)
      {
        $sql = "delete from perm_upload where perm_upload_pk='$perm_upload_pk'";
      }
      else if (!empty($perm))
      {
        $sql = "update perm_upload set perm='$perm' where perm_upload_pk='$perm_upload_pk'";
      }
      else if (!empty($group_pk))
      {
        $sql = "update perm_upload set group_fk='$group_pk' where perm_upload_pk='$perm_upload_pk'";
      }
      
      if (!empty($sql))
      {
        $result = pg_query($PG_CONN, $sql);
        DBCheckResult($result, $sql, __FILE__, __LINE__);
        pg_free_result($result);
      } 
    }
    else if (!empty($newgroup) and (!empty($newperm)))
    {
      // before inserting this new record, delete any record for the same upload and group since
      // that would be a duplicate
      $sql = "delete from perm_upload where upload_fk=$upload_pk and group_fk=$newgroup";
      $result = pg_query($PG_CONN, $sql);
      DBCheckResult($result, $sql, __FILE__, __LINE__);
      pg_free_result($result);
    
      // Don't insert a PERM_NONE.  NONE is the default 
      if ($newperm != PERM_NONE)
      {
        $sql = "insert into perm_upload (perm, upload_fk, group_fk) values ($newperm, $upload_pk, $newgroup)";
        $result = pg_query($PG_CONN, $sql);
        DBCheckResult($result, $sql, __FILE__, __LINE__);
        pg_free_result($result);
      }
      $newperm = $newgroup = 0;
    }
    else if ($public_perm >= 0)
    {
      $sql = "update upload set public_perm='$public_perm' where upload_pk='$upload_pk'";
      $result = pg_query($PG_CONN, $sql);
      DBCheckResult($result, $sql, __FILE__, __LINE__);
      pg_free_result($result);
    }

    $root_folder_pk = GetUserRootFolder();
    if (empty($folder_pk)) $folder_pk = $root_folder_pk;

    // Get folder array folder_pk => folder_name
    $FolderArray = array();
    GetFolderArray($root_folder_pk, $FolderArray);

    /* define js_url */
    $V .= js_url(); 

    $text = _("Select the folder that contains the upload:  \n");
    $V.= "$text";

    /*** Display folder select list, on change request new page with folder= in url ***/
    $url = Traceback_uri() . "?mod=upload_permissions&folder=";
    $onchange = "onchange=\"js_url(this.value, '$url')\"";
    $V .= Array2SingleSelect($FolderArray, "folderselect", $folder_pk, false, false, $onchange);

    /*** Display upload select list, on change, request new page with new upload= in url ***/
    $text = _("Select the upload you wish to edit:  \n");
    $V.= "<br>$text";

    // Get list of all upload records in this folder that the user has PERM_ADMIN
    $UploadList = FolderListUploads_perm($folder_pk, PERM_ADMIN);

    // Make data array for upload select list.  Key is upload_pk, value is a composite
    // of the upload_filename and upload_ts.
    // Note that $UploadList may be empty so $UploadArray will be empty
    $UploadArray = array();
    foreach($UploadList as $UploadRec) 
    {
      $SelectText = htmlentities($UploadRec['name']);
      if (!empty($UploadRec['upload_ts']))
      {
        $SelectText .= ", " . substr($UploadRec['upload_ts'], 0, 19);
      }
      $UploadArray[$UploadRec['upload_pk']] = $SelectText;
    }

    /* Get selected upload info to display*/
    if (empty($upload_pk))
    {
      // no upload selected, so use the top one in the select list
      reset($UploadArray);
      $upload_pk = key($UploadArray);
    }

    /* Upload select list */
    $url = Traceback_uri() . "?mod=upload_permissions&folder=$folder_pk&upload=";
    $onchange = "onchange=\"js_url(this.value, '$url')\"";
    $V .= Array2SingleSelect($UploadArray, "uploadselect", $upload_pk, false, false, $onchange);

    /* Get permissions for this upload */
    if (!empty($UploadArray))
    {
      // Get upload.public_perm
      $sql = "select public_perm from upload where upload_pk='$upload_pk'";
      $result = pg_query($PG_CONN, $sql);
      DBCheckResult($result, $sql, __FILE__, __LINE__);
      $Row = pg_fetch_all($result);
      $public_perm = $Row[0]['public_perm'];
      pg_free_result($result);
      $text1 = _("Public Permission");
      $V .= "<p>$text1 &nbsp;";
      $url = Traceback_uri() . "?mod=upload_permissions&folder=$folder_pk&upload=$upload_pk&public=";
      $onchange = "onchange=\"js_url(this.value, '$url')\"";
      $V .= Array2SingleSelect($PERM_NAMES, "publicpermselect", $public_perm, false, false, $onchange);

      $sql = "select perm_upload_pk, perm, group_pk, group_name from groups, perm_upload where group_fk=group_pk and upload_fk='$upload_pk'";
      $result = pg_query($PG_CONN, $sql);
      DBCheckResult($result, $sql, __FILE__, __LINE__);
      $PermArray = pg_fetch_all($result);
      pg_free_result($result);

      /* Get master array of groups */
      $sql = "select group_pk, group_name from groups order by group_name";
      $result = pg_query($PG_CONN, $sql);
      DBCheckResult($result, $sql, __FILE__, __LINE__);
      $GroupArray = array();
      while ($GroupRow = pg_fetch_assoc($result))
      {
        $GroupArray[$GroupRow['group_pk']] = $GroupRow['group_name'];
      }
      pg_free_result($result);

      /* Permissions Table */
      $V .= "<p><table border=1>";
      $GroupText = _("Group");
      $PermText = _("Permission");
      $V .= "<tr><th>$GroupText</th><th>$PermText</th></tr>";
      foreach ($PermArray as $PermRow)
      {
        $V .= "<tr>";
        $V .= "<td>";  // group
        $url = Traceback_uri() . "?mod=upload_permissions&group_pk=$users_group_pk&upload=$upload_pk&folder=$folder_pk&permupk={$PermRow['perm_upload_pk']}&group=";
        $onchange = "onchange=\"js_url(this.value, '$url')\"";
        $V .= Array2SingleSelect($GroupArray, "groupselect", $PermRow['group_pk'], false, false, $onchange);
        $V .= "</td>";
        $V .= "<td>";  // permission
        $url = Traceback_uri() . "?mod=upload_permissions&group_pk=$users_group_pk&upload=$upload_pk&folder=$folder_pk&permupk={$PermRow['perm_upload_pk']}&perm=";
        $onchange = "onchange=\"js_url(this.value, '$url')\"";
        $V .= Array2SingleSelect($PERM_NAMES, "permselect", $PermRow['perm'], false, false, $onchange);
        $V .= "</td>";
        $V .= "</tr>";
      }
      /* Print one extra row for adding perms */
      $V .= "<tr>";
      $V .= "<td>";  // group
      $url = Traceback_uri() . "?mod=upload_permissions&group_pk=$users_group_pk&upload=$upload_pk&folder=$folder_pk&newperm=$newperm&newgroup=";
      $onchange = "onchange=\"js_url(this.value, '$url')\"";
      $Selected = (empty($newgroup)) ? "" : $newgroup;
      $V .= Array2SingleSelect($GroupArray, "groupselectnew", $Selected, true, false, $onchange);
      $V .= "</td>";
      $V .= "<td>";  // permission
      $url = Traceback_uri() . "?mod=upload_permissions&group_pk=$users_group_pk&upload=$upload_pk&folder=$folder_pk&newgroup=$newgroup&newperm=";
      $onchange = "onchange=\"js_url(this.value, '$url')\"";
      $Selected = (empty($newperm)) ? "" : $newperm;
      $V .= Array2SingleSelect($PERM_NAMES, "permselectnew", $Selected, false, false, $onchange);
      $V .= "</td>";
      $V .= "</tr>";
  
      $V .= "</table>";
  
      $text = _("All upload permissions take place immediately when a value is changed.  There is no submit button.");
      $V .= "<p>" . $text;
      $text = _("Add new groups on the last line.");
      $V .= "<br>" . $text;
    }
    else
    {
      $text = _("You have no permission to change permissions on any upload in this folder.");
      $V .= "<p>$text<p>";
    }

    $V .= "<hr>";
    $V .= $this->DisplayGroupMembership();
    return $V;
  }
}
$NewPlugin = new upload_permissions;