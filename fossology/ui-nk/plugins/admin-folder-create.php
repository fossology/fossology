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
if (!isset($GlobalReady)) { exit; }

class folder_create extends Plugin
  {
  var $Type=PLUGIN_UI;
  var $Name="folder_create";
  public $Title = "Create a new Fossology folder";
  var $Version="1.0";
  var $MenuList="Organize::Folders::Create";
  var $Dependency=array("db");

  /*********************************************
   Create(): Given a parent folder ID, a name and description,
   create the named folder under the parent.
   Includes idiot checking since the input comes from stdin.
   Returns: 1 if created, 0 if failed.
   *********************************************/
  function Create($ParentId, $NewFolder, $Desc)
    {
    global $Plugins;
    global $_pg_conn, $DATADIR, $PROJECT, $WEBDIR;
    global $DB;
    
    // Needed for the Action method
    // below is needed for the createfolder call
    $path = "{$DATADIR}/dbconnect/{$PROJECT}";
    db_init($path);
    if (!$_pg_conn) {
      echo "<pre>ERROR: could not connect to DateBase\n</pre>";
      exit(1);
    }
    
    /* Check the name */
    $NewFolder = trim($NewFolder);
    if (empty($NewFolder)) { return(0); }

    /* Make sure the parent folder exists */
    $Results = $DB->Action("SELECT * FROM folder WHERE folder_pk = '$ParentId';");
    $Row = $Results[0];
    if ($Row['folder_pk'] != $ParentId) { return(0); }
    // folder name exists under the parent?
    $Sql = "SELECT * FROM leftnav WHERE parent = '$ParentId' AND foldercontents_mode = '1';";
    $Results = $DB->Action("SELECT * FROM folder WHERE folder_pk = '$ParentId';");
    $Row = $Results[0];
    if ($Row['name'] == $NewFolder) { return(0); }

    /* Create the folder 
     ** Block SQL injection by protecting single quotes 
     *
     * Protect the folder name with htmlentities.
     */
    $NewFolder = str_replace("'", "''", $NewFolder);  // PostgreSQL quoting
    $NewFolder = htmlentities($NewFolder);            // for a clean display
    $Desc = str_replace("'", "''", $Desc);            // PostgreSQL quoting
    $fc_pk = createfolder($ParentId, $NewFolder, $Desc);
    if (!isset($fc_pk)) {
      return(0);
    }
    else {
      return(1);
    }
    } // Create()

  /*********************************************
   Output(): Generate the text for this plugin.
   *********************************************/
  function Output()
    {
    if ($this->State != PLUGIN_STATE_READY) { return; }
    $V="";
    switch($this->OutputType)
      {
      case "XML":
	break;
      case "HTML":
	$V .= "<H1>$this->Title</H1>\n";
	/* If this is a POST, then process the request. */
	$ParentId = GetParm('parentid',PARM_INTEGER);
	$NewFolder = GetParm('newname',PARM_TEXT);
	$Desc = GetParm('description',PARM_TEXT);
	if (!empty($ParentId) && !empty($NewFolder))
	  {
	  $rc = $this->Create($ParentId, $NewFolder, $Desc);
	  if ($rc==1)
	    {
	    /* Need to refresh the screen */
	    $V .= "<script language='javascript'>\n";
	    $V .= "alert('Folder $NewFolder Created')\n";
	    $Uri = Traceback_uri() . "?mod=refresh&remod=" . $this->Name;
	    $V .= "window.open('$Uri','_top');\n";
	    $V .= "</script>\n";
	    }
	  }
	/* Display the form */
	$V .= "<form method='post'>\n"; // no url = this url
	$V .= "<ol>\n";
	$V .= "<li>Select the parent folder:  \n";
	$V .= "<select name='parentid'>\n";
	$V .= FolderListOption(-1,0);
	$V .= "</select><P />\n";
	$V .= "<li>Enter the new folder name:  \n";
	$V .= "<INPUT type='text' name='newname' size=40 />\n<br>";
	$V .= "<br><li>Enter a meaningful description:  \n";
	$V .= "<INPUT type='text' name='description' size=80 />\n";
	$V .= "</ol>\n";
	$V .= "<input type='submit' value='Create!'>\n";
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
$NewPlugin = new folder_create;
$NewPlugin->Initialize();
?>
