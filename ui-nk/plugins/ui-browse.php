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

class ui_browse extends Plugin
  {
  var $Name="browse";
  var $Version="1.0";
  // var $MenuList="Tools::Browse";
  var $Dependency=array("db");

  /***********************************************************
   ShowFid(): Given a Folder_pk, list every upload in the folder.
   ***********************************************************/
  function ShowFid($Fid)
    {
    global $Plugins;
    $V="";
    $DB = &$Plugins[plugin_find_id("db")];
    $Sql = "SELECT * FROM upload WHERE upload_pk IN (SELECT child_id FROM foldercontents WHERE foldercontents_mode & 2 != 0 AND parent_fk = $Fid);";
    $Results = $DB->Action($Sql);
    $Uri = Traceback_uri() . "?mod=" . $this->Name;
    $V .= "<table class='text' border=0 width='100%' cellpadding=0>\n";
    $V .= "<tr><th>Upload Name and Description</th><th>Upload Date</th></tr>\n";
    foreach($Results as $Row)
      {
      if (empty($Row['upload_pk'])) { continue; }
      $Desc = htmlentities($Row['upload_desc']);
      if (empty($Desc)) { $Desc = "<i>No description</i>"; }
      $Sql = "SELECT ufile_name FROM ufile WHERE ufile_pk = " . $Row['ufile_fk'] . ";";
      $UResults = $DB->Action($Sql);
      $Name = $UResults[0]['ufile_name'];
      $V .= "<tr><td>";
      $V .= "<a href='$Uri&upload=" . $Row['upload_pk'] . "'>";
      $V .= $Name . "/";
      $V .= "</a><br>" . $Desc . "</td>\n";
      $V .= "<td align='right'>" . substr($Row['upload_ts'],0,16) . "</td></tr>\n";
      $V .= "<tr><td colspan=2>&nbsp;</td></tr>\n";
      }
    $V .= "</ul>\n";
    return($V);
    } /* ShowFid() */

  /***********************************************************
   Output(): This function returns the scheduler status.
   ***********************************************************/
  function Output()
    {
    if ($this->State != PLUGIN_STATE_READY) { return(0); }
    $V="";
    $Fid = GetParm("folder",PARM_INTEGER);
    $Uid = GetParm("upload",PARM_INTEGER);
    switch(GetParm("show",PARM_INTEGER))
	{
	case 'detail':
		$Show='detail';
		break;
	case 'name':
	default:
		$Show='name';
		break;
	}

    switch($this->OutputType)
      {
      case "XML":
	break;
      case "HTML":
	$V .= "<style type='text/css'>\n";
	$V .= ".text { height:24px; font:normal 10pt verdana, arial, helvetica; }\n";
	$V .= "a { text-decoration:none; }\n";
	$V .= "div { padding:0; margin:0; }\n";
	$V .= "</style>\n";

	$V .= "<font class='text'>\n";

	/* Get the folder description */
	if (!empty($Fid))
	  {
	  $V .= $this->ShowFid($Fid);
	  }

	/* Display the browse */
	$V .= "</font>\n";
	break;
      case "Text":
	break;
      default:
	break;
      }
    if (!$this->OutputToStdout) { return($V); }
    print "$V";
    return;
    }

  };
$NewPlugin = new ui_browse;
$NewPlugin->Initialize();

?>
