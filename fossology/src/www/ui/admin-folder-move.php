<?php
/***********************************************************
 Copyright (C) 2008-2011 Hewlett-Packard Development Company, L.P.

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

define("TITLE_folder_move", _("Move Folder"));

class folder_move extends FO_Plugin {
  var $Name = "folder_move";
  var $Title = TITLE_folder_move;
  var $Version = "1.0";
  var $MenuList = "Organize::Folders::Move";
  var $Dependency = array();
  var $DBaccess = PLUGIN_DB_WRITE;

  /**
   * \brief Given a folder's ID and a TargetId, move
   * the folder from the old patent to the TargetId!
   * Includes idiot checking since the input comes from stdin.
   * 
   * \return 1 if renamed, 0 if failed.
   */
  function Move($FolderId, $NewParentId) {
    global $Plugins;
    global $PG_CONN;
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
    /* Both folders must exist */
    $sql = "SELECT * FROM folder where folder_pk = '$FolderId';";
    $result = pg_query($PG_CONN, $sql);
    DBCheckResult($result, $sql, __FILE__, __LINE__);
    $Row = pg_fetch_assoc($result);
    if ($Row['folder_pk'] != $FolderId) {
      pg_free_result($result);
      return (0);
    }
    pg_free_result($result);
    /* Second folder exist? */
    $sql = "SELECT * FROM folder where folder_pk = '$NewParentId';";
    $result = pg_query($PG_CONN, $sql);
    DBCheckResult($result, $sql, __FILE__, __LINE__);
    $Row = pg_fetch_assoc($result);
    if ($Row['folder_pk'] != $NewParentId) {
      pg_free_result($result);
      return (0);
    }
    pg_free_result($result);

    /* Do the move */
    $Sql = "SELECT * from foldercontents WHERE child_id = '$FolderId' AND foldercontents_mode = '1';";
    $result = pg_query($PG_CONN, $Sql);
    DBCheckResult($result, $Sql, __FILE__, __LINE__);
    $Row = pg_fetch_assoc($result);
    $fc_pk = $Row['foldercontents_pk'];
    pg_free_result($result);
    $Sql = "UPDATE foldercontents SET parent_fk = '$NewParentId' WHERE child_id = '$FolderId ' AND foldercontents_pk = '$fc_pk' AND foldercontents_mode = '1'";
    $result = pg_query($PG_CONN, $Sql);
    DBCheckResult($result, $Sql, __FILE__, __LINE__);
    pg_free_result($result);
    return (1);
  } // Move()

  /**
   * \brief Generate the text for this plugin.
   */
  function Output() {
    if ($this->State != PLUGIN_STATE_READY) {
      return;
    }
    $V = "";
    global $Plugins;
    global $PG_CONN;
    switch ($this->OutputType) {
      case "XML":
        break;
      case "HTML":
        /* If this is a POST, then process the request. */
        $OldFolderId = GetParm('oldfolderid', PARM_INTEGER);
        $TargetFolderId = GetParm('targetfolderid', PARM_INTEGER);
        if (!empty($OldFolderId) && !empty($TargetFolderId)) {
          $rc = $this->Move($OldFolderId, $TargetFolderId);
          if ($rc == 1) {
            /* create sucess mesage */
            $sql = "SELECT * FROM folder where folder_pk = '$TargetFolderId';";
            $result = pg_query($PG_CONN, $sql);
            DBCheckResult($result, $sql, __FILE__, __LINE__);
            $NRow = pg_fetch_assoc($result);
            pg_free_result($result);
            $sql = "SELECT * FROM folder where folder_pk = '$OldFolderId';";
            $result = pg_query($PG_CONN, $sql);
            DBCheckResult($result, $sql, __FILE__, __LINE__);
            $ORow = pg_fetch_assoc($result);
            pg_free_result($result);
            $text=_("Moved folder ");
            $text1=_(" to folder ");
            $success = $text . $ORow['folder_name'] . $text1 . $NRow['folder_name'];
            $V.= displayMessage($success);
          }
          else {
            $text=_("Could not move folder!: ");
            $V.= displayMessage($text.$rc);
          }
        }
        /* Display the form */
        $V.= "<form method='post'>\n"; // no url = this url
        $V.= "<ol>\n";
        $text = _("Select the source folder to move:  \n");
        $V.= "<li>$text";
        $V.= "<select name='oldfolderid'>\n";
        $V.= FolderListOption(-1, 0, 0);
        $V.= "</select><P />\n";
        $text = _("Select the destination folder:  \n");
        $V.= "<li>$text";
        $V.= "<select name='targetfolderid'>\n";
        $V.= FolderListOption(-1, 0);
        $V.= "</select><P />\n";
        $V.= "</ol>\n";
        $text = _("Move");
        $V.= "<input type='submit' value='$text!'>\n";
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
$NewPlugin = new folder_move;
?>
