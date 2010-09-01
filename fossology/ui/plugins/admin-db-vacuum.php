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

class admin_db_vacuum extends FO_Plugin
  {
  var $Name       = "admin_db_vacuum";
  var $Version    = "1.0";
  var $Title      = "Database Vacuum and Analyze";
  var $MenuList   = "Admin::Database::Vacuum and Analyze";
  var $Dependency = array("db");
  var $DBaccess   = PLUGIN_DB_USERADMIN;

  /************************************************
   FixDB(): Fix the DB by deleting offending records.
   ************************************************/
  function FixDB	($CheckType)
    {
    global $DB;
$text = _("Deleting");
    print $text . $CheckType['label'] . "...";
    $DB->Action("DELETE ". $CheckType['sql']);
$text=_("cleaned.");
    print $DB->GetAffectedRows . "$text<br>";
    } // FixDB()

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
        $Action = "";
	if (GetParm('vacuum',PARM_INTEGER) == 1) { $Action .= "VACUUM"; }
	if (GetParm('analyze',PARM_INTEGER) == 1) { $Action .= " ANALYZE"; }
	$SQL = "SELECT table_name AS table
		FROM information_schema.tables
		WHERE table_type = 'BASE TABLE'
		AND table_schema = 'public'
		ORDER BY table_name
		;";
	$Tables = $DB->Action($SQL);
	if (!empty($Action))
	  {
$text = _("Cleaning: Vacuum and Analyze");
	  print "<b>$text</b><br>\n";
	  flush();
	  for($i=0; !empty($Tables[$i]['table']); $i++)
		{
		print "$Action for " . $Tables[$i]['table'] . "<br>\n";
		flush();
		$DB->Action($Action . " " . $Tables[$i]['table'] . ";");
		}
	  print "<P>\n";
	  }

	/***************************************/
	$V .= _("Database performance can be improved by optimizing table memory allocation.");
	$V .= _(" The database supports 'vacuum' to free deleted rows");
	$V .= _(" and 'analyze' to precompute row counts.\n");
	$V .= _(" These two functions are called by most agents on an as-needed basis.");
	$V .= _(" However, you can start them yourself as needed.");
	$V .= _(" Keep in mind:\n");
	$V .= _(" Running them too often can negatively impact database performance since the database will spend more time cleaning than doing real work.");

	$V .= "<form method='POST'>";
$text = _(" Vacuum\n");
	$V .= "<P><input type='checkbox' name='vacuum' value='1'>$text";
$text = _(" Analyze\n");
	$V .= "<br><input type='checkbox' name='analyze' value='1'>$text";
$text = _("Clean");
	$V .= "<P><input type='submit' value='$text!'>\n";
	$V .= "</form>";
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
$NewPlugin = new admin_db_vacuum;
$NewPlugin->Initialize();
?>
