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

class admin_db_cleanup extends FO_Plugin
  {
  var $Name       = "admin_db_cleanup";
  var $Version    = "1.0";
  var $Title      = "Database Check";
  var $MenuList   = "Admin::Database::Check";
  var $Dependency = array("db");
  var $DBaccess   = PLUGIN_DB_USERADMIN;

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
	$V .= "On occasion, the database can become inconsistent.";
	$V .= " For example, there may be pfile records without ufile entries";
	$V .= " or ufile entries that are not linked by any uploadtree records.";
	$V .= " Inconsistencies usually arise due to failed unpacking, partial deletions, and testing.\n";

	$Results = $DB->Action("SELECT COUNT(*) AS count FROM jobqueue WHERE jq_type = 'unpack' AND jq_starttime IS NOT NULL AND jq_endtime IS NULL;");
	$Count = $Results[0]['count'];
	if ($Count == 1) { $Verb = "is"; $String = "task"; }
	else { $Verb = "are"; $String = "tasks"; }
	$V .= "<P>Temporary inconsistencies may exist when a file is uploaded. The unpack system creates ufiles and pfiles first, then links them together when it completes. There $Verb currently $Count unpack $String running in the job queue.\n";

	$V .= "<P>The following inconsistencies have been identified:\n";
	$V .= "<table border=1>\n";
	$V .= "<tr><th>Type of Inconsistency</th><th>Count</th></tr>\n";

	$Results = $DB->Action("SELECT COUNT(*) AS count FROM pfile WHERE pfile_pk NOT IN (SELECT pfile_fk FROM ufile);");
	$Count = $Results[0]['count'];
	$V .= "<tr><td>Unreferenced pfiles</td><td align='right'>" . number_format($Count,0,"",",") . "</td></tr>\n";

	$Results = $DB->Action("SELECT COUNT(*) AS count FROM ufile WHERE ufile_pk NOT IN (SELECT ufile_fk FROM upload) AND ufile_pk NOT IN (SELECT ufile_fk FROM uploadtree);");
	$Count = $Results[0]['count'];
	$V .= "<tr><td>Unreferenced ufiles</td><td align='right'>" . number_format($Count,0,"",",") . "</td></tr>\n";

	$Results = $DB->Action("SELECT COUNT(*) AS count FROM ufile WHERE pfile_fk NOT IN (SELECT pfile_pk FROM pfile);");
	$Count = $Results[0]['count'];
	$V .= "<tr><td>Ufiles with invalid pfile references</td><td align='right'>" . number_format($Count,0,"",",") . "</td></tr>\n";

	$Results = $DB->Action("SELECT COUNT(*) AS count FROM upload WHERE ufile_fk NOT IN (SELECT ufile_pk FROM ufile);");
	$Count = $Results[0]['count'];
	$V .= "<tr><td>Upload records with invalid ufile references</td><td align='right'>" . number_format($Count,0,"",",") . "</td></tr>\n";

	$Results = $DB->Action("SELECT COUNT(*) AS count FROM uploadtree WHERE ufile_fk NOT IN (SELECT ufile_pk FROM ufile);");
	$Count = $Results[0]['count'];
	$V .= "<tr><td>Uploadtree records with invalid ufile references</td><td align='right'>" . number_format($Count,0,"",",") . "</td></tr>\n";

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
$NewPlugin = new admin_db_cleanup;
$NewPlugin->Initialize();
?>
