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

define("TITLE_debug_user", _("Debug User"));

class debug_user extends FO_Plugin
{
  var $Name       = "debug_user";
  var $Version    = "1.0";
  var $Title      = TITLE_debug_user;
  var $MenuList   = "Help::Debug::Debug User";
  var $Dependency = array();
  var $DBaccess   = PLUGIN_DB_ADMIN;

  /**
   * \brief Generate output.
   */
  function Output()
  {
    if ($this->State != PLUGIN_STATE_READY) {
      return;
    }
    $V="";
    global $PG_CONN;
    switch($this->OutputType)
    {
      case "XML":
        break;
      case "HTML":
        $sql = "SELECT * FROM users WHERE user_pk = '" . @$_SESSION['UserId'] . "';";
        $result = pg_query($PG_CONN, $sql);
        DBCheckResult($result, $sql, __FILE__, __LINE__);
        $R = pg_fetch_assoc($result);
        pg_free_result($result);
        $text = _("User Information");
        $V .= "<H2>$text</H2>\n";
        $V .= "<table border=1>\n";
        $text = _("Field");
        $text1 = _("Value");
        $V .= "<tr><th>$text</th><th>$text1</th></tr>\n";
        foreach($R as $Key => $Val)
        {
          if (empty($Key)) {
            continue;
          }
          $V .= "<tr><td>" . htmlentities($Key) . "</td><td>" . htmlentities($Val) . "</td></tr>\n";
        }
        $V .= "</table>\n";
        break;
      case "Text":
        break;
      default:
        break;
    }
    if (!$this->OutputToStdout) {
      return($V);
    }
    print($V);
    return;
  } // Output()

};
$NewPlugin = new debug_user;
$NewPlugin->Initialize();
?>
