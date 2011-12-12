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
  public $Title      = "License Histogram For Uploads";
  public $MenuList   = "Jobs::Analyze::License Histogram Uploads";
  public $Version    = "3.0";
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
        $Filename = GetParm("filename",PARM_STRING);
        $Uri = preg_replace("/&filename=[^&]*/","",Traceback());

        /* Prompt for the string to search for */
        $V .= "<form action='$Uri' method='POST'>\n";
        $V .= "<ul>\n";
        $V .= "<li>Enter the string to search for:<P>";
        $V .= "<INPUT type='text' name='filename' size='40' value='" . htmlentities($Filename) . "'>\n";
        $V .= "</ul>\n";
        $V .= "<input type='submit' value='Search!'>\n";
        $V .= "</form>\n";

        if (!empty($Filename))
        {
          /* Get uploadtree_pk's for all debian uploads */
          $sql = "SELECT uploadtree_pk, upload_pk, upload_filename FROM upload INNER JOIN uploadtree ON upload_fk=upload_pk AND parent IS NULL WHERE upload_filename LIKE '%$Filename%';";
          $result = pg_query($PG_CONN, $sql);
          DBCheckResult($result, $sql, __FILE__, __LINE__);
          $row = pg_fetch_assoc($result);
          if (empty($row['upload_pk']))
          {
            $V = "There are no uploads with $Filename in the description.";
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
              $V = "<option value='" . $Row['upload_pk'] . "'>$Name</option>\n";
            }
            pg_free_result($result);
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
        }
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
