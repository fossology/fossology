<?php
/***********************************************************
 Copyright (C) 2008-2013 Hewlett-Packard Development Company, L.P.

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

define("TITLE_dashboard", _("Dashboard"));

class dashboard extends FO_Plugin
{
  var $Name       = "dashboard";
  var $Version    = "1.0";
  var $Title      = TITLE_dashboard;
  var $MenuList   = "Admin::Dashboard";
  var $Dependency = array();
  var $DBaccess   = PLUGIN_DB_ADMIN;

  /**
   * \brief Return each html row for DatabaseContents()
   * \returns html table row
   */
  function DatabaseContentsRow($TableName, $TableLabel)
  {
    global $PG_CONN;

    // $sql = "SELECT count(*) AS val FROM $TableName;";  too slow on big tables, use pg_class which will be accurate as of last ANALYZE
    //$sql = "select reltuples as val from pg_class where relname='$TableName'"; this doesn't handle uploadtree
    $sql = "select sum(reltuples) as val from pg_class where relname like '$TableName' and reltype !=0";
    $result = pg_query($PG_CONN, $sql);
    DBCheckResult($result, $sql, __FILE__, __LINE__);
    $row = pg_fetch_assoc($result);
    $item_count = $row['val'];
    pg_free_result($result);

    $V = "<tr><td>$TableLabel</td>";
    $V .= "<td align='right'>" . number_format($item_count,0,"",",") . "</td>";

    $LastVacTime = $this->GetLastVacTime($TableName);
    if (empty($LastVacTime))
      $mystyle = "style=background-color:red";
    else
      $mystyle = "";
    $V .= "<td $mystyle>" . substr($LastVacTime, 0, 16) . "</td>";

    $LastAnalyzeTime = $this->GetLastAnalyzeTime($TableName);
    if (empty($LastAnalyzeTime))
      $mystyle = "style=background-color:red";
    else
      $mystyle = "";
    $V .= "<td $mystyle>" . substr($LastAnalyzeTime, 0, 16) . "</td>";

    $V .= "</tr>\n";
    return $V;
  }

  /**
   * \brief Database Contents metrics
   * \returns html table containing metrics
   */
  function DatabaseContents()
  {
    global $PG_CONN;

    $V = "<table border=1>\n";

    $head1 = _("Metric");
    $head2 = _("Total");
    $head3 = _("Last<br>Vacuum");
    $head4 = _("Last<br>Analyze");
    $V .= "<tr><th>$head1</th><th>$head2</th><th>$head3</th><th>$head4</th></tr>\n";

    /**** Users ****/
    $V .= $this->DatabaseContentsRow("users", _("Users"));

    /**** Uploads  ****/
    $V .= $this->DatabaseContentsRow("upload", _("Uploads"));

    /**** Unique pfiles  ****/
    $V .= $this->DatabaseContentsRow("pfile", _("Unique files referenced in repository"));

    /**** uploadtree recs  ****/
    $V .= $this->DatabaseContentsRow("uploadtree_%", _("Individual Files"));

    /**** License recs  ****/
    $V .= $this->DatabaseContentsRow("license_file", _("Discovered Licenses"));

    /**** Copyright recs  ****/
    $V .= $this->DatabaseContentsRow("copyright", _("Copyrights/URLs/Emails"));

    $V .= "</table>\n";

    return $V;
  }

