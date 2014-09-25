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
  function __construct()
  {
    $this->Name = "group_delete";
    $this->Title = TITLE_delete_group;
    $this->MenuList = "Admin::Groups::Delete Group";
    $this->DBaccess = PLUGIN_DB_WRITE;
    $this->LoginFlag = 1;  /* Don't allow Default User to add a group */
    parent::__construct();
  }



  protected function htmlContent() 
  {
    global $SysConf;

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
        $this->vars['message'] = "$text {$GroupArray[$Group]} $text1.";
      } 
      else 
      {
        $this->vars['message'] = $rc;
      }
    }

    /* Build HTML form */
    $text = _("Delete a Group");
    $V.= "<h4>$text</h4>\n";
    $V.= "<form name='formy' method='POST' action=" . Traceback_uri() ."?mod=group_delete>\n";
    $UserArray = Table2Array('user_pk', 'user_name', 'users');

    /* Remove from $GroupArray any active users.  A user must always have a group by the same name */
    foreach($GroupArray as $group_fk => $group_name)
    {
      if (array_search($group_name, $UserArray)) unset($GroupArray[$group_fk]);
    }

    if (empty($GroupArray))
    {
      $text = _("You have no groups you can delete.");
      return "<p>$text<p>";
    }
    reset($GroupArray);
    if (empty($group_pk)) $group_pk = key($GroupArray);

    $V .= _("Select the group to delete").":  \n";

    /* Display group select list, on change request new page with group= in url */
    $V .= Array2SingleSelect($GroupArray, "grouppk", $group_pk, false, false);

    $text = _("Delete");
    $V.= "<input type='submit' value='$text'>\n";
    $V.= "</form>\n";

    return $V;
  }
}
$NewPlugin = new group_delete;
