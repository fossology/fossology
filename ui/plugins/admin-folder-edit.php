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
 * @version "$Id$"
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
class folder_properties extends FO_Plugin {
  var $Name = "folder_properties";
  var $Title = "Edit Folder Properties";
  var $Version = "1.0";
  var $MenuList = "Organize::Folders::Edit Properties";
  var $Dependency = array(
    "db"
  );
  var $DBaccess = PLUGIN_DB_WRITE;
  /*********************************************
  Edit(): Given a folder's ID and a name, alter
  the folder properties.
  Includes idiot checking since the input comes from stdin.
  Returns: 1 if changed, 0 if failed.
  *********************************************/
  function Edit($FolderId, $NewName, $NewDesc) {
    global $Plugins;
    global $DB;
    $Results = $DB->Action("SELECT * FROM folder where folder_pk = '$FolderId';");
    $Row = $Results[0];
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
    $NewName = str_replace("'", "''", $NewName); // PostgreSQL quoting
    $NewFolder = htmlentities($NewFolder); // for a clean display
    $NewDesc = str_replace("'", "''", $NewDesc); // PostgreSQL quoting
    $Sql = "UPDATE folder SET folder_name = '$NewName', folder_desc = '$NewDesc'
    		   WHERE folder_pk = '$FolderId';";
    $Results = $DB->Action($Sql);
    return (1);
  } // Edit()
  /*********************************************
  Output(): Generate the text for this plugin.
  *********************************************/
  function Output() {
    if ($this->State != PLUGIN_STATE_READY) {
      return;
    }
    $V = "";
    global $DB;
    switch ($this->OutputType) {
      case "XML":
      break;
      case "HTML":
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
            $V.= displayMessage('Folder Properties changed');
          }
        }
        $V.= "<p>The folder properties that can be changed are the folder name and
			 description.  First select the folder to edit. Then enter the new values.
			 If no value is entered, then the corresponding field will not be changed.</p>";
        /* Get the folder info */
        $Results = $DB->Action("SELECT * FROM folder WHERE folder_pk = '$FolderSelectId';");
        $Folder = & $Results[0];
        /* Display the form */
        $V.= "<form method='post'>\n"; // no url = this url
        $V.= "<ol>\n";
        $V.= "<li>Select the folder to edit:  \n";
        $Uri = Traceback_uri() . "?mod=" . $this->Name . "&selectfolderid=";
        $V.= "<select name='oldfolderid' onChange='window.location.href=\"$Uri\" + this.value'>\n";
        $V.= FolderListOption(-1, 0, 1, $FolderSelectId);
        $V.= "</select><P />\n";
        $V.= "<li>Change folder name:  \n";
        $V.= "<INPUT type='text' name='newname' size=40 value=\"" . htmlentities($Folder['folder_name'], ENT_COMPAT) . "\" />\n";
        $V.= "<P /><li>Change folder description:  \n";
        $V.= "<INPUT type='text' name='newdesc' size=60 value=\"" . htmlentities($Folder['folder_desc'], ENT_COMPAT) . "\" />\n";
        $V.= "</ol>\n";
        $V.= "<input type='submit' value='Edit!'>\n";
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
$NewPlugin = new folder_properties;
?>