function GetLastVacTime($TableName)
{
  global $PG_CONN;
  
  $sql = "select greatest(last_vacuum, last_autovacuum) as lasttime from pg_stat_all_tables where schemaname = 'public' and relname like '$TableName'";
  $result = pg_query($PG_CONN, $sql);
  DBCheckResult($result, $sql, __FILE__, __LINE__);
  $row = pg_fetch_assoc($result);
  pg_free_result($result);

  return $row["lasttime"];
}
function GetLastAnalyzeTime($TableName)
{
  global $PG_CONN;
  
  $sql = "select greatest(last_analyze, last_autoanalyze) as lasttime from pg_stat_all_tables where schemaname = 'public' and relname like '$TableName'";
  $result = pg_query($PG_CONN, $sql);
  DBCheckResult($result, $sql, __FILE__, __LINE__);
  $row = pg_fetch_assoc($result);
  pg_free_result($result);

  return $row["lasttime"];
}


  /**
   * \brief Database metrics
   * \returns html table containing metrics
   */
  function DatabaseMetrics()
  {
    global $PG_CONN;

    $V = "<table border=1>\n";
    $text = _("Metric");
    $text1 = _("Total");
    $V .= "<tr><th>$text</th><th>$text1</th></tr>\n";

    /* Database size */
    $sql = "SELECT pg_database_size('fossology') as val;";
    $result = pg_query($PG_CONN, $sql);
    DBCheckResult($result, $sql, __FILE__, __LINE__);
    $row = pg_fetch_assoc($result);
    $Size = HumanSize($row['val']);
    pg_free_result($result);
    $text = _("FOSSology database size");
    $V .= "<tr><td>$text</td>";
    $V .= "<td align='right'>  $Size </td></tr>\n";;

    /**** Version ****/
    $sql = "SELECT * from version();";
    $result = pg_query($PG_CONN, $sql);
    DBCheckResult($result, $sql, __FILE__, __LINE__);
    $row = pg_fetch_assoc($result);
    pg_free_result($result);
    $version = explode(' ', $row['version'], 3);
    $text = _("Postgresql version");
    $V .= "<tr><td>$text</td>";
    $V .= "<td align='right'>  $version[1] </td></tr>\n";;

    // Get the current query column name in pg_stat_activity
    if (strcmp($version[1], "9.2") >= 0) // when greater than PostgreSQL 9.2 replace "current_query" with "state"
      $current_query = "state";
    else
      $current_query = "current_query";

    /**** Query stats ****/
    // count current queries
    $sql = "SELECT count(*) AS val FROM pg_stat_activity";
    $result = pg_query($PG_CONN, $sql);
    DBCheckResult($result, $sql, __FILE__, __LINE__);
    $row = pg_fetch_assoc($result);
    $connection_count = '';
    $connection_count = $row['val'];
    pg_free_result($result);

    /**** Active connection count ****/
    $text = _("Active database connections");
    $V .= "<tr><td>$text</td>";
    $V .= "<td align='right'>" . number_format($connection_count,0,"",",") . "</td></tr>\n";;
    $sql = "SELECT count(*) AS val FROM pg_stat_activity WHERE $current_query != '<IDLE>' AND datname = 'fossology';";
    $result = pg_query($PG_CONN, $sql);
    DBCheckResult($result, $sql, __FILE__, __LINE__);
    $row = pg_fetch_assoc($result);
    $item_count = $row['val'];
    pg_free_result($result);

    $V .= "</table>\n";

    return $V;
  }


  /**
   * \brief Database queries
   * \returns html table containing query strings, pid, and start time
   */
  function DatabaseQueries()
  {
    global $PG_CONN;

    $V = "<table border=1>\n";
    $head1 = _("PID");
    $head2 = _("Query");
    $head3 = _("Started");
    $head4 = _("Elapsed");
    $V .= "<tr><th>$head1</th><th>$head2</th><th>$head3</th><th>$head4</th></tr>\n";

    /**** Version ****/
    $sql = "SELECT * from version();";
    $result = pg_query($PG_CONN, $sql);
    DBCheckResult($result, $sql, __FILE__, __LINE__);
    $row = pg_fetch_assoc($result);
    pg_free_result($result);
    $version = explode(' ', $row['version'], 3);

    // Get the current query column name in pg_stat_activity
    if (strcmp($version[1], "9.2") >= 0) // when greater than PostgreSQL 9.2 replace "current_query" with "state"
      $current_query = "state";
    else
      $current_query = "current_query";

    $sql = "SELECT procpid, $current_query, query_start, now()-query_start AS elapsed FROM pg_stat_activity WHERE $current_query != '<IDLE>' AND datname = 'fossology' ORDER BY procpid;"; 
    $result = pg_query($PG_CONN, $sql);
    DBCheckResult($result, $sql, __FILE__, __LINE__);
    if (pg_num_rows($result) > 1)
    {
      while ($row = pg_fetch_assoc($result))
      {
        if ($row[$current_query] == $sql) continue;  // Don't display this query
        $V .= "<tr>";
        $V .= "<td>$row[procpid]</td>";
        $V .= "<td>" . htmlspecialchars($row[$current_query]) . "</td>";
        $StartTime = substr($row['query_start'], 0, 19);
        $V .= "<td>$StartTime</td>";
        $V .= "<td>$row[elapsed]</td>";
        $V .= "</tr>\n";
      }
    }
    else
      $V .= "<tr><td colspan=4>There are no active FOSSology queries</td></tr>";

    pg_free_result($result);
    $V .= "</table>\n";

    return $V;
  }


  /**
   * \brief Determine amount of free disk space.
   */
  function DiskFree()
  {
    global $SYSCONFDIR;
    global $SysConf;

    $Cmd = "df -hP";
    $Buf = DoCmd($Cmd);

    /* Separate lines */
    $Lines = explode("\n",$Buf);

    /* Display results */
    $V = "";
    $V .= "<table border=1>\n";
    $head0 = _("Filesystem");
    $head1 = _("Capacity");
    $head2 = _("Used");
    $head3 = _("Available");
    $head4 = _("Percent Full");
    $head5 = _("Mount Point");
    $V .= "<tr><th>$head0</th><th>$head1</th><th>$head2</th><th>$head3</th><th>$head4</th><th>$head5</th></tr>\n";
    $headerline = true;
    foreach($Lines as $L)
    {
      // Skip top header line
      if ($headerline)
      {
        $headerline = false;
        continue;
      }

      if (empty($L)) { continue; }
      $L = trim($L);
      $L = preg_replace("/[[:space:]][[:space:]]*/"," ",$L);
      $List = explode(" ",$L);

      // Skip some filesystems we are not interested in
      if ($List[0] == 'tmpfs') continue;
      if ($List[0] == 'udev') continue;
      if ($List[0] == 'none') continue;
      if ($List[5] == '/boot') continue;

      $V .= "<tr><td>" . htmlentities($List[0]) . "</td>";
      $V .= "<td align='right' style='border-right:none'>$List[1]</td>";
      $V .= "<td align='right' style='border-right:none'>$List[2]</td>";
      $V .= "<td align='right' style='border-right:none'>$List[3]</td>";

      // Warn if running out of disk space
      $PctFull = (int)$List[4];
      $WarnAtPct = 90;  // warn the user if they exceed this % full
      if ($PctFull > $WarnAtPct)
        $mystyle = "style=border-right:none;background-color:red";
      else
        $mystyle = "style='border-right:none'";
      $V .= "<td align='right' $mystyle>$List[4]</td>";

      $V .= "<td align='left'>" . htmlentities($List[5]) . "</td></tr>\n";
    }
    $V .= "</table>\n";

    /***  Print out important file paths so users can interpret the above "df"  ***/
    $V .= _("Note:") . "<br>";
    $Indent = "&nbsp;&nbsp;";

    // File path to the database
    $V .= $Indent . _("Database"). ": " . "&nbsp;";
    // Get the database data_directory.  If we were the superuser we could
    // just query the database with "show data_directory", but we are not.
    // So try to get it from a ps and parse the -D argument
    $Cmd = "ps -eo cmd | grep postgres | grep -- -D";
    $Buf = DoCmd($Cmd);
    // Find the -D
    $DargToEndOfStr = trim(substr($Buf, strpos($Buf, "-D") + 2 ));
    $DargArray = explode(' ', $DargToEndOfStr);
    $V .= $DargArray[0] . "<br>";

    // Repository path
    $V .= $Indent . _("Repository") . ": " . $SysConf['FOSSOLOGY']['path'] . "<br>";

    // FOSSology config location
    $V .= $Indent . _("FOSSology config") . ": " . $SYSCONFDIR . "<br>";

    return($V);
  } // DiskFree()

  /**
   * \brief Generate output.
   */
  function Output() {
    if ($this->State != PLUGIN_STATE_READY) { return; }
    $V="";

    switch($this->OutputType)
    {
      case "XML":
        break;
      case "HTML":
        $V .= "<table border=0 width='100%'><tr>\n";
        $V .= "<td valign='top'>\n";
        $text = _("Database Contents");
        $V .= "<h2>$text</h2>\n";
        $V .= $this->DatabaseContents();
        $V .= "</td>";
        $V .= "<td valign='top'>\n";
        $text = _("Database Metrics");
        $V .= "<h2>$text</h2>\n";
        $V .= $this->DatabaseMetrics();
        $V .= "</td>";
        $V .= "</tr></table>\n";
        $text = _("Active FOSSology queries");
        $V .= "<h2>$text</h2>\n";
        $V .= $this->DatabaseQueries();
        $text = _("Disk Space");
        $V .= "<h2>$text</h2>\n";
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

}
$NewPlugin = new dashboard;
$NewPlugin->Initialize();
