<?php
/***********************************************************
 Copyright (C) 2008-2011 Hewlett-Packard Development Company, L.P.

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

define("TITLE_admin_db_vacuum", _("Database Vacuum and Analyze"));

class admin_db_vacuum extends FO_Plugin
{
  var $Name       = "admin_db_vacuum";
  var $Version    = "1.0";
  var $Title      = TITLE_admin_db_vacuum;
  var $MenuList   = "Admin::Database::Vacuum and Analyze";
  var $Dependency = array();
  var $DBaccess   = PLUGIN_DB_USERADMIN;

  /**
   * \brief Fix the DB by deleting offending records.
   */
  function FixDB	($CheckType)
  {
    global $PG_CONN;
    $text = _("Deleting");
    print $text . $CheckType['label'] . "...";
    $sql = "DELETE ". $CheckType['sql'];
    $result = pg_query($PG_CONN, $sql);
    DBCheckResult($result, $sql, __FILE__, __LINE__);
    $text=_("cleaned.");
    print   pg_affected_rows($result) . "$text<br>";
    pg_free_result($result);
  } // FixDB()

  /**
   * \brief Generate output.
   */
  function Output()
  {
    if ($this->State != PLUGIN_STATE_READY) { return; }
    $V="";
    global $PG_CONN;
    switch($this->OutputType)
    {
      case "XML":
        break;
      case "HTML":
        $Action = "";
        if (GetParm('vacuum',PARM_INTEGER) == 1) { $Action .= "VACUUM"; }
        if (GetParm('analyze',PARM_INTEGER) == 1) { $Action .= " ANALYZE"; }
        $sql = "SELECT table_name AS table
		FROM information_schema.tables
		WHERE table_type = 'BASE TABLE'
		AND table_schema = 'public'
		ORDER BY table_name
		;";
        $result = pg_query($PG_CONN, $sql);
        DBCheckResult($result, $sql, __FILE__, __LINE__);
        if (!empty($Action) && !empty($result))
        {
          $text = _("Cleaning: Vacuum and Analyze");
          print "<b>$text</b><br>\n";
          flush();
          while (($row = pg_fetch_assoc($result)) and !empty($row['table']))
          {
            print "$Action for " . $row['table'] . "<br>\n";
            flush();
            $sql = $Action . " " . $row['table'] . ";"; 
            $result1 = pg_query($PG_CONN, $sql);
            DBCheckResult($result1, $sql, __FILE__, __LINE__);
            pg_free_result($result1);
          }
          print "<P>\n";
        }
        pg_free_result($result);

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
