<?php
/***********************************************************
 Copyright (C) 2010-2011 Hewlett-Packard Development Company, L.P.

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
 * \file ajax-perms.php
 * \brief This plugin is used to list all uploads associated
 * with a folder.  This is NOT intended to be a user-UI
 * plugin.
 * This is intended as an active plugin to provide support
 * data to the UI.
 */

define("TITLE_ajax_perms", _("List Perms"));

class ajax_perms extends FO_Plugin
{
  var $Name       = "perm_get";
  var $Title      = TITLE_ajax_perms;
  var $Version    = "1.3";
  var $Dependency = array();
  var $DBaccess   = PLUGIN_DB_READ;
  var $NoHTML     = 1; /* This plugin needs no HTML content help */

  /**
   * \brief Display the loaded menu and plugins.
   */
  function Output()
  {
    if ($this->State != PLUGIN_STATE_READY) { return; }
    $V="";
    global $PG_CONN;
    $tag_ns_pk = GetParm("tag_ns_pk",PARM_INTEGER);
    if (empty($tag_ns_pk)) { return;}
    switch($this->OutputType)
    {
      case "XML":
        break;
      case "HTML":
        $text = _("Existing Permissions:");
        $V .= "<h4>$text</h4>\n";
        $sql = "SELECT * FROM tag_ns_group,tag_ns,groups WHERE tag_ns_fk=tag_ns_pk AND group_fk = group_pk AND tag_ns_fk = $tag_ns_pk;";
        $result = pg_query($PG_CONN, $sql);
        DBCheckResult($result, $sql, __FILE__, __LINE__);
        if (pg_num_rows($result) > 0)
        {
          $V .= "<table border=1>\n";
          $text = _("Tag Namespace");
          $text1 = _("Group");
          $text2 = _("Permission");
          $V .= "<tr><th>$text</th><th>$text1</th><th>$text2</th><th></th></tr>\n";
          while ($row = pg_fetch_assoc($result))
          {
            $V .= "<tr><td align='center'>" . $row['tag_ns_name'] . "</td><td align='center'>" . $row['group_name'] . "</td><td align='center'>";
            $tag_ns_group_pk = $row['tag_ns_group_pk'];
            if ($row['tag_ns_perm'] > 2){
              $V .= _("Admin");
            }else if($row['tag_ns_perm'] > 1){
              $V .= _("Read/Write");
            }else if($row['tag_ns_perm'] > 0){
              $V .= _("Read Only");
            }else{
              $V .= _("None");
            }
            $V .= "</td><td align='center'><a href='" . Traceback_uri() . "?mod=admin_tag_ns_perm&action=delete&tag_ns_group_pk=$tag_ns_group_pk'>Delete</a></td></tr>\n";
          }
          $V .= "</table><p>\n";
        }else{
          $text = _("No Permission!");
          $V .= "<h5><font color=red>$text</font></h5>\n";
        }
        pg_free_result($result);

        $text = _("Add new Permission for this Tag Namespace:");
        $V .= "<h4>$text</h4>\n";
        $V .= "<form name='formy' method='POST' action='" . Traceback_uri() . "?mod=admin_tag_ns_perm&action=add&tag_ns_fk=$tag_ns_pk'>\n";
        $group_array = DB2KeyValArray("groups", "group_pk", "group_name", "");
        $select = Array2SingleSelect($group_array, "group_fk", "", false,false);
        $text = _("Select Group");
        $V .= "<h5>$text:$select</h5>\n";

        $perm_array = array();
        $perm_array[0] = "None";
        $perm_array[1] = "Read Only";
        $perm_array[2] = "Read/Write";
        $perm_array[3] = "Admin";
        $select = Array2SingleSelect($perm_array, "tag_ns_perm", "", false,false);
        $text = _("Select Permisssion");
        $V .= "<h5>$text:$select</h5>\n";

        $text = _("Add");
        $V .= "<input type='submit' value='$text'>\n";
        $V .= "</form>\n";
        break;
      case "Text":
        break;
      default:
        break;
    }
    if (!$this->OutputToStdout) { return($V); }
    print("$V");
    return;
  } // Output()
};
$NewPlugin = new ajax_perms;
$NewPlugin->Initialize();
?>
