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
 This plugin is used to list all files associated
 with a specific license.
 This is NOT intended to be a user-UI plugin.
 This is intended as an active plugin to provide support
 data to the UI.
 *************************************************/

class search_file_by_license extends Plugin
  {
  var $Name       = "search_file_by_license";
  var $Title      = "List Files based on License";
  var $Version    = "1.0";
  var $Dependency = array("db","browse");
  var $DBaccess   = PLUGIN_DB_READ;

  /***********************************************************
   Output(): Display the loaded menu and plugins.
   ***********************************************************/
  function Output()
    {
    if ($this->State != PLUGIN_STATE_READY) { return; }
    $V="";
    global $Plugins;
    global $DB;
    $Time = time();
    switch($this->OutputType)
      {
      case "XML":
        break;
      case "HTML":
	$UploadTreePk = GetParm("item",PARM_INTEGER);
	$LicPk = GetParm("lic",PARM_INTEGER);
	if (empty($UploadTreePk) || empty($LicPk))
		{
		return;
		}

	/* Get License Name */
	$Results = $DB->Action("SELECT * FROM agent_lic_raw WHERE lic_pk = '$LicPk';");
	$LicName = htmlentities($Results[0]['lic_name']);
	if (empty($LicName)) { return; }
	$V .= "The following files contain the license '<b>$LicName</b>'.<P />\n";
	/* Load licenses */
	$Lics = array();
	LicenseGetAllFiles($UploadTreePk,$Lics,$LicPk);
	/* Save the license results */
	foreach($Lics as $Key => $L)
	  {
	  if (empty($L['pfile_fk'])) { continue; }
	  $V .= Dir2Browse("browse",$L['uploadtree_pk'],$L['ufile_pk'],"view-license",1,NULL,$Key+1) . "<P />\n";
	  }
	$V .= "<hr>\n";
	$Time = time() - $Time;
	$V .= "<small>Elaspsed time: $Time seconds</small>\n";
        break;
      case "Text":
        break;
      default:
        break;
      }
    if (!$this->OutputToStdout) { return($V); }
    print($V);
    return;
    } // Output()


  };
$NewPlugin = new search_file_by_license;
$NewPlugin->Initialize();

?>
