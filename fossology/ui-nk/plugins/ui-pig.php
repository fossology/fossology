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

class ui_pig extends FO_Plugin
  {
  var $Name       = "pig";
  var $Title      = "Pig Lipstick";
  var $Version    = "1.0";
  var $MenuList   = "Help::Pig";
  var $Dependency = array("db");
  var $DBaccess   = PLUGIN_DB_WRITE;
  // var $DBaccess   = 99;

  function Output()
    {
    if ($this->State != PLUGIN_STATE_READY) { return; }
    $V="";
    switch($this->OutputType)
      {
      case "XML":
	break;
      case "HTML":
	$V .= "Managing licenses is like putting lipstick on a pig!<P />\n";
	$V .= "<img id='pig' src='images/pig.gif'><P />\n";
	$V .= "Select your lipstick color: ";
	global $DATADIR;
	$V .= "<select id='lipstick' onchange='document.getElementById(\"pig\").style.background = document.getElementById(\"lipstick\").value'>\n";
	global $DB;
	$Results = $DB->Action("SELECT lic_name,lic_unique FROM agent_lic_raw WHERE lic_id = lic_pk ORDER BY lic_name;");
	for($i=0; !empty($Results[$i]['lic_name']); $i++)
	  {
	  $Color = "#" . substr($Results[$i]['lic_unique'],0,6);
	  $Lipstick = htmlentities($Results[$i]['lic_name']);
	  $V .= "<option value='" . $Color . "'>" . $Lipstick . "</option>\n";
	  }
	$V .= "</select>\n";
	break;
      case "Text":
	break;
      default:
	break;
      }
    if (!$this->OutputToStdout) { return($V); }
    print($V);
    return;
    }

  };
$NewPlugin = new ui_pig;
$NewPlugin->Initialize();
?>
