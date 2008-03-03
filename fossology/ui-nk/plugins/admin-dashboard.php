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

class dashboard extends Plugin
  {
  var $Name       = "dashboard";
  var $Version    = "1.0";
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
    while(($Line = fread($Fin,8192)) && (strlen($Line) > 0))
	{
	$Buf .= $Line;
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
      $V .= "<td align='right'>" . number_format($List[2] * 1024,0,"",",") . "</td>";
      $V .= "<td align='right'>" . number_format($List[1] * 1024,0,"",",") . "</td>";
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
	$V .= "<H2>Database Contents</H2>\n";
	$V .= "<table border=1>\n";
	$Results = $DB->Action("SELECT count(*) AS val FROM upload;");
	$V .= "<tr><td>Number of unique uploads</td>";
	$V .= "<td align='right'>" . number_format($Results[0]['val'],0,"",",") . "</td></tr>\n";;
	$Results = $DB->Action("SELECT count(*) AS val FROM pfile;");
	$V .= "<tr><td>Number of unique extracted files</td>";
	$V .= "<td align='right'>" . number_format($Results[0]['val'],0,"",",") . "</td></tr>\n";;
	$Results = $DB->Action("SELECT count(*) AS val FROM uploadtree;");
	$V .= "<tr><td>Number of extracted names</td>";
	$V .= "<td align='right'>" . number_format($Results[0]['val'],0,"",",") . "</td></tr>\n";;
	$Results = $DB->Action("SELECT count(*) AS val FROM agent_lic_raw;");
	$V .= "<tr><td>Number of known license templates</td>";
	$V .= "<td align='right'>" . number_format($Results[0]['val'],0,"",",") . "</td></tr>\n";;
	$Results = $DB->Action("SELECT count(*) AS val FROM agent_lic_meta;");
	$V .= "<tr><td>Number of identified licenses</td>";
	$V .= "<td align='right'>" . number_format($Results[0]['val'],0,"",",") . "</td></tr>\n";;
	$V .= "</table>\n";

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
