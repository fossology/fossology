<?php
/***********************************************************
 Copyright (C) 2008-2012 Hewlett-Packard Development Company, L.P.

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

define("TITLE_folder_properties", _("Edit Folder Properties"));

class folder_properties extends FO_Plugin {
  function __construct()
  {
    $this->Name = "folder_properties";
    $this->Title = TITLE_folder_properties;
    $this->MenuList = "Organize::Folders::Edit Properties";
    $this->Dependency = array();
    $this->DBaccess = PLUGIN_DB_WRITE;
    parent::__construct();
  }

  /**
   * \brief Given a folder's ID and a name, alter
   * the folder properties.
   * Includes idiot checking since the input comes from stdin.
   * \return 1 if changed, 0 if failed.
   */
  function Edit($FolderId, $NewName, $NewDesc) {
    global $Plugins;
    global $PG_CONN;
    $sql = "SELECT * FROM folder where folder_pk = '$FolderId';"; 
    $result = pg_query($PG_CONN, $sql);
    DBCheckResult($result, $sql, __FILE__, __LINE__);
    $Row = pg_fetch_assoc($result);
    pg_free_result($result);
    /* If the folder does not exist. */
    if ($Row['folder_pk'] != $FolderId) {
      return (0);
    }
    $NewName = trim($NewName);
    if (!empty($FolderId)) {
      // Reuse the old name if no new name was given
      if (empty($NewName)) {
        $NewName = $Row['folder_name'];
      }
      // Reuse the old description if no new description was given
      if (empty($NewDesc)) {
        $NewDesc = $Row['folder_desc'];
      }
    }
    else {
      return (0); // $FolderId is empty

    }
    /* Change the properties */
    /** Block SQL injection by protecting single quotes **/
    $NewFolder = "";
    $NewName = str_replace("'", "''", $NewName); // PostgreSQL quoting
    $NewFolder = htmlentities($NewFolder); // for a clean display
    $NewDesc = str_replace("'", "''", $NewDesc); // PostgreSQL quoting
    $Sql = "UPDATE folder SET folder_name = '$NewName', folder_desc = '$NewDesc'
         WHERE folder_pk = '$FolderId';";
    $result = pg_query($PG_CONN, $Sql);
    DBCheckResult($result, $Sql, __FILE__, __LINE__);
    pg_free_result($result);
    return (1);
  }

  /**
   * \brief Generate the text for this plugin.
   */
  public function Output() {
    $V = "";
    global $PG_CONN;

    /* If this is a POST, then process the request. */
    $FolderSelectId = GetParm('selectfolderid', PARM_INTEGER);
    if (empty($FolderSelectId)) {
      $FolderSelectId = FolderGetTop();
    }
    $FolderId = GetParm('oldfolderid', PARM_INTEGER);
    $NewName = GetParm('newname', PARM_TEXT);
    $NewDesc = GetParm('newdesc', PARM_TEXT);
    if (!empty($FolderId)) {
      $FolderSelectId = $FolderId;
      $rc = $this->Edit($FolderId, $NewName, $NewDesc);
      if ($rc == 1) {
        /* Need to refresh the screen */
        $text=_("Folder Properties changed");
        $V.= displayMessage($text);
      }
    }
    $V.= _("<p>The folder properties that can be changed are the folder name and
       description.  First select the folder to edit. Then enter the new values.
       If no value is entered, then the corresponding field will not be changed.</p>");
    /* Get the folder info */
    $sql = "SELECT * FROM folder WHERE folder_pk = '$FolderSelectId';";
    $result = pg_query($PG_CONN, $sql);
    DBCheckResult($result, $sql, __FILE__, __LINE__);
    $Folder = pg_fetch_assoc($result);
    pg_free_result($result);
    /* Display the form */
    $V.= "<form method='post'>\n"; // no url = this url
    $V.= "<ol>\n";
    $text = _("Select the folder to edit:  \n");
    $V.= "<li>$text";
    $Uri = Traceback_uri() . "?mod=" . $this->Name . "&selectfolderid=";
    $V.= "<select name='oldfolderid' onChange='window.location.href=\"$Uri\" + this.value'>\n";
    $V.= FolderListOption(-1, 0, 1, $FolderSelectId);
    $V.= "</select><P />\n";
    $text = _("Change folder name:  \n");
    $V.= "<li>$text";
    $V.= "<INPUT type='text' name='newname' size=40 value=\"" . htmlentities($Folder['folder_name'], ENT_COMPAT) . "\" />\n";
    $text = _("Change folder description: \n");
    $V.= "<P /><li>$text";
    $V.= "<INPUT type='text' name='newdesc' size=60 value=\"" . htmlentities($Folder['folder_desc'], ENT_COMPAT) . "\" />\n";
    $V.= "</ol>\n";
    $text = _("Edit");
    $V.= "<input type='submit' value='$text!'>\n";
    $V.= "</form>\n";
    return $V;
  }
}
$NewPlugin = new folder_properties;