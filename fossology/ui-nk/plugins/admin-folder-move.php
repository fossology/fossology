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
 * @Version "$Id$"
 */

/*************************************************
 Restrict usage: Every PHP file should have this
 at the very beginning.
 This prevents hacking attempts.
 *************************************************/
global $GlobalReady;
if (!isset($GlobalReady)) { exit; }

class folder_move extends Plugin
  {
  var $Name       ="folder_move";
  var $Version    ="1.0";
  var $MenuList   ="Organize::Folders::Move";
  var $Dependency =array("db");
  var $DBaccess   = PLUGIN_DB_WRITE;

  /*********************************************
   Move(): Given a folder's ID and a TargetId, move
   the folder from the old patent to the TargetId!  
	Includes idiot checking since the input comes from stdin.

   Returns: 1 if renamed, 0 if failed.
   *********************************************/
  function Move($FolderId,$NewParentId)
    {
    global $Plugins;
    global $DB;

    /* Check the name */
    if (empty($NewParentId)) { return(0); }
    if ($FolderId == $NewParentId) { return(0); }   // already there
    if ($FolderId == FolderGetTop()) { return(0); } // cannot move folder root

    /* Both folders must exist */
    $Results = $DB->Action("SELECT * FROM folder where folder_pk = '$FolderId';");
    $Row = $Results[0];
    if ($Row['folder_pk'] != $FolderId) { return(0); }
    /* Second folder exist? */
    $Results = $DB->Action("SELECT * FROM folder where folder_pk = '$NewParentId';");
    $Row = $Results[0];
    if ($Row['folder_pk'] != $NewParentId) { return(0); }
    
    /* Do the move */
    $Sql = "SELECT * from foldercontents WHERE child_id = '$FolderId' AND foldercontents_mode = '1';";
    $FContents = $DB->Action($Sql);
    $Row = $FContents[0];
    $fc_pk = $Row['foldercontents_pk'];
    //echo ("<pre>\$Sql = UPDATE foldercontents SET parent_fk = '$NewParentId' WHERE child_id = '$FolderId ' AND foldercontents_pk = '$fc_pk' AND foldercontents_mode = '1'\n</pre>");
    $Sql = "UPDATE foldercontents SET parent_fk = '$NewParentId' WHERE child_id = '$FolderId ' AND foldercontents_pk = '$fc_pk' AND foldercontents_mode = '1'";
    $Results = $DB->Action($Sql);
    return(1);
    } // Move()

  /*********************************************
   Output(): Generate the text for this plugin.
   *********************************************/
  function Output()
    {
    if ($this->State != PLUGIN_STATE_READY) { return; }
    $V="";
    global $Plugins;
    global $DB;
    switch($this->OutputType)
      {
      case "XML":
	break;
      case "HTML":
	$V .= "<H1>Move Folder</H1>\n";

	/* If this is a POST, then process the request. */
	$OldFolderId = GetParm('oldfolderid',PARM_INTEGER);
	$TargetFolderId = GetParm('targetfolderid',PARM_INTEGER);
	if (!empty($OldFolderId) && !empty($TargetFolderId))
	  {
	  $rc = $this->Move($OldFolderId,$TargetFolderId);
	  if ($rc==1)
	    {
	   /* Need to refresh the screen */
	    $NewFolder = $DB->Action("SELECT * FROM folder where folder_pk = '$TargetFolderId';");
	    $NRow = $NewFolder[0];
	    $OldFolder = $DB->Action("SELECT * FROM folder where folder_pk = '$OldFolderId';");
	    $ORow = $OldFolder[0];
	    $success = "Moved folder " . $ORow['folder_name'] . " to folder " . $NRow['folder_name']; 
	    $V .= "<script language='javascript'>\n";
	    $V .= "alert('$success')\n";
	    $Uri = Traceback_uri() . "?mod=refresh&remod=" . $this->Name;
	    $V .= "window.open('$Uri','_top');\n";
	    $V .= "</script>\n";
	    }
	  }
	/* Display the form */ 
	$V .= "<form method='post'>\n"; // no url = this url
	$V .= "<ol>\n";
	$V .= "<li>Select the source folder to move:  \n";
	$V .= "<select name='oldfolderid'>\n";
	$V .= FolderListOption(-1,0,0);
	$V .= "</select><P />\n";
	$V .= "<li>Select the destination folder:  \n";
	$V .= "<select name='targetfolderid'>\n";
	$V .= FolderListOption(-1,0);
	$V .= "</select><P />\n";
	$V .= "</ol>\n";
	$V .= "<input type='submit' value='Move!'>\n";
	$V .= "</form>\n";
	break;
      case "Text":
	break;
      default:
	break;
      }
    if (!$this->OutputToStdout) { return($V); }
    print("$V");
    return;
    }

  };
$NewPlugin = new folder_move;
$NewPlugin->Initialize();
?>
