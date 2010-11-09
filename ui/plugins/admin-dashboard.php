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

define("TITLE_dashboard", _("Dashboard"));

class dashboard extends FO_Plugin
{
  var $Name       = "dashboard";
  var $Version    = "1.0";
  var $Title      = TITLE_dashboard;
  var $MenuList   = "Admin::Dashboard";
  var $Dependency = array("db");
  var $DBaccess   = PLUGIN_DB_DOWNLOAD;

  /************************************************
   DiskFree(): Determine amount of free disk space.
   ************************************************/
  function DiskFree()
  {
    global $SYSCONFDIR;
    $Cmd = "df -Pk `cat '$SYSCONFDIR/fossology/RepPath.conf'`/*/* | sort -u | grep '%'";
    $Fin = popen($Cmd,"r");

    /* Read results */
    $Buf = "";
    while(!feof($Fin))
    {
      $Buf .= fread($Fin,8192);
    }
    pclose($Fin);

    /* Separate lines */
    $Lines = split("\n",$Buf);

    /* Display results */
    $V = "";
    $V .= "<table border=1>\n";
$text = _("Filesystem");
$text1 = _("Used");
$text2 = _("Capacity");
$text3 = _("Percent Full");
    $V .= "<tr><th>$text</th><th colspan=2>$text1</th><th colspan=2>$text2</th><th>$text3</th></tr>\n";
    foreach($Lines as $L)
    {
      if (empty($L)) { continue; }
      $L = trim($L);
      $L = preg_replace("/[[:space:]][[:space:]]*/"," ",$L);
      $List = split(" ",$L);
      $V .= "<tr><td>" . htmlentities($List[0]) . "</td>";
      $Used = $List[2] * 1024;
      $UsedH = Bytes2Human($Used);
      $Capacity = $List[1] * 1024;
      $CapacityH = Bytes2Human($Capacity);

      $V .= "<td align='right' style='border-right:none'>$UsedH</td>";
      $V .= "<td align='right' style='border-left:none'>(" . number_format($Used,0,"",",") . ")</td>";
      $V .= "<td align='right' style='border-right:none'>$CapacityH</td>";
      $V .= "<td align='right' style='border-left:none'>(" . number_format($Capacity,0,"",",") . ")</td>";
      $V .= "<td align='right'>" . htmlentities($List[4]) . "</td></tr>\n";
    }
    $V .= "</table>\n";
    return($V);
  } // DiskFree()

