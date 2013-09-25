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
   * \brief Database metrics
   * \returns html table containing information on the job queue
   */
  function JobQueueInfo()
  {
    global $PG_CONN;

        $V = "<table border=1>\n";
        $text = _("Queue Information");
        $text1 = _("Total");
        $V .= "<tr><th>$text</th><th>$text1</th></tr>\n";
        // Dynamically set hyperlinks based on showjobs plugin existence.
        $uri_showjobs = Traceback_uri() . "?mod=showjobs";
        $showjobs_exists = &$Plugins[plugin_find_id("showjobs")]; /* may be null */

        $sql = "SELECT COUNT(DISTINCT jq_job_fk) AS val FROM jobqueue WHERE jq_endtime IS NULL OR jq_end_bits = 2;";
        $result = pg_query($PG_CONN, $sql);
        DBCheckResult($result, $sql, __FILE__, __LINE__);
        $row = pg_fetch_assoc($result);
        $item_count = '';
        $item_count = $row['val'];
        pg_free_result($result);
        if ($showjobs_exists) {
          $text = _("Pending Analysis Jobs");
          $V .= "<tr><td><a href='$uri_showjobs'>$text</a></td> ";
        }
        else {
          $text = _("Pending Analysis Jobs");
          $V .= "<tr><td>$text</td>";
        }
        $V .= "<td align='right'>" . number_format($item_count,0,"",",") . "</td></tr>\n";;
        $sql = "SELECT COUNT(*) AS val FROM jobqueue WHERE jq_endtime IS NULL OR jq_end_bits = 2;";
        $result = pg_query($PG_CONN, $sql);
        DBCheckResult($result, $sql, __FILE__, __LINE__);
        $row = pg_fetch_assoc($result);
        $item_count = '';
        $item_count = $row['val'];
        pg_free_result($result);
        if ($showjobs_exists) {
          $text = _("Tasks in the Job Queue");
          $V .= "<tr><td><a href='$uri_showjobs'>$text</a></td> ";
        }
        else {
          $text = _("Tasks in the Job Queue");
          $V .= "<tr><td>$text</td>";
        }
        $V .= "<td align='right'>" . number_format($item_count,0,"",",") . "</td></tr>\n";
        $sql = "SELECT COUNT(*) AS val FROM jobqueue WHERE jq_endtime IS NULL AND jq_starttime IS NOT NULL;";
        $result = pg_query($PG_CONN, $sql);
        DBCheckResult($result, $sql, __FILE__, __LINE__);
        $row = pg_fetch_assoc($result);
        $item_count = '';
        $item_count = $row['val'];
        pg_free_result($result);
        if ($showjobs_exists) {
          $text = _("Running Tasks");
          $V .= "<tr><td><a href='$uri_showjobs'>$text</a></td> ";
        }
        else {
          $text = _("Running Tasks");
          $V .= "<tr><td>$text</td>";
        }
        $V .= "<td align='right'>" . number_format($item_count,0,"",",") . "</td></tr>\n";
        $sql = "SELECT COUNT(*) AS val FROM jobqueue WHERE jq_endtime IS NOT NULL AND jq_end_bits=2;";
        $result = pg_query($PG_CONN, $sql);
        DBCheckResult($result, $sql, __FILE__, __LINE__);
        $row = pg_fetch_assoc($result);
        $item_count = '';
        $item_count = $row['val'];
        pg_free_result($result);
        if ($showjobs_exists) {
          $text = _("Failed Tasks");
          $V .= "<tr><td><a href='$uri_showjobs'>$text</a></td> ";
        }
        else {
          $text = _("Failed Tasks");
          $V .= "<tr><td>$text</td>";
        }
        $V .= "<td align='right'>" . number_format($item_count,0,"",",") . "</td></tr>\n";
        $V .= "</table>\n";
        $V .= "</td>";
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

    $text = _("Metric");
    $text1 = _("Total");
    $V .= "<tr><th>$text</th><th>$text1</th></tr>\n";

    /**** Users ****/
    $sql = "SELECT count(*) AS val FROM users";
    $result = pg_query($PG_CONN, $sql);
    DBCheckResult($result, $sql, __FILE__, __LINE__);
    $row = pg_fetch_assoc($result);
    $item_count = $row['val'];
    pg_free_result($result);
    $text = _("Users");
    $V .= "<tr><td>$text</td>";
    $V .= "<td align='right'>" . number_format($item_count,0,"",",") . "</td></tr>\n";;

    /**** Uploads  ****/
    $sql = "SELECT count(*) AS val FROM upload;";
    $result = pg_query($PG_CONN, $sql);
    DBCheckResult($result, $sql, __FILE__, __LINE__);
    $row = pg_fetch_assoc($result);
    $item_count = $row['val'];
    pg_free_result($result);
    $text = _("Uploads");
    $V .= "<tr><td>$text</td>";
    $V .= "<td align='right'>" . number_format($item_count,0,"",",") . "</td></tr>\n";;

    /**** Unique pfiles  ****/
    // $sql = "SELECT count(*) AS val FROM pfile;";  too slow on big tables, use pg_class which will be accurate as of last ANALYZE
    $sql = "select reltuples as val from pg_class where relname='pfile'";
    $result = pg_query($PG_CONN, $sql);
    DBCheckResult($result, $sql, __FILE__, __LINE__);
    $row = pg_fetch_assoc($result);
    $item_count = $row['val'];
    pg_free_result($result);
    $text = _("Unique Files referenced in repository");
    $V .= "<tr><td>$text</td>";
    $V .= "<td align='right'>" . number_format($item_count,0,"",",") . "</td></tr>\n";;

    /**** uploadtree recs  ****/
    // $sql = "SELECT count(*) AS val FROM uploadtree;";  too slow on big tables, use pg_class which will be accurate as of last ANALYZE
    $sql = "select sum(reltuples) as val from pg_class where relname like 'uploadtree_%' and reltype !=0";
    $result = pg_query($PG_CONN, $sql);
    DBCheckResult($result, $sql, __FILE__, __LINE__);
    $row = pg_fetch_assoc($result);
    $item_count = $row['val'];
    pg_free_result($result);
    $text = _("Individual Files");
    $V .= "<tr><td>$text</td>";
    $V .= "<td align='right'>" . number_format($item_count,0,"",",") . "</td></tr>\n";;

    /**** License recs  ****/
    // $sql = "SELECT count(*) AS val FROM license_file;";  too slow on big tables, use pg_class which will be accurate as of last ANALYZE
    $sql = "select reltuples as val from pg_class where relname='license_file'";
    $result = pg_query($PG_CONN, $sql);
    DBCheckResult($result, $sql, __FILE__, __LINE__);
    $row = pg_fetch_assoc($result);
    $item_count = $row['val'];
    pg_free_result($result);
    $text = _("Discovered Licenses");
    $V .= "<tr><td>$text</td>";
    $V .= "<td align='right'>" . number_format($item_count,0,"",",") . "</td></tr>\n";;

    /**** Copyright recs  ****/
    // $sql = "SELECT count(*) AS val FROM copyright;";  too slow on big tables, use pg_class which will be accurate as of last ANALYZE
    $sql = "select reltuples as val from pg_class where relname='copyright'";
    $result = pg_query($PG_CONN, $sql);
    DBCheckResult($result, $sql, __FILE__, __LINE__);
    $row = pg_fetch_assoc($result);
    $item_count = $row['val'];
    pg_free_result($result);
    $text = _("Copyrights/URLs/Emails");
    $V .= "<tr><td>$text</td>";
    $V .= "<td align='right'>" . number_format($item_count,0,"",",") . "</td></tr>\n";;

    $V .= "</table>\n";

    return $V;
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
    $sql = "SELECT pg_size_pretty(pg_database_size('fossology')) as val;";
    $result = pg_query($PG_CONN, $sql);
    DBCheckResult($result, $sql, __FILE__, __LINE__);
    $row = pg_fetch_assoc($result);
    $Size = Bytes2Human($row['val'] * 1000000);
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
    $Cmd = "df -Pk `cat '$SYSCONFDIR/fossology/RepPath.conf'`/*/* | sort -u | grep '%'";
    $Buf = DoCmd($Cmd);

    /* Separate lines */
    $Lines = explode("\n",$Buf);

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
      $List = explode(" ",$L);
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

  /**
   * \brief Generate output.
   */
  function Output() {

    global $PG_CONN;
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
        $V .= $this->JobQueueInfo();
        $V .= "</td>";

        /**************************************************/
        $V .= "<td valign='top'>\n";
        $text = _("Database Contents");
        $V .= "<H2>$text</H2>\n";
        $V .= $this->DatabaseContents();
        $V .= "</td>";

        /**************************************************/
        $V .= "<td valign='top'>\n";
        $text = _("Database Metrics");
        $V .= "<H2>$text</H2>\n";
        $V .= $this->DatabaseMetrics();
        $V .= "</td>";

        /**************************************************/
        $V .= "</tr></table>\n";


        /**************************************************/
        $text = _("Active FOSSology queries");
        $V .= "<H2>$text</H2>\n";
        $V .= $this->DatabaseQueries();
        $V .= "</td>";

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
