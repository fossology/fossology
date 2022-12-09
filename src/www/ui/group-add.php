<?php

use Fossology\Lib\Auth\Auth;
/*
 SPDX-FileCopyrightText: © 2013 Hewlett-Packard Development Company, L.P.
 SPDX-FileCopyrightText: © 2015 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

define("TITLE_ADD_GROUP", _("Add Group"));

/**
 * \class group_add extends FO_Plugin
 * \brief add a new group
 */
class group_add extends FO_Plugin
{
  function __construct()
  {
    $this->Name = "group_add";
    $this->Title = TITLE_ADD_GROUP;
    $this->MenuList = "Admin::Groups::Add Group";
    $this->DBaccess = PLUGIN_DB_WRITE;
    $this->LoginFlag = 1;  /* Don't allow Default User to add a group */
    parent::__construct();
  }


  public function Output()
  {
    $V = "";
    /* If this is a POST, then process the request. */
    $groupname = GetParm('groupname', PARM_TEXT);
    if (! empty($groupname)) {
      try {
        /* @var $userDao UserDao */
        $userDao = $GLOBALS['container']->get('dao.user');
        $groupId = $userDao->addGroup($groupname);
        $userDao->addGroupMembership($groupId, Auth::getUserId());
        $text = _("Group");
        $text1 = _("added");
        $this->vars['message'] = "$text $groupname $text1.";
      } catch (Exception $e) {
        $this->vars['message'] = $e->getMessage();
      }
    }

    /* Build HTML form */
    $text = _("Add a Group");
    $V.= "<h4>$text</h4>\n";
    $V.= "<form name='formy' method='POST' action=" . Traceback_uri() ."?mod=group_add>\n";
    $Val = htmlentities(GetParm('groupname', PARM_TEXT), ENT_QUOTES);
    $text = _("Enter the groupname:");
    $V.= "$text\n";
    $V.= "<input type='text' value='$Val' name='groupname' size=20>\n";
    $text = _("Add");
    $V.= "<input type='submit' value='$text'>\n";
    $V.= "</form>\n";

    return $V;
  }
}
$NewPlugin = new group_add;
