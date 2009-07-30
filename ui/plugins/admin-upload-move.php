<?php
/***********************************************************
Copyright (C) 2008 Hewlett-Packard Development Company, L.P.

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
 * @Version "$Id: admin-folder-move.php 231 2008-02-28 22:58:10Z nealk2 $"
 */
/*************************************************
Restrict usage: Every PHP file should have this
at the very beginning.
This prevents hacking attempts.
*************************************************/
global $GlobalReady;
if (!isset($GlobalReady)) {
  exit;
}
class upload_move extends FO_Plugin {
  var $Name = "upload_move";
  var $Version = "1.0";
  var $MenuList = "Organize::Uploads::Move";
  var $Dependency = array(
    "db"
  );
  var $DBaccess = PLUGIN_DB_WRITE;
  /*********************************************
  Move(): Given an uploadID, it's parent folder and a Target folder Id, move
  the upload from the old folder to the NewParentID (new folder)!
  Includes idiot checking since the input comes from stdin.
  Returns: 1 if renamed, 0 if failed.
  *********************************************/
  function Move($UploadId, $NewParentId, $OldParentId) {
    global $Plugins;
    global $DB;
    /* Check the name */
    if (empty($NewParentId)) {
      return (0);
    }
    if ($FolderId == $NewParentId) {
      return (0);
    } // already there
    if ($FolderId == FolderGetTop()) {
      return (0);
    } // cannot move folder root
    /* New folder must exist? */
    /* Old folder and uploadId will be checked by select from foldercontents */
    $Results = $DB->Action("SELECT * FROM folder where folder_pk = '$NewParentId' limit 1;");
    $Row = $Results[0];
    if ($Row['folder_pk'] != $NewParentId) {
      return (0);
    }
    /* Do the move */
    /* get the foldercontents record for the old folder and this upload */
    $Sql = "SELECT * from foldercontents WHERE child_id = '$UploadId' AND parent_fk=$OldParentId AND foldercontents_mode = '2' limit 1;";
    $FContents = $DB->Action($Sql);
    $Row = $FContents[0];
    $fc_pk = $Row['foldercontents_pk'];
    /* Now change the parent folder in this rec */
    $Sql = "UPDATE foldercontents SET parent_fk = '$NewParentId' WHERE foldercontents_pk=$fc_pk";
    $Results = $DB->Action($Sql);
    return (1);
  } // Move()
  /*********************************************
  Output(): Generate the text for this plugin.
  *********************************************/
  function Output() {
    global $Plugins;
    global $DB;
    if ($this->State != PLUGIN_STATE_READY) {
      return;
    }
    $V = "";
    switch ($this->OutputType) {
      case "XML":
      break;
      case "HTML":
        $V.= "<H2>Move upload to different folder.</H1>\n";
        /* If this is a POST, then process the request. */
        $OldFolderId = GetParm('oldfolderid', PARM_INTEGER);
        $UploadId = GetParm('uploadid', PARM_INTEGER);
        $TargetFolderId = GetParm('targetfolderid', PARM_INTEGER);
        if (!empty($OldFolderId) && !empty($TargetFolderId)) {
          $rc = $this->Move($UploadId, $TargetFolderId, $OldFolderId);
          if ($rc == 1) {
            /* Need to refresh the screen */
            $NewFolder = $DB->Action("SELECT * FROM folder where folder_pk = '$TargetFolderId';");
            $NRow = $NewFolder[0];
            $Sql = "SELECT pfile_fk FROM upload WHERE upload_pk='$UploadId';";
            $uploadData = $DB->Action($Sql);
            $pfileNum  = $uploadData[0]['pfile_fk'];
            $Sql = "SELECT ufile_name FROM uploadtree WHERE " .
                   "upload_fk='$UploadId' and pfile_fk=$pfileNum;";
            $Uploads = $DB->Action($Sql);
            $base = basename($Uploads[0]['ufile_name']);
            $OldFolder = $DB->Action("SELECT * FROM folder where folder_pk = '$OldFolderId';");
            $ORow = $OldFolder[0];
            $success = "Moved $base from folder $ORow[folder_name] to folder $NRow[folder_name]";
            $V.= displayMessage($success);
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
        $V.= "    document.formy.uploadid.innerHTML = Uploads.responseText;\n";
        /* Add new options */
        $V.= "    }\n";
        $V.= "  }\n";
        $V.= "</script>\n";
        /* Build the  HTML form */
        $V.= "<form name='formy' method='post'>\n"; // no url = this url
        /* Display the form */
        $V.= "<form method='post'>\n"; // no url = this url
        $V.= "<ol>\n";
        $V.= "<li>Select the folder containing the upload you wish to move:  \n";
        $V.= "<select name='oldfolderid'\n";
        $V.= "onLoad='Uploads_Get((\"" . Traceback_uri() . "?mod=upload_options&folder=-1' ";
        $V.= "onChange='Uploads_Get(\"" . Traceback_uri() . "?mod=upload_options&folder=\" + this.value)'>\n";
        $V.= FolderListOption(-1, 0);
        $V.= "</select><P />\n";
        $V.= "<li>Select the upload you wish to move:  \n";
        $V.= "<select name='uploadid'>\n";
        $List = FolderListUploads(-1);
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
        $V.= "<li>Select the destination folder:  \n";
        $V.= "<select name='targetfolderid'>\n";
        $V.= FolderListOption(-1, 0);
        $V.= "</select><P />\n";
        $V.= "</ol>\n";
        $V.= "<input type='submit' value='Move!'>\n";
        $V.= "</form>\n";
      break;
      case "Text":
      break;
      default:
      break;
    }
    if (!$this->OutputToStdout) {
      return ($V);
    }
    print ("$V");
    return;
  }
};
$NewPlugin = new upload_move;
?>
