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
    $Cmd = "/bin/df -Pk `cat '$DATADIR/repository/RepPath.conf'`/*/* | /usr/bin/sort -u | /bin/grep '%'";
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
    $V .= "<tr><th>Filesystem</th><th>Used</th><th>Capacity</th><th>Percent Full</th></tr>\n";
    foreach($Lines as $L)
      {
      if (empty($L)) { continue; }
      $L = trim($L);
      $L = preg_replace("/[[:space:]][[:space:]]*/"," ",$L);
      $List = split(" ",$L);
      $V .= "<tr><td>" . htmlentities($List[0]) . "</td>";
      $Used = $List[2] * 1024;
      $UsedH = Bytes2Human($Used);
      if ($Used != $UsedH) { $UsedH = " ($UsedH)"; }
      else { $UsedH = ""; }
      $Capacity = $List[1] * 1024;
      $CapacityH = Bytes2Human($Capacity);
      if ($Capacity != $CapacityH) { $CapacityH = " ($CapacityH)"; }
      else { $CapacityH = ""; }

      $V .= "<td align='right'>" . number_format($Used,0,"",",") . "$UsedH</td>";
      $V .= "<td align='right'>" . number_format($Capacity,0,"",",") . "$CapacityH</td>";
      $V .= "<td align='right'>" . htmlentities($List[4]) . "</td></tr>\n";
      }
    $V .= "</table>\n";
    return($V);
    } // DiskFree()

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
	/**************************************************/
	$V .= "<table border=0 width='100%'><tr><td align='left' valign='top'>\n";

	$V .= "<H2>Job Queue</H2>\n";
	$V .= "<table border=1>\n";
	$V .= "<tr><th>Queue Information</th><th>Total</th></tr>\n";

	$Results = $DB->Action("SELECT COUNT(DISTINCT jq_job_fk) AS val FROM jobqueue WHERE jq_endtime IS NULL OR jq_end_bits = 2;");
	$V .= "<tr><td>Pending Analysis Jobs</td>";
	$V .= "<td align='right'>" . number_format($Results[0]['val'],0,"",",") . "</td></tr>\n";;
	$Results = $DB->Action("SELECT COUNT(*) AS val FROM jobqueue WHERE jq_endtime IS NULL OR jq_end_bits = 2;");
	$V .= "<tr><td>Tasks in the Job Queue</td>";
	$V .= "<td align='right'>" . number_format($Results[0]['val'],0,"",",") . "</td></tr>\n";
	$Results = $DB->Action("SELECT COUNT(*) AS val FROM jobqueue WHERE jq_endtime IS NULL AND jq_starttime IS NOT NULL;");
	$V .= "<tr><td>Running Tasks</td>";
	$V .= "<td align='right'>" . number_format($Results[0]['val'],0,"",",") . "</td></tr>\n";
	$Results = $DB->Action("SELECT COUNT(*) AS val FROM jobqueue WHERE jq_endtime IS NOT NULL AND jq_end_bits=2;");
	$V .= "<tr><td>Failed Tasks</td>";
	$V .= "<td align='right'>" . number_format($Results[0]['val'],0,"",",") . "</td></tr>\n";

	$V .= "</table>\n";

	$V .= "</td><td align='left' valign='top'>\n";

	/**************************************************/
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

	$V .= "</td></tr></table>\n";

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
