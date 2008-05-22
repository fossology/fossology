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

class dashboard extends FO_Plugin
{
  var $Name       = "dashboard";
  var $Version    = "1.0";
  var $Title      = "Dashboard";
  var $MenuList   = "Admin::Dashboard";
  var $Dependency = array("db");

  /************************************************
   DiskFree(): Determine amount of free disk space.
   ************************************************/
  function DiskFree()
  {
    global $DATADIR;
    $Cmd = "df -Pk `cat '$DATADIR/repository/RepPath.conf'`/*/* | sort -u | grep '%'";
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
    $V .= "<tr><th>Filesystem</th><th colspan=2>Used</th><th colspan=2>Capacity</th><th>Percent Full</th></tr>\n";
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
	$V .= "<H2>Job Queue</H2>\n";
	$V .= "<table border=1>\n";
	$V .= "<tr><th>Queue Information</th><th>Total</th></tr>\n";
	// Dynamically set hyperlinks based on showjobs plugin existence.
	$uri_showjobs = Traceback_uri() . "?mod=showjobs";
	$showjobs_exists = &$Plugins[plugin_find_id("showjobs")]; /* may be null */

	$Results = $DB->Action("SELECT COUNT(DISTINCT jq_job_fk) AS val FROM jobqueue WHERE jq_endtime IS NULL OR jq_end_bits = 2;");
	if ($showjobs_exists) {
	  $V .= "<tr><td><a href='$uri_showjobs'>Pending Analysis Jobs</a></td>";
	}
	else {
	  $V .= "<tr><td>Pending Analysis Jobs</td>";
	}
	$V .= "<td align='right'>" . number_format($Results[0]['val'],0,"",",") . "</td></tr>\n";;
	$Results = $DB->Action("SELECT COUNT(*) AS val FROM jobqueue WHERE jq_endtime IS NULL OR jq_end_bits = 2;");
	if ($showjobs_exists) {
	  $V .= "<tr><td><a href='$uri_showjobs'>Tasks in the Job Queue</a></td>";
	}
	else {
	  $V .= "<tr><td>Tasks in the Job Queue</td>";
	}
	$V .= "<td align='right'>" . number_format($Results[0]['val'],0,"",",") . "</td></tr>\n";
	$Results = $DB->Action("SELECT COUNT(*) AS val FROM jobqueue WHERE jq_endtime IS NULL AND jq_starttime IS NOT NULL;");
	if ($showjobs_exists) {
	  $V .= "<tr><td><a href='$uri_showjobs'>Running Tasks</a></td>";
	}
	else {
	  $V .= "<tr><td>Running Tasks</td>";
	}
	$V .= "<td align='right'>" . number_format($Results[0]['val'],0,"",",") . "</td></tr>\n";
	$Results = $DB->Action("SELECT COUNT(*) AS val FROM jobqueue WHERE jq_endtime IS NOT NULL AND jq_end_bits=2;");
	if ($showjobs_exists) {
	  $V .= "<tr><td><a href='$uri_showjobs'>Failed Tasks</a></td>";
	}
	else {
	  $V .= "<tr><td>Failed Tasks</td>";
	}
	$V .= "<td align='right'>" . number_format($Results[0]['val'],0,"",",") . "</td></tr>\n";
	$V .= "</table>\n";
	$V .= "</td>";

	/**************************************************/
	$V .= "<td valign='top'>\n";
	$V .= "<H2>Database Contents</H2>\n";
	$V .= "<table border=1>\n";
	$V .= "<tr><th>Metric</th><th>Total</th></tr>\n";
	$Results = $DB->Action("SELECT count(*) AS val FROM upload;");
	$V .= "<tr><td>Unique Uploads</td>";
	$V .= "<td align='right'>" . number_format($Results[0]['val'],0,"",",") . "</td></tr>\n";;
	$Results = $DB->Action("SELECT count(*) AS val FROM pfile;");
	$V .= "<tr><td>Unique Extracted Files</td>";
	$V .= "<td align='right'>" . number_format($Results[0]['val'],0,"",",") . "</td></tr>\n";;
	$Results = $DB->Action("SELECT count(*) AS val FROM uploadtree;");
	$V .= "<tr><td>Extracted Names</td>";
	$V .= "<td align='right'>" . number_format($Results[0]['val'],0,"",",") . "</td></tr>\n";;
	$Results = $DB->Action("SELECT count(*) AS val FROM agent_lic_raw;");
	$V .= "<tr><td>Known License Templates</td>";
	$V .= "<td align='right'>" . number_format($Results[0]['val'],0,"",",") . "</td></tr>\n";;
	$Results = $DB->Action("SELECT count(*) AS val FROM agent_lic_meta;");
	$V .= "<tr><td>Identified Licenses</td>";
	$V .= "<td align='right'>" . number_format($Results[0]['val'],0,"",",") . "</td></tr>\n";;
	$V .= "</table>\n";

	/**************************************************/
	$V .= "<td valign='top'>\n";
	$V .= "<H2>Database Metrics</H2>\n";
	$V .= "<table border=1>\n";
	$V .= "<tr><th>Metric</th><th>Total</th></tr>\n";
	$Results = $DB->Action("SELECT SUM(relpages) AS val FROM pg_class INNER JOIN pg_stat_all_tables ON pg_stat_all_tables.relname = pg_class.relname WHERE schemaname='public';");
	$V .= "<tr><td>FOSSology database size</td>";
	$Size = $Results[0]['val'] * 8*1024; /* 8K per page */
	$V .= "<td align='right'>" . Bytes2Human($Size) . " (" . number_format($Size,0,"",",") . ")</td></tr>\n";;

	$Results = $DB->Action("SELECT count(*) AS val FROM pg_stat_activity';");
	if (!empty($Results[0]['val']))
	  {
	  $V .= "<tr><td>Active database connections</td>";
	  $V .= "<td align='right'>" . number_format($Results[0]['val'],0,"",",") . "</td></tr>\n";;
	  $Results = $DB->Action("SELECT count(*) AS val FROM pg_stat_activity WHERE current_query != '<IDLE>' AND datname = 'fossology';");
	  $V .= "<tr><td>Active FOSSology queries</td>";
	  $V .= "<td align='right'>" . number_format($Results[0]['val'],0,"",",") . "</td></tr>\n";;
	  $Results = $DB->Action("SELECT datname,now()-query_start AS val FROM pg_stat_activity WHERE current_query != '<IDLE>' AND datname = 'fossology' ORDER BY val;");
	  for($i=0; !empty($Results['datname']); $i++)
	    {
	    $V .= "<tr><td>Duration query #" . $i . " has been active</td>";
	    $V .= "<td align='right'>" . $Results[$i]['val'] . "</td></tr>\n";
	    }
	  }
	$V .= "</table>\n";

	/**************************************************/
	$V .= "</table>\n";

	/**************************************************/
	$V .= "<H2>Repository Disk Space</H2>\n";
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
