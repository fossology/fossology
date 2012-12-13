<?php
/***********************************************************
 Copyright (C) 2012 Hewlett-Packard Development Company, L.P.

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

/**
 * \file ajax-manage-tag.php
 * \brief get the status of one upload, if have enabled/disabled on a upload
 */

define("TITLE_upload_tagging", _("Manage Upload Tagging"));

class upload_tagging extends FO_Plugin
{
  var $Name       = "upload_tagging";
  var $Title      = TITLE_upload_tagging;
  var $Version    = "1.0";
  var $Dependency = array();
  var $DBaccess   = PLUGIN_DB_USERADMIN;
  var $NoHTML     = 1; /* This plugin needs no HTML content help */

  /**
   * \brief Display the loaded menu and plugins.
   */
  function Output()
  {
    if ($this->State != PLUGIN_STATE_READY) {
      return;
    }
    $V="";
    global $Plugins;
    switch($this->OutputType)
    {
      case "XML":
        break;
      case "HTML":
        $upload_id= GetParm("upload",PARM_INTEGER);
        if (empty($upload_id)) {
          break;
        }

        global $PG_CONN;

        /** check if this upload has been disabled */
        $sql = "select * from tag_manage where upload_fk = $upload_id and is_disabled = true;";
        $result = pg_query($PG_CONN, $sql);
        DBCheckResult($result, $sql, __FILE__, __LINE__);
        $count = pg_num_rows($result);
        pg_free_result($result);
        if (empty($count)) // enabled
        {
          $text = _("Disable");
          $V = "<input type='submit' name='manage'  value='$text'>\n";
        }
        else // disabled
        {
          $text = _("Enable");
          $V = "<input type='submit' name='manage' value='$text'>\n";
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
    print("$V");
    return;
  } // Output()


};
$NewPlugin = new upload_tagging;
$NewPlugin->Initialize();

?>
