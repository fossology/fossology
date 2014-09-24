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
 * \class upload_move extend from FO_Plugin
 * \brief move a upload from a place to another one
 */
class upload_move extends FO_Plugin {
  function __construct()
  {
    $this->Name = "upload_move";
    $this->MenuList = "Organize::Uploads::Move";
    $this->DBaccess = PLUGIN_DB_WRITE;
    $this->Title = "Move upload to different folder";
    parent::__construct();
  }

  /**
   * \brief Given an uploadID, it's parent folder and a Target folder Id, move
   * the upload from the old folder to the NewParentID (new folder)!
   * Includes idiot checking since the input comes from stdin.
   *
   * \return 1 if renamed, 0 if failed.
   */
  function Move($UploadId, $NewParentId, $OldParentId) {
    global $Plugins;
    global $PG_CONN;
    /* Check the name */
    if (empty($NewParentId)) {
      return (0);
    }
    if ($OldParentId == $NewParentId) {
      return (0);
    } // already there
    /* New folder must exist? */
    /* Old folder and uploadId will be checked by select from foldercontents */
    $sql = "SELECT * FROM folder where folder_pk = '$NewParentId' limit 1;";
    $result = pg_query($PG_CONN, $sql);
    DBCheckResult($result, $sql, __FILE__, __LINE__);
    $row = pg_fetch_assoc($result);
    pg_free_result($result);
    if ($row['folder_pk'] != $NewParentId) {
      return (0);
    }
    /* Do the move */
    /* get the foldercontents record for the old folder and this upload */
    $sql = "SELECT * from foldercontents WHERE child_id = '$UploadId' AND parent_fk=$OldParentId AND foldercontents_mode = '2' limit 1;";
    $result = pg_query($PG_CONN, $sql);
    DBCheckResult($result, $sql, __FILE__, __LINE__);
    $row = pg_fetch_assoc($result);
    pg_free_result($result);
    $fc_pk = $row['foldercontents_pk'];
    /* Now change the parent folder in this rec */
    $sql = "UPDATE foldercontents SET parent_fk = '$NewParentId' WHERE foldercontents_pk=$fc_pk";
    $result = pg_query($PG_CONN, $sql);
    DBCheckResult($result, $sql, __FILE__, __LINE__);
    $row = pg_fetch_assoc($result);
    pg_free_result($result);
    return (1);
  } // Move()

  /**
   * \brief Generate the text for this plugin.
   */
  protected function htmlContent() {
    global $PG_CONN;

    $V = "";
    $text = _("Move upload to different folder.");
    $V.= "<H2>$text</H1>\n";
    /* If this is a POST, then process the request. */
    $OldFolderId = GetParm('oldfolderid', PARM_INTEGER);
    $UploadId = GetParm('uploadid', PARM_INTEGER);
    $TargetFolderId = GetParm('targetfolderid', PARM_INTEGER);
    if (!empty($OldFolderId) && !empty($TargetFolderId)) 
    {
      /* check upload permission */
      $UploadPerm = GetUploadPerm($UploadId);
      if ($UploadPerm < PERM_WRITE)
      {
        $text = _("Permission Denied");
        echo "<h2>$text<h2>";
        return;
      }

      $rc = $this->Move($UploadId, $TargetFolderId, $OldFolderId);
      if ($rc == 1) {
        /* Need to refresh the screen */
        $sql =  "SELECT * FROM folder where folder_pk = '$TargetFolderId';";
        $result = pg_query($PG_CONN, $sql);
        DBCheckResult($result, $sql, __FILE__, __LINE__);
        $NRow = pg_fetch_assoc($result);
        pg_free_result($result);
        $sql = "SELECT pfile_fk FROM upload WHERE upload_pk='$UploadId';";
        $result = pg_query($PG_CONN, $sql);
        DBCheckResult($result, $sql, __FILE__, __LINE__);
        $row= pg_fetch_assoc($result);
        pg_free_result($result);
        $pfileNum  = $row['pfile_fk'];
        $sql = "SELECT ufile_name FROM uploadtree WHERE " .
               "upload_fk='$UploadId' and pfile_fk=$pfileNum;";
        $result = pg_query($PG_CONN, $sql);
        DBCheckResult($result, $sql, __FILE__, __LINE__);
        $row= pg_fetch_assoc($result);
        pg_free_result($result);
        $base = basename($row['ufile_name']);
        $sql = "SELECT * FROM folder where folder_pk = '$OldFolderId';";
        $result = pg_query($PG_CONN, $sql);
        DBCheckResult($result, $sql, __FILE__, __LINE__);
        $ORow = pg_fetch_assoc($result);
        pg_free_result($result);
        $text = _("Moved");
        $text1 = _("from folder");
        $text2 = _("to folder");
        $success = "$text $base $text1 $ORow[folder_name] $text2 $NRow[folder_name]";
        $this->vars['message'] = $success;
      }
    }
    /* Create the AJAX (Active HTTP) javascript for doing the reply
     and showing the response. */
    $V.= ActiveHTTPscript("Uploads");
    $V.= "<script language='javascript'>\n";
    $V.= "function Uploads_Reply()\n";
    $V.= "  {\n";
    $V.= "  if ((Uploads.readyState==4) && (Uploads.status==200))\n";
    $V.= "    {\n";
    /* Remove all options */
    $V.= "    document.getElementById('uploaddiv').innerHTML = '<select name=\'uploadid\'>' + Uploads.responseText + '</select><P />';\n";
    /* Add new options */
    $V.= "    }\n";
    $V.= "  }\n";
    $V.= "</script>\n";
    /* Build the  HTML form */
    $V.= "<form name='formy' method='post'>\n"; // no url = this url
    /* Display the form */
    $V.= "<form method='post'>\n"; // no url = this url
    $V.= "<ol>\n";
    $text = _("Select the folder containing the upload you wish to move:  \n");
    $V.= "<li>$text";
    $V.= "<select name='oldfolderid'\n";
    $V.= "onLoad='Uploads_Get((\"" . Traceback_uri() . "?mod=upload_options&folder=-1' ";
    $V.= "onChange='Uploads_Get(\"" . Traceback_uri() . "?mod=upload_options&folder=\" + this.value)'>\n";

    $root_folder_pk = GetUserRootFolder();
    $V.= FolderListOption($root_folder_pk, 0);
    $V.= "</select><P />\n";
    $text = _("Select the upload you wish to move:  \n");
    $V.= "<li>$text";
    $V.= "<div id='uploaddiv'>\n";
    $V.= "<select name='uploadid'>\n";
    $List = FolderListUploads_perm($root_folder_pk, PERM_WRITE);
    foreach($List as $L) {
      $V.= "<option value='" . $L['upload_pk'] . "'>";
      $V.= htmlentities($L['name']);
      if (!empty($L['upload_desc'])) {
        $V.= " (" . htmlentities($L['upload_desc']) . ")";
      }
      if (!empty($L['upload_ts'])) {
        $V.= " :: " . substr($L['upload_ts'], 0, 19);
      }
      $V.= "</option>\n";
    }
    $V.= "</select><P />\n";
    $V.= "</div>\n";
    $text = _("Select the destination folder:  \n");
    $V.= "<li>$text";
    $V.= "<select name='targetfolderid'>\n";
    $V.= FolderListOption($root_folder_pk, 0);
    $V.= "</select><P />\n";
    $V.= "</ol>\n";
    $text = ("Move");
    $V.= "<input type='submit' value='$text!'>\n";
    $V.= "</form>\n";

    return $V;
  }
}
$NewPlugin = new upload_move;
