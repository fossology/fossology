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
 * @version "$Id: $"
 */

/*************************************************
 Restrict usage: Every PHP file should have this
 at the very beginning.
 This prevents hacking attempts.
 *************************************************/
global $GlobalReady;
if (!isset($GlobalReady)) { exit; }

class folder_properties extends Plugin
{
  var $Name       ="folder_properties";
  var $Version    ="1.0";
  var $MenuList   ="Organize::Folders::Edit Properties";
  var $Dependency =array("db");
  var $DBaccess   = PLUGIN_DB_WRITE;

  /*********************************************
   Edit(): Given a folder's ID and a name, alter
   the folder properties.
   Includes idiot checking since the input comes from stdin.
   Returns: 1 if changed, 0 if failed.
   *********************************************/
  function Edit($FolderId, $NewName, $NewDesc)
  {
    global $Plugins;
    global $DB;

    $Results = $DB->Action("SELECT * FROM folder where folder_pk = '$FolderId';");
    $Row = $Results[0];
    if ($Row['folder_pk'] != $FolderId) { return(0); }
    if ($Row['folder_name'] == $NewName) { return(0); } // don't rename the same thing
    // Make sure the user didn't just leave the root folder selected
    if ($FolderId == FolderGetTop()){
      echo '<span style="color: #CC0000; font-size:larger;"<p><strong>Please Selcect the Folder to Operate on</strong></p></span>';
      return(0);
    }
     
    $NewName = trim($NewName);
    if (!empty($FolderId)) {
      // Reuse the old name if no new name was given
      if(empty($NewName)){
        $NewName = $Row['folder_name'];
      }
      // Reuse the old description if no new description was given
      if(empty($NewDesc)){
        $NewDesc = $Row['folder_desc'];
      }
    }
    else {
      return(0);    // $FolderId is empty
    }
    /* Change the properties */
    /** Block SQL injection by protecting single quotes **/
    $NewName = str_replace("'", "''", $NewName);      // PostgreSQL quoting
    $NewFolder = htmlentities($NewFolder);            // for a clean display
    $NewDesc = str_replace("'", "''", $NewDesc);            // PostgreSQL quoting
    $Sql = "UPDATE folder SET folder_name = '$NewName', folder_desc = '$NewDesc'
    		   WHERE folder_pk = '$FolderId';";
    $Results = $DB->Action($Sql);
    return(1);
  } // Edit()

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
        $V .= "<H1>Edit Folder Properties</H1>\n";
        /* If this is a POST, then process the request. */
        $FolderId = GetParm('oldfolderid',PARM_INTEGER);
        $NewName = GetParm('newname',PARM_TEXT);
        $NewDesc = GetParm('newdesc',PARM_TEXT);

        if (!empty($FolderId)) {
          $rc = $this->Edit($FolderId, $NewName, $NewDesc);
          if ($rc==1){
            /* Need to refresh the screen */
            $V .= "<script language='javascript'>\n";
            $V .= "alert('Folder Properties changed')\n";
            $Uri = Traceback_uri() . "?mod=refresh&remod=" . $this->Name;
            $V .= "window.open('$Uri','_top');\n";
            $V .= "</script>\n";
          }
        }
        
        $V .= "<p>The folder properties that can be changed are the folder name and
			 description.  First select the folder to edit. Then enter the new values.
			 If no value is entered, then the corresponding field will not be changed.</p>";
         
        /* Display the form */
        $V .= "<form method='post'>\n"; // no url = this url
        $V .= "<ol>\n";
        $V .= "<li>Select the folder to edit:  \n";
        $V .= "<select name='oldfolderid'>\n";
        $V .= FolderListOption(-1,0);
        $V .= "</select><P />\n";
        $V .= "<li>Change folder name:  \n";
        $V .= "<INPUT type='text' name='newname' size=40 />\n";
        $V .= "<P /><li>Change folder description:  \n";
        $V .= "<INPUT type='text' name='newdesc' size=60 />\n";
        $V .= "</ol>\n";
        $V .= "<input type='submit' value='Edit!'>\n";
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
$NewPlugin = new folder_properties;
$NewPlugin->Initialize();
?>
