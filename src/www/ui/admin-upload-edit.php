<?php
/***********************************************************
 Copyright (C) 2008-2013 Hewlett-Packard Development Company, L.P.

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
 * @file admin-upload-edit.php
 * @brief edit upload file properties
 **/

define("TITLE_upload_properties", _("Edit Uploaded File Properties"));

class upload_properties extends FO_Plugin 
{
  var $Name = "upload_properties";
  public $Title = TITLE_upload_properties;
  var $Version = "1.0";
  var $MenuList = "Organize::Uploads::Edit Properties";
  var $Dependency = array();
  var $DBaccess = PLUGIN_DB_WRITE;

  /**
   * @brief Update upload properties (name and description)
   *
   * @param $uploadId upload.upload_pk of record to update
   * @param $NewName New upload.upload_filename, and uploadtree.ufle_name
   *        If null, old value is not changed.
   * @param $NewDesc New upload description (upload.upload_desc)
   *        If null, old value is not changed.
   *
   * @return 1 if the upload record is updated, 0 if not, 2 if no inputs
   **/
  function UpdateUploadProperties($uploadId, $NewName, $NewDesc) 
  {
    global $PG_CONN;

    if (empty($NewName) and empty($NewDesc)) return 2; // nothing to do 

    if (!empty($NewName)) 
    {
      $NewName = pg_escape_string(trim($NewName));
      /* Use pfile_fk to select the correct entry in the upload tree, artifacts
       * (e.g. directories of the upload do not have pfiles).
       */
      $sql = "SELECT pfile_fk FROM upload WHERE upload_pk=$uploadId";
      $result = pg_query($PG_CONN, $sql);
      DBCheckResult($result, $sql, __FILE__, __LINE__);
      if (pg_num_rows($result) == 0)
      {
        pg_free_result($result);
        return 0;
      }

      $row = pg_fetch_assoc($result);
      $pfileFk = $row['pfile_fk'];
      pg_free_result($result);

      /* Always keep uploadtree.ufile_name and upload.upload_filename in sync */
      $sql = "UPDATE uploadtree SET ufile_name='$NewName' WHERE upload_fk=$uploadId AND pfile_fk=$pfileFk";
      $result = pg_query($PG_CONN, $sql);
      DBCheckResult($result, $sql, __FILE__, __LINE__);
      pg_free_result($result);

      $sql = "UPDATE upload SET upload_filename='$NewName' WHERE upload_pk=$uploadId AND pfile_fk=$pfileFk";
      $result = pg_query($PG_CONN, $sql);
      DBCheckResult($result, $sql, __FILE__, __LINE__);
      pg_free_result($result);
    }

    if (!empty($NewDesc)) 
    {
      $NewDesc = pg_escape_string(trim($NewDesc));
      $sql = "UPDATE upload SET upload_desc='$NewDesc' WHERE upload_pk=$uploadId";
      $result = pg_query($PG_CONN, $sql);
      DBCheckResult($result, $sql, __FILE__, __LINE__);
      pg_free_result($result);
    }
    return 1;
  }

  /*********************************************
   Output(): Generate the text for this plugin.
   *********************************************/
  function Output() 
  {
    global $PG_CONN;
    if ($this->State != PLUGIN_STATE_READY)  return;

    $V = "";
    $folder_pk = GetParm('folder', PARM_TEXT);
    if (empty($folder_pk)) $folder_pk = FolderGetTop();

    $NewName = GetArrayVal("newname", $_POST);
    $NewDesc = GetArrayVal("newdesc", $_POST);
    $upload_pk = GetArrayVal("upload_pk", $_POST);
    if (empty($upload_pk)) $upload_pk = GetParm('upload', PARM_INTEGER);

    /* Check Upload permission */
    if (!empty($upload_pk))
    {
      $UploadPerm = GetUploadPerm($upload_pk);
      if ($UploadPerm < PERM_WRITE)
      {
        $text = _("Permission Denied");
        echo "<h2>$text<h2>";
        return;
      }
    }

    $rc = $this->UpdateUploadProperties($upload_pk, $NewName, $NewDesc);
    if($rc == 0)
    {
      $text = _("Nothing to Change");
      $V.= displayMessage($text);
    }
    else if($rc == 1)
    {
      $text = _("Upload Properties successfully changed");
      $V.= displayMessage($text);
    }

    /* define js_url */
    $V .= js_url(); 

    /* Build the HTML form */
    $V.= "<form name='formy' method='post'>\n"; // no url = this url
    $V.= "<ol>\n";
    $text = _("Select the folder that contains the upload:  \n");
    $V.= "<li>$text";

    // Get folder array folder_pk => folder_name
    $FolderArray = array();
    GetFolderArray($folder_pk, $FolderArray);

    /*** Display folder select list, on change request new page with folder= in url ***/
    $url = Traceback_uri() . "?mod=upload_properties&folder=";
    $onchange = "onchange=\"js_url(this.value, '$url')\"";
    $V .= Array2SingleSelect($FolderArray, "folderselect", $folder_pk, false, false, $onchange);

    /*** Display upload select list, on change, request new page with new upload= in url ***/
    $text = _("Select the upload you wish to edit:  \n");
    $V.= "<li>$text";

    // Get list of all upload records in this folder
    $UploadList = FolderListUploads_perm($folder_pk, PERM_WRITE);

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

    if ($upload_pk)
    {
      // case where upload is set in the URL
      $sql = "SELECT * FROM upload WHERE upload_pk = '$upload_pk'";
      $result = pg_query($PG_CONN, $sql);
      DBCheckResult($result, $sql, __FILE__, __LINE__);
      if (pg_num_rows($result) == 0)
      {
        /* Bad upload_pk */
        $text = _("Missing upload.");
        $V.= displayMessage($text);
        pg_free_result($result);
        return 0;
      }
      $UploadRec = pg_fetch_assoc($result);
      pg_free_result($result);
      $V.= "<INPUT type='hidden' name='upload_pk' value='$upload_pk' />\n";
    }
    else
    {
      // no uploads in the folder
      $UploadRec = array();
    }

    $url = Traceback_uri() . "?mod=upload_properties&folder=$folder_pk&upload=";
    $onchange = "onchange=\"js_url(this.value, '$url')\"";
    $V .= Array2SingleSelect($UploadArray, "uploadselect", $upload_pk, false, false, $onchange);

    /* Input upload_filename */
    $text = _("Upload name:  \n");
    $V.= "<li>$text";
    if(empty($UploadRec['upload_filename']))
      $upload_filename = "";
    else
      $upload_filename = htmlentities($UploadRec['upload_filename']);
    
    $V.= "<INPUT type='text' name='newname' size=40 value='$upload_filename' />\n";

    /* Input upload_desc */
    $text = _("Upload description:  \n");
    $V.= "<li>$text";
    if(empty($UploadRec['upload_desc']))
      $upload_desc = "";
    else
      $upload_desc = htmlentities($UploadRec['upload_desc'], ENT_QUOTES);
    
    $V.= "<INPUT type='text' name='newdesc' size=60 value='$upload_desc' />\n";

    $V.= "</ol>\n";
    $text = _("Edit");
    $V.= "<input type='submit' value='$text!'>\n";
    $V.= "</form>\n";

    if (!$this->OutputToStdout) return ($V);
    print ("$V");
    return;
  }
}
$NewPlugin = new upload_properties;
?>
