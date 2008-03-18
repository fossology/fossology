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

class debug_user extends FO_Plugin
  {
  var $Name       = "debug_user";
  var $Version    = "1.0";
  var $Title      = "Debug User";
  var $MenuList   = "Help::Debug::Debug User";
  var $Dependency = array("db");
  var $DBaccess   = PLUGIN_DB_DEBUG;

  /************************************************
   Output(): Generate output.
   ************************************************/
  function Output()
    {
    if ($this->State != PLUGIN_STATE_READY) { return; }
    $V="";
    global $DB;
    switch($this->OutputType)
      {
      case "XML":
	break;
      case "HTML":
	$Results = $DB->Action("SELECT * FROM users WHERE user_pk = '" . $_SESSION['UserId'] . "';");
	$R = &$Results[0];
	$V .= "<H2>User Information</H2>\n";
	$V .= "<table border=1>\n";
	$V .= "<tr><th>Field</th><th>Value</th></tr>\n";
	foreach($R as $Key => $Val)
	  {
	  if (empty($Key)) { continue; }
	  $V .= "<tr><td>" . htmlentities($Key) . "</td><td>" . htmlentities($Val) . "</td></tr>\n";
	  }
	$V .= "</table>\n";
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
$NewPlugin = new debug_user;
$NewPlugin->Initialize();
?>
