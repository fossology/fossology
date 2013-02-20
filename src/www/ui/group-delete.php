<?php
/***********************************************************
 Copyright (C) 2013 Hewlett-Packard Development Company, L.P.

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

define("TITLE_delete_group", _("Delete Group"));

/**
 * \class group_add extends FO_Plugin
 * \brief add a new group
 */
class group_delete extends FO_Plugin {
  var $Name = "group_delete";
  var $Title = TITLE_delete_group;
  var $MenuList = "Admin::Groups::Delete Group";
  var $Dependency = array();
  var $DBaccess = PLUGIN_DB_WRITE;
  var $LoginFlag = 1;  /* Don't allow Default User to add a group */


  /**
   * \brief Generate the text for this plugin.
   */
  function Output() 
  {
    global $PG_CONN;
    global $SysConf;

    if ($this->State != PLUGIN_STATE_READY)  return;

    $user_pk = $SysConf['auth']['UserId'];

    /* Get array of groups that this user is an admin of */
    $GroupArray = GetGroupArray($user_pk);

    $V = "";
    /* If this is a POST, then process the request. */
    $Group = GetParm('grouppk', PARM_TEXT);
    if (!empty($Group)) 
    {
      $rc = DeleteGroup($Group);
      if (empty($rc)) 
      {
        /* Need to refresh the screen */
        $text = _("Group");
        $text1 = _("Deleted");
        $V.= displayMessage("$text {$GroupArray[$Group]} $text1.");
      } 
      else 
      {
        $V.= displayMessage($rc);
      }
    }

    /* Build HTML form */
    $text = _("Delete a Group");
    $V.= "<h4>$text</h4>\n";
    $V.= "<form name='formy' method='POST' action=" . Traceback_uri() ."?mod=group_delete>\n";

    /* Get array of users */
    $UserArray = Table2Array('user_pk', 'user_name', 'users');

    /* Remove from $GroupArray any active users.  A user must always have a group by the same name */
    foreach($GroupArray as $group_fk => $group_name)
    {
      if (array_search($group_name, $UserArray)) unset($GroupArray[$group_fk]);
    }

    if (empty($GroupArray))
    {
      $text = _("You have no groups you can delete.");
      echo "<p>$text<p>";
      return;
    }
    reset($GroupArray);
    if (empty($group_pk)) $group_pk = key($GroupArray);

    $text = _("Select the group to delete:  \n");
    $V.= "$text";

    /*** Display group select list, on change request new page with group= in url ***/
    $V .= Array2SingleSelect($GroupArray, "grouppk", $group_pk, false, false);

    $text = _("Delete");
    $V.= "<input type='submit' value='$text'>\n";
    $V.= "</form>\n";

    if (!$this->OutputToStdout)  return ($V);

    print ("$V");
    return;
  }
};
$NewPlugin = new group_delete;
?>
