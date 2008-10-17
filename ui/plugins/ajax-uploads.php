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

/*************************************************
 This plugin is used to list all uploads associated
 with a folder.  This is NOT intended to be a user-UI
 plugin.
 This is intended as an active plugin to provide support
 data to the UI.
 *************************************************/

class core_uploads extends FO_Plugin
  {
  var $Name       = "upload_options";
  var $Title      = "List Uploads as Options";
  var $Version    = "1.0";
  var $Dependency = array("db");
  var $DBaccess   = PLUGIN_DB_READ;
  var $NoHTML     = 1; /* This plugin needs no HTML content help */

  /***********************************************************
   Output(): Display the loaded menu and plugins.
   ***********************************************************/
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
	$FolderId = GetParm("folder",PARM_INTEGER);
	if (empty($FolderId)) { $FolderId = FolderGetTop(); }
	$List = FolderListUploads($FolderId);
	foreach($List as $L)
	  {
	  $V .= "<option value='" . $L['upload_pk'] . "'>";
	  $V .= htmlentities($L['name']);
	  if (!empty($L['upload_desc']))
		{
		$V .= " (" . htmlentities($L['upload_desc']) . ")";
		}
	  if (!empty($L['upload_ts']))
		{
		$V .= " :: " . htmlentities($L['upload_ts']);
		}
	  $V .= "</option>\n";
	  }
	break;
      case "Text":
	break;
      default:
	break;
      }
    if (!$this->OutputToStdout) { return($V); }
    print("$V");
    return;
    } // Output()


  };
$NewPlugin = new core_uploads;
$NewPlugin->Initialize();

?>
