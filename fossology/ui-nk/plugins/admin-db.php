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
   FixDB(): Fix the DB by deleting offending records.
   ************************************************/
  function FixDB	($CheckType)
    {
    global $DB;
    print "Deleting " . $CheckType['label'] . "...";
    $DB->Action("DELETE " . $CheckType['sql']);
    print $DB->GetAffectedRows . "cleaned.<br>";
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
	$Checks = array();
	$i=0;
	$Checks[$i]['tag']   = "bad_lic_pfile";
	$Checks[$i]['label'] = "License records associated with unreferenced pfiles";
	$Checks[$i]['sql']   = "FROM agent_lic_meta WHERE pfile_fk IN (SELECT pfile_pk FROM pfile WHERE pfile_pk NOT IN (SELECT pfile_fk FROM ufile));";
	$i++;
	$Checks[$i]['tag']   = "bad_licstatus_pfile";
	$Checks[$i]['label'] = "License status records associated with unreferenced pfiles";
	$Checks[$i]['sql']   = "FROM agent_lic_status WHERE pfile_fk IN (SELECT pfile_pk FROM pfile WHERE pfile_pk NOT IN (SELECT pfile_fk FROM ufile));";
	$i++;
	$Checks[$i]['tag']   = "bad_attrib_pfile";
	$Checks[$i]['label'] = "Attribute records associated with unreferenced pfiles";
	$Checks[$i]['sql']   = "FROM attrib WHERE pfile_fk IN (SELECT pfile_pk FROM pfile WHERE pfile_pk NOT IN (SELECT pfile_fk FROM ufile));";
	$i++;
	$Checks[$i]['tag']   = "unreferenced_pfiles";
	$Checks[$i]['label'] = "Unreferenced pfiles";
	$Checks[$i]['sql']   = "FROM pfile WHERE pfile_pk NOT IN (SELECT pfile_fk FROM ufile);";
	$i++;
	$Checks[$i]['tag']   = "unreferenced_ufile";
	$Checks[$i]['label'] = "Unreferenced ufiles";
	$Checks[$i]['sql']   = "FROM ufile WHERE ufile_pk NOT IN (SELECT ufile_fk FROM upload) AND ufile_pk NOT IN (SELECT ufile_fk FROM uploadtree);";
	$i++;
	$Checks[$i]['tag']   = "bad_ufile_pfile";
	$Checks[$i]['label'] = "Ufiles with invalid pfile references";
	$Checks[$i]['sql']   = "FROM ufile WHERE pfile_fk IS NOT NULL AND pfile_fk NOT IN (SELECT pfile_pk FROM pfile);";
	$i++;
	$Checks[$i]['tag']   = "bad_upload_ufile";
	$Checks[$i]['label'] = "Upload records with invalid ufile references";
	$Checks[$i]['sql']   = "FROM upload WHERE ufile_fk NOT IN (SELECT ufile_pk FROM ufile);";
	$i++;
	$Checks[$i]['tag']   = "bad_uploadtree_ufile";
	$Checks[$i]['label'] = "Uploadtree records with invalid ufile references";
	$Checks[$i]['sql']   = "FROM uploadtree WHERE ufile_fk NOT IN (SELECT ufile_pk FROM ufile);";
	$i++;
	$Checks[$i]['tag']   = "bad_foldercontents_upload";
	$Checks[$i]['label'] = "Foldercontents with invalid upload references";
	$Checks[$i]['sql']   = "FROM foldercontents WHERE foldercontents_mode = 2 AND child_id NOT IN (SELECT upload_pk FROM upload);";
	$i++;
	$Checks[$i]['tag']   = "bad_foldercontents_uploadtree";
	$Checks[$i]['label'] = "Foldercontents with invalid uploadtree references";
	$Checks[$i]['sql']   = "FROM foldercontents WHERE foldercontents_mode = 4 AND child_id NOT IN (SELECT uploadtree_pk FROM uploadtree);";
	$i++;
	$Checks[$i]['tag']   = "bad_foldercontents_folder";
	$Checks[$i]['label'] = "Foldercontents with invalid folder references";
	$Checks[$i]['sql']   = "FROM foldercontents WHERE foldercontents_mode = 1 AND child_id NOT IN (SELECT folder_pk FROM folder);";
	$i++;
	$Checks[$i]['tag']   = "unreferenced_folder";
	$Checks[$i]['label'] = "Unreferenced folders";
	$Checks[$i]['sql']   = "FROM folder WHERE folder_pk NOT IN (SELECT child_id FROM foldercontents WHERE foldercontents_mode = 1) AND folder_pk != '1';";

	/* Check for anything to fix */
        $Args=0;
	$DB->Debug=1; /* I want to see errors! */
	for($i=0; !empty($Checks[$i]['tag']); $i++)
	  {
	  if (GetParm($Checks[$i]['tag'],PARM_INTEGER) == 1)
	    {
	    if ($Args==0) { print "<H1>Fixing Records</H1>\n"; }
	    $this->FixDB($Checks[$i]);
	    $Args++;
	    }
	  }
	if ($Args > 0) { $V .= "<P />\n"; }

$V .= "<H1>THIS IS TBD -- DO NOT USE</H1>\n";
	/***************************************/
	$V .= "On occasion, the database can become inconsistent.";
	$V .= " For example, there may be pfile records without ufile entries";
	$V .= " or ufile entries that are not linked by any uploadtree records.";
	$V .= " Inconsistencies usually arise due to failed unpacking, partial deletions, and testing.\n";

	$Results = $DB->Action("SELECT COUNT(*) AS count FROM jobqueue WHERE (jq_type = 'unpack' OR jq_type = 'delagent') AND jq_starttime IS NOT NULL AND jq_endtime IS NULL;");
	$Count = $Results[0]['count'];
	if ($Count == 1) { $Verb = "is"; $String = "task"; }
	else { $Verb = "are"; $String = "tasks"; }
	$V .= "<P>Temporary inconsistencies may exist when a file is uploaded, being unpacked, or being deleted.\n";
	$V .= "<ul>";
	$V .= "<li>An uploaded file -- before being unpacked -- can appear inconsistent.\n";
	$V .= "<li>The unpack system creates ufiles and pfiles first, then links them together when it completes.\n";
	$V .= "<li>The delete system removes records in series, so a partial delete (or delete in progress) can show inconsistencies.\n";
	$V .= "</ul>\n";
	$V .= "There $Verb <b>currently $Count $String running</b> in the job queue that may make records appear inconsistent.\n";
	$V .= "Fixing inconsistencies while any jobs are running could lead to job failures and further inconsistencies.\n";

	$V .= "<P>The following inconsistencies have been identified:\n";
	$V .= "<form method='POST'>";
	$V .= "<table border=1>\n";
	$V .= "<tr><th>Fix</th><th>Type of Inconsistency</th><th>Count</th></tr>\n";

	for($i=0; !empty($Checks[$i]['tag']); $i++)
	  {
	  $Results = $DB->Action("SELECT COUNT(*) AS count " . $Checks[$i]['sql']);
	  $Count = $Results[0]['count'];
	  $V .= "<tr>";
	  if ($Count > 0) { $V .= "<td><input type='checkbox' name='" . $Checks[$i]['tag'] . "' value='1'></td>"; }
	  else { $V .= "<td></td>"; }
	  $V .= "<td>" . $Checks[$i]['label'] . "</td>";
	  $V .= "<td align='right'>" . number_format($Count,0,"",",") . "</td></tr>\n";
	  }

	$V .= "</table>\n";
	$V .= "<P /><input type='submit' value='Fix!'>";
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
$NewPlugin = new admin_db_cleanup;
$NewPlugin->Initialize();
?>
