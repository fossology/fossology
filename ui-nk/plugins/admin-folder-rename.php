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

/*************************************************
 Restrict usage: Every PHP file should have this
 at the very beginning.
 This prevents hacking attempts.
 *************************************************/
global $GlobalReady;
if (!isset($GlobalReady)) { exit; }

class folder_rename extends Plugin
  {
  var $Type=PLUGIN_UI;
  var $Name="folder_rename";
  var $Version="1.0";
  var $MenuList="Admin::Folder::Rename";
  var $Dependency=array("db");

  /*********************************************
   Rename(): Given a folder's ID and a name, rename
   the folder!  Includes idiot checking since the
   input comes from stdin.
   Returns: 1 if renamed, 0 if failed.
   *********************************************/
  function Rename($FolderId,$NewName)
    {
    global $Plugins;
    $DB = &$Plugins[plugin_find_id("db")];

    /* Check the name */
    $NewName = trim($NewName);
    if (empty($NewName)) { return(0); }

    /* Make sure the folder exists */
    $Results = $DB->Action("SELECT * FROM folder where folder_pk = '$FolderId';");
    $Row = $Results[0];
    if ($Row['folder_pk'] != $FolderId) { return(0); }
    if ($Row['folder_name'] == $NewName) { return(0); } // don't rename the same thing

    /* Do the rename */
    /** Block SQL injection by protecting single quotes **/
    $NewName = str_replace("'", "''", $NewName);  // PostgreSQL quoting
    $Sql = "UPDATE folder SET folder_name = '" . $NewName . "' WHERE folder_pk = '$FolderId';";
    $Results = $DB->Action($Sql);
    return(1);
    } // Rename()

  /*********************************************
   Output(): Generate the text for this plugin.
   *********************************************/
  function Output()
    {
    if ($this->State != PLUGIN_STATE_READY) { return; }
    $V="";
    global $Plugins;
    switch($this->OutputType)
      {
      case "XML":
	break;
      case "HTML":
	$V .= "<H1>Rename Folder</H1>\n";

	/* If this is a POST, then process the request. */
	$OldFolderId = GetParm('oldfolderid',PARM_INTEGER);
	$NewName = GetParm('newname',PARM_TEXT);
	if (!empty($OldFolderId) && !empty($NewName))
	  {
	  $rc = $this->Rename($OldFolderId,$NewName);
	  if ($rc==1)
	    {
	    /* Need to refresh the screen */
	    $V .= "<script language='javascript'>\n";
	    $V .= "alert('Folder named.')\n";
	    $Uri = Traceback_uri() . "?mod=refresh&remod=" . $this->Name;
	    $V .= "window.open('$Uri','_top');\n";
	    $V .= "</script>\n";
	    }
	  }

	/* Display the form */
	$F = &$Plugins[plugin_find_id("folders")];
	$V .= "<form method='post'>\n"; // no url = this url
	$V .= "<ol>\n";
	$V .= "<li>Select the folder to rename:  \n";
	$V .= "<select name='oldfolderid'>\n";
	$V .= FolderListOption(-1,0);
	$V .= "</select><P />\n";
	$V .= "<li>Enter the new name:  \n";
	$V .= "<INPUT type='text' name='newname' size=40 />\n";
	$V .= "</ol>\n";
	$V .= "<input type='submit' value='Rename!'>\n";
	$V .= "</form>\n";
	break;
      case "_Text":
	break;
      default:
	break;
      }
    if (!$this->OutputToStdout) { return($V); }
    print("$V");
    return;
    }

  };
$NewPlugin = new folder_rename;
$NewPlugin->Initialize();
?>
