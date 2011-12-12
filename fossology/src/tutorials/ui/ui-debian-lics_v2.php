<?php
/***********************************************************
 Copyright (C) 2009-2011 Hewlett-Packard Development Company, L.P.

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

class debian_lics extends FO_Plugin
{
  public $Name       = "debian_lics";
  public $Title      = "License Histogram For All Debian Uploads";
  public $MenuList   = "Jobs::Analyze::License Histogram for Debian Uploads";
  public $Version    = "2.0";
  public $Dependency = array();
  public $DBaccess   = PLUGIN_DB_READ;

  /**
   * \brief Generate the text for this plugin.
   */
  function Output()
  {
    if ($this->State != PLUGIN_STATE_READY) {
      return;
    }
    global $PG_CONN;
    $V="";
    switch($this->OutputType)
    {
      case "XML":
        break;
      case "HTML":
        /* Get uploadtree_pk's for all debian uploads */
        $sql = "SELECT uploadtree_pk, upload_pk, upload_filename FROM upload INNER JOIN uploadtree ON upload_fk=upload_pk AND parent IS NULL WHERE upload_filename LIKE '%debian%';";
        $result = pg_query($PG_CONN, $sql);
        DBCheckResult($result, $sql, __FILE__, __LINE__);
        $row = pg_fetch_assoc($result);
        if (empty($row['upload_pk']))
        {
          $V .= "There are no uploads with 'debian' in the description.";
        }
        else
        {
          /* Loop thru results to obtain all licenses in their uploadtree recs*/
          $Lics = array();
          while ($Row = pg_fetch_array($result))
          {
            if (empty($Row['upload_pk'])) {
              continue;
            }
            else { LicenseGetAll($Row[uploadtree_pk], $Lics);
            }
            $V .= "<option value='" . $Row['upload_pk'] . "'>$Name</option>\n";
          }
          $V .= "</select><P />\n";
          arsort($Lics);
          $V .= "<table border=1>\n"; foreach($Lics as $key => $value)
          {
            if ($key==" Total ")
            {
              $V .= "<tr><th>$key<th>$value\n";
            }
            else
            if (plugin_find_id('search_file_by_license') >= 0)
            {
              $V .= "<tr><td><a href='/repo/?mod=search_file_by_license&item=$Row[uploadtree_pk]&lic=" . urlencode($key) . "'>$key</a><td align='right'>$value\n";
            }
            else { $V .= "<tr><td>$key<td align='right'>$value\n";
            }
          }
          $V .= "</table>\n";
          //	  print "<pre>"; print_r($Lics); print "</pre>";
        }
        pg_free_result($result);
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
  }
};
$NewPlugin = new debian_lics;
$NewPlugin->Initialize();
?>
