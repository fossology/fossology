<?php
/***********************************************************
 Copyright (C) 2008-2013 Hewlett-Packard Development Company, L.P.
 Copyright (C) 2015 Siemens AG

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

use Fossology\Lib\Db\DbManager;

class dashboard extends FO_Plugin
{
  protected $pgVersion;
  
  /** @var DbManager */
  private $dbManager;
  
  function __construct()
  {
    $this->Name       = "dashboard";
    $this->Title      = TITLE_dashboard;
    $this->MenuList   = "Admin::Dashboard";
    $this->DBaccess   = PLUGIN_DB_ADMIN;
    parent::__construct();
    $this->dbManager = $GLOBALS['container']->get('db.manager');
  }

  /**
   * \brief Return each html row for DatabaseContents()
   * \returns html table row
   */
  function DatabaseContentsRow($TableName, $TableLabel)
  {
    $row = $this->dbManager->getSingleRow(
      "select sum(reltuples) as val from pg_class where relname like $1 and reltype !=0",
      array($TableName),
      __METHOD__);
    $item_count = $row['val'];

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

function GetLastAnalyzeTimeOrVacTime($queryPart,$TableName)
{
  $sql = "select greatest($queryPart) as lasttime from pg_stat_all_tables where schemaname = 'public' and relname like $1";
  $row = $this->dbManager->getSingleRow($sql, array($TableName), __METHOD__);

  return $row["lasttime"];
}

function GetLastVacTime($TableName)
{
  return $this->GetLastAnalyzeTimeOrVacTime("last_vacuum, last_autovacuum",$TableName);
}
  
function GetLastAnalyzeTime($TableName)
{
  return $this->GetLastAnalyzeTimeOrVacTime("last_analyze, last_autoanalyze",$TableName);
}


  /**
   * \brief Database metrics
   * \returns html table containing metrics
   */
  function DatabaseMetrics()
  {

    $V = "<table border=1>\n";
    $text = _("Metric");
    $text1 = _("Total");
    $V .= "<tr><th>$text</th><th>$text1</th></tr>\n";

    /* Database size */
    $sql = "SELECT pg_database_size('fossology') as val;";
    $row = $this->dbManager->getSingleRow($sql, array(), __METHOD__."get_Size");
    $Size = HumanSize($row['val']);
    $text = _("FOSSology database size");
    $V .= "<tr><td>$text</td>";
    $V .= "<td align='right'> $Size </td></tr>\n";

    /**** Version ****/
    $text = _("Postgresql version");
    $V .= "<tr><td>$text</td>";
    $V .= "<td align='right'> {$this->pgVersion[server]} </td></tr>\n";

    /**** Query stats ****/
    // count current queries
    $sql = "SELECT count(*) AS val FROM pg_stat_activity";
    $row = $this->dbManager->getSingleRow($sql, array(), __METHOD__."get_connection_count");
    $connection_count = $row['val'];

    /**** Active connection count ****/
    $current_query = (strcmp($this->pgVersion['server'], "9.2") >= 0) ? "state" : "current_query";
    $text = _("Active database connections");
    $V .= "<tr><td>$text</td>";
    $V .= "<td align='right'>" . number_format($connection_count,0,"",",") . "</td></tr>\n";

    $V .= "</table>\n";

    return $V;
  }


  /**
   * \brief Database queries
   * \returns html table containing query strings, pid, and start time
   */
  function DatabaseQueries()
  {
    $V = "<table border=1>\n";
    $head1 = _("PID");
    $head2 = _("Query");
    $head3 = _("Started");
    $head4 = _("Elapsed");
    $V .= "<tr><th>$head1</th><th>$head2</th><th>$head3</th><th>$head4</th></tr>\n";

    $current_query = (strcmp($this->pgVersion['server'], "9.2") >= 0) ? "state" : "current_query";
    $procpid = (strcmp($this->pgVersion['server'], "9.2") >= 0) ? "pid" : "procpid";
    $sql = "SELECT $procpid processid, $current_query, query_start, now()-query_start AS elapsed FROM pg_stat_activity WHERE $current_query != '<IDLE>' AND datname = 'fossology' ORDER BY $procpid"; 

    $statementName = __METHOD__."queryFor_".$current_query."_orderBy_".$procpid;
    $this->dbManager->prepare($statementName,$sql);
    $result = $this->dbManager->execute($statementName, array());
    
    if (pg_num_rows($result) > 1)
    {
      while ($row = pg_fetch_assoc($result))
      {
        if ($row[$current_query] == $sql) continue;  // Don't display this query
        $V .= "<tr>";
        $V .= "<td>$row[processid]</td>";
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
    $Buf = $this->DoCmd($Cmd);

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
    $Buf = $this->DoCmd($Cmd);
    // Find the -D
    $DargToEndOfStr = trim(substr($Buf, strpos($Buf, "-D") + 2 ));
    $DargArray = explode(' ', $DargToEndOfStr);
    $V .= $DargArray[0] . "<br>";

    // Repository path
    $V .= $Indent . _("Repository") . ": " . $SysConf['FOSSOLOGY']['path'] . "<br>";

    // FOSSology config location
    $V .= $Indent . _("FOSSology config") . ": " . $SYSCONFDIR . "<br>";

    return($V);
  }
  
  public function Output() {
    global $PG_CONN;
    $this->pgVersion = pg_version($PG_CONN);
    
    $V="";
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

    return $V;
  }
  
  /**
   * \brief execute a shell command
   * \param $cmd - command to execute
   * \return command results
   */
  protected function DoCmd($cmd)
  {
    $fin = popen($cmd,"r");
    $buffer = "";
    while (!feof($fin)) {
      $buffer .= fread($fin, 8192);
    }
    pclose($fin);
    return $buffer;
  }
}

$NewPlugin = new dashboard;
$NewPlugin->Initialize();
