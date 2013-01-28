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

define("TITLE_upload_permissions", _("Edit Upload File Permissions"));

class upload_permissions extends FO_Plugin 
{
  var $Name = "upload_permissions";
  public $Title = TITLE_upload_permissions;
  var $Version = "1.0";
  var $MenuList = "Organize::Uploads::Permissions";
  var $Dependency = array();
  var $DBaccess = PLUGIN_DB_WRITE;

  /**
   * @brief Update upload Properties (name and description)
   *
   * @param $uploadId upload.upload_pk of record to update
   * @param $NewName New upload.upload_filename, and uploadtree.ufle_name
   *        If null, old value is not changed.
   * @param $NewDesc New upload description (upload.upload_desc)
   *        If null, old value is not changed.
   *
   * @return 1 if the upload record is updated, 0 if not, 2 if no inputs
   **/
  function myfunction($uploadId, $NewName, $NewDesc) 
  {
    global $PG_CONN;
  }

  /*********************************************
   Output(): Generate the text for this plugin.
   *********************************************/
  function Output() 
  {
    global $PG_CONN;
    global $PERM_NAMES;

    /* GET parameters */
    $folder_pk = GetParm('folder', PARM_INTEGER);
    $upload_pk = GetParm('upload', PARM_INTEGER);
    $group_pk = GetParm('group', PARM_INTEGER);
    $perm_upload_pk = GetParm('permupk', PARM_INTEGER);
    $perm = GetParm('perm', PARM_INTEGER);
    $newgroup = GetParm('newgroup', PARM_INTEGER);
    $newperm = GetParm('newperm', PARM_INTEGER);

    /* If perm_upload_pk is passed in, update either the perm or group_pk */
    $sql = "";
    if (!empty($perm_upload_pk))
    { 
      if (!empty($perm))
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
      $sql = "insert into perm_upload (perm, upload_fk, group_fk) values ($newperm, $upload_pk, $newgroup)";
      $result = pg_query($PG_CONN, $sql);
      DBCheckResult($result, $sql, __FILE__, __LINE__);
      pg_free_result($result);
    }

    $root_folder_pk = GetUserRootFolder();
    if (empty($folder_pk)) $folder_pk = $root_folder_pk;

    // Get folder array folder_pk => folder_name
    $FolderArray = array();
    GetFolderArray($root_folder_pk, $FolderArray);

    // start building the output buffer
    $V = "";
    /* define js_url */
    $V .= js_url(); 

    /* Build the HTML form */
//    $V.= "<form name='formy' method='post'>\n"; // no url = this url
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
    $UploadArray = array();
    foreach($UploadList as $UploadRec) 
    {
      $SelectText = htmlentities($UploadRec['name']);
      if (!empty($UploadRec['upload_ts'])) 
        $SelectText .= ", " . substr($UploadRec['upload_ts'], 0, 19);
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
      $url = Traceback_uri() . "?mod=upload_permissions&upload=$upload_pk&permupk={$PermRow['perm_upload_pk']}&group=";
      $onchange = "onchange=\"js_url(this.value, '$url')\"";
      $V .= Array2SingleSelect($GroupArray, "groupselect", $PermRow['group_pk'], false, false, $onchange);
      $V .= "</td>";
      $V .= "<td>";  // permission
      $url = Traceback_uri() . "?mod=upload_permissions&upload=$upload_pk&permupk={$PermRow['perm_upload_pk']}&perm=";
      $onchange = "onchange=\"js_url(this.value, '$url')\"";
      $V .= Array2SingleSelect($PERM_NAMES, "permselect", $PermRow['perm'], false, false, $onchange);
      $V .= "</td>";
      $V .= "</tr>";
    }
    /* Print one extra row for adding perms */
    $V .= "<tr>";
    $V .= "<td>";  // group
    $url = Traceback_uri() . "?mod=upload_permissions&upload=$upload_pk&newperm={$PermRow['perm_upload_pk']}&newgroup=";
    $onchange = "onchange=\"js_url(this.value, '$url')\"";
    $V .= Array2SingleSelect($GroupArray, "groupselectnew", "None", true, true, $onchange);
    $V .= "</td>";
    $V .= "<td>";  // permission
    $url = Traceback_uri() . "?mod=upload_permissions&upload=$upload_pk&newgroup={$PermRow['group_pk']}&newperm=";
    $onchange = "onchange=\"js_url(this.value, '$url')\"";
    $V .= Array2SingleSelect($PERM_NAMES, "permselectnew", "", true, false, $onchange);
    $V .= "</td>";
    $V .= "</tr>";

    $V .= "</table>";

    $text = _("All upload permissions take place immediatly when a value is changed.  There is no submit button.");
    $V .= "<p>" . $text;
    $text = _("Add new groups on the last line.");
    $V .= "<br>" . $text;
/*
    $text = _("Submit");
    $V.= "<p><input type='submit' value='$text!'>\n";
    $V.= "</form>\n";
*/

    if (!$this->OutputToStdout) return ($V);
    print ("$V");
    return;
  }
}
$NewPlugin = new upload_permissions;
?>