  /************************************************
   Output(): Generate output.
   ************************************************/
  function Output() {
    
    global $DB;
    global $Plugins;
    
    if ($this->State != PLUGIN_STATE_READY) { return; }
    $V="";
    
    switch($this->OutputType)
    {
      case "XML":
	break;
      case "HTML":
	/**************************************************/
	$V .= "<table border=0 width='100%'><tr>\n";

	/**************************************************/
	$V .= "<td valign='top'>\n";
$text = _("Job Queue");
	$V .= "<H2>$text</H2>\n";
	$V .= "<table border=1>\n";
$text = _("Queue Information");
$text1 = _("Total");
	$V .= "<tr><th>$text</th><th>$text1</th></tr>\n";
	// Dynamically set hyperlinks based on showjobs plugin existence.
	$uri_showjobs = Traceback_uri() . "?mod=showjobs";
	$showjobs_exists = &$Plugins[plugin_find_id("showjobs")]; /* may be null */

	$Results = $DB->Action("SELECT COUNT(DISTINCT jq_job_fk) AS val FROM jobqueue WHERE jq_endtime IS NULL OR jq_end_bits = 2;");
	if ($showjobs_exists) {
$text = _("Pending Analysis Jobs");
	  $V .= "<tr><td><a href='$uri_showjobs'>$text</a></td> ";
	}
	else {
$text = _("Pending Analysis Jobs");
	  $V .= "<tr><td>$text</td>";
	}
	$V .= "<td align='right'>" . number_format($Results[0]['val'],0,"",",") . "</td></tr>\n";;
	$Results = $DB->Action("SELECT COUNT(*) AS val FROM jobqueue WHERE jq_endtime IS NULL OR jq_end_bits = 2;");
	if ($showjobs_exists) {
$text = _("Tasks in the Job Queue");
	  $V .= "<tr><td><a href='$uri_showjobs'>$text</a></td> ";
	}
	else {
$text = _("Tasks in the Job Queue");
	  $V .= "<tr><td>$text</td>";
	}
	$V .= "<td align='right'>" . number_format($Results[0]['val'],0,"",",") . "</td></tr>\n";
	$Results = $DB->Action("SELECT COUNT(*) AS val FROM jobqueue WHERE jq_endtime IS NULL AND jq_starttime IS NOT NULL;");
	if ($showjobs_exists) {
$text = _("Running Tasks");
	  $V .= "<tr><td><a href='$uri_showjobs'>$text</a></td> ";
	}
	else {
$text = _("Running Tasks");
	  $V .= "<tr><td>$text</td>";
	}
	$V .= "<td align='right'>" . number_format($Results[0]['val'],0,"",",") . "</td></tr>\n";
	$Results = $DB->Action("SELECT COUNT(*) AS val FROM jobqueue WHERE jq_endtime IS NOT NULL AND jq_end_bits=2;");
	if ($showjobs_exists) {
$text = _("Failed Tasks");
	  $V .= "<tr><td><a href='$uri_showjobs'>$text</a></td> ";
	}
	else {
$text = _("Failed Tasks");
	  $V .= "<tr><td>$text</td>";
	}
	$V .= "<td align='right'>" . number_format($Results[0]['val'],0,"",",") . "</td></tr>\n";
	$V .= "</table>\n";
	$V .= "</td>";

	/**************************************************/
	$V .= "<td valign='top'>\n";
$text = _("Database Contents");
	$V .= "<H2>$text</H2>\n";
	$V .= "<table border=1>\n";
$text = _("Metric");
$text1 = _("Total");
	$V .= "<tr><th>$text</th><th>$text1</th></tr>\n";
	$Results = $DB->Action("SELECT count(*) AS val FROM upload;");
$text = _("Unique Uploads");
	$V .= "<tr><td>$text</td>";
	$V .= "<td align='right'>" . number_format($Results[0]['val'],0,"",",") . "</td></tr>\n";;
	$Results = $DB->Action("SELECT count(*) AS val FROM pfile;");
$text = _("Unique Extracted Files");
	$V .= "<tr><td>$text</td>";
	$V .= "<td align='right'>" . number_format($Results[0]['val'],0,"",",") . "</td></tr>\n";;
	$Results = $DB->Action("SELECT count(*) AS val FROM uploadtree;");
$text = _("Extracted Names");
	$V .= "<tr><td>$text</td>";
	$V .= "<td align='right'>" . number_format($Results[0]['val'],0,"",",") . "</td></tr>\n";;
	$Results = $DB->Action("SELECT count(*) AS val FROM agent_lic_raw;");
$text = _("Known License Templates");
	$V .= "<tr><td>$text</td>";
	$V .= "<td align='right'>" . number_format($Results[0]['val'],0,"",",") . "</td></tr>\n";;
	$Results = $DB->Action("SELECT count(*) AS val FROM agent_lic_meta;");
$text = _("Identified Licenses");
	$V .= "<tr><td>$text</td>";
	$V .= "<td align='right'>" . number_format($Results[0]['val'],0,"",",") . "</td></tr>\n";;
	$V .= "</table>\n";

	/**************************************************/
	$V .= "<td valign='top'>\n";
$text = _("Database Metrics");
	$V .= "<H2>$text</H2>\n";
	$V .= "<table border=1>\n";
$text = _("Metric");
$text1 = _("Total");
	$V .= "<tr><th>$text</th><th>$text1</th></tr>\n";
    $Results = $DB->Action("SELECT pg_size_pretty(pg_database_size('fossology')) as val");
$text = _("FOSSology database size");
	$V .= "<tr><td>$text</td>";
	$Size = $Results[0]['val']; 
	$V .= "<td align='right'>  $Size </td></tr>\n";;

	$Results = $DB->Action("SELECT count(*) AS val FROM pg_stat_activity';");
	if (!empty($Results[0]['val']))
	  {
$text = _("Active database connections");
	  $V .= "<tr><td>$text</td>";
	  $V .= "<td align='right'>" . number_format($Results[0]['val'],0,"",",") . "</td></tr>\n";;
	  $Results = $DB->Action("SELECT count(*) AS val FROM pg_stat_activity WHERE current_query != '<IDLE>' AND datname = 'fossology';");
$text = _("Active FOSSology queries");
	  $V .= "<tr><td>$text</td>";
	  $V .= "<td align='right'>" . number_format($Results[0]['val'],0,"",",") . "</td></tr>\n";;
	  $Results = $DB->Action("SELECT datname,now()-query_start AS val FROM pg_stat_activity WHERE current_query != '<IDLE>' AND datname = 'fossology' ORDER BY val;");
	  for($i=0; !empty($Results['datname']); $i++)
	    {
$text = _("Duration query #");
$text1 = _(" has been active");
	    $V .= "<tr><td>$text . $i . $text1</td>";
	    $V .= "<td align='right'>" . $Results[$i]['val'] . "</td></tr>\n";
	    }
	  }
	$V .= "</table>\n";

	/**************************************************/
	$V .= "</table>\n";

	/**************************************************/
$text = _("Repository Disk Space");
	$V .= "<H2>$text</H2>\n";
	$V .= $this->DiskFree();

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
$NewPlugin = new dashboard;
$NewPlugin->Initialize();
?>
