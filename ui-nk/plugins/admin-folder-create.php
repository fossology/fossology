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
  public $Type       = PLUGIN_UI;
  public $Name       = "folder_create";
  public $Title      = "Create a new Fossology folder";
  public $Version    = "1.0";
  public $MenuList   = "Organize::Folders::Create";
  public $Dependency = array("db");

  /**
   * Create(): Given a parent folder ID, a name and description,
   * create the named folder under the parent.
   *
   * Includes idiot checking since the input comes from stdin.
   * 
   * @param int    $ParentId
   * @param string $NewFolder
   * @param string $Desc
   * @return 1 if created, 0 if failed
   */
  function Create($ParentId, $NewFolder, $Desc)
  {
    global $Plugins;
    global $DB;

    /* Check the name */
    $NewFolder = trim($NewFolder);
    if (empty($NewFolder)) { return(0); }

    /* Make sure the parent folder exists */
    $Results = $DB->Action("SELECT * FROM folder WHERE folder_pk = '$ParentId';");
    $Row = $Results[0];
    if ($Row['folder_pk'] != $ParentId) { return(0); }

    // folder name exists under the parent?
    $Sql = "SELECT * FROM leftnav WHERE name = '$NewFolder' AND 
    			parent = '$ParentId' AND foldercontents_mode = '1';";
    $Results = $DB->Action($Sql);
    if ($Results[0]['name'] == $NewFolder) { return(0); } 

    /* Create the folder
     * Block SQL injection by protecting single quotes
     *
     * Protect the folder name with htmlentities.
     */
    $NewFolder = str_replace("'", "''", $NewFolder);  // PostgreSQL quoting
    $Desc = str_replace("'", "''", $Desc);            // PostgreSQL quoting
    $DB->Action("INSERT INTO folder (folder_name,folder_desc) VALUES ('$NewFolder','$Desc');");
    $Results = $DB->Action("SELECT folder_pk FROM folder WHERE folder_name='$NewFolder' AND folder_desc = '$Desc';");
    $FolderPk = $Results[0]['folder_pk'];
    if (empty($FolderPk)) { return(0); }

    $DB->Action("INSERT INTO foldercontents (parent_fk,foldercontents_mode,child_id) VALUES ('$ParentId','1','$FolderPk');");
    return(1);
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
