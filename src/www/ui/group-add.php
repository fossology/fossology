<?php
use Fossology\Lib\Auth\Auth;
use Fossology\Lib\Dao\UserDao;
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
    $this->LoginFlag = 1;  
    parent::__construct();
  }
  /**
   * @brief Get list of existing groups
   * @return array Associative array of group_pk => group_name
   */
  private function getExistingGroups()
  {
    $userDao = $GLOBALS['container']->get('dao.user');
    return $userDao->getDeletableAdminGroupMap(Auth::getUserId(), $_SESSION[Auth::USER_LEVEL]);
  }
  public function Output()
  {
    $V = "";
    
    $groupname = GetParm('groupname', PARM_TEXT);
    if (!empty($groupname)) {
      try {
        $existingGroups = $this->getExistingGroups();
        $groupExists = false;
        foreach ($existingGroups as $groupId => $existingName) {
          if (strcasecmp($existingName, $groupname) === 0) {
            $groupExists = true;
            break;
          }
        }
        if ($groupExists) {
          $this->vars['message'] = _("Error: Group '$groupname' already exists.");
        } else {
          $userDao = $GLOBALS['container']->get('dao.user');
          $groupId = $userDao->addGroup($groupname);
          $userDao->addGroupMembership($groupId, Auth::getUserId());
          $text = _("Group");
          $text1 = _("added");
          $this->vars['message'] = "$text $groupname $text1.";
        }
      } catch (Exception $e) {
        $this->vars['message'] = $e->getMessage();
      }
    }
    
    $text = _("Add a Group");
    $V .= "<h4>$text</h4>\n";
    
    $V .= "<form name='formy' method='POST' action=" . Traceback_uri() . "?mod=group_add>\n";
    $Val = htmlentities(GetParm('groupname', PARM_TEXT), ENT_QUOTES);
    $text = _("Enter the groupname:");
    $V .= "$text\n";
    $V .= "<input type='text' value='$Val' name='groupname' size=20>\n";
    $text = _("Add");
    $V .= "<input type='submit' value='$text'>\n";
    $V .= "</form>\n";
    
    $V .= "<div class='existing-groups' style='margin-top: 20px;'>\n";
    $V .= "<p>" . _("Existing Groups:") . "</p>\n";
    $V .= "<table class='table-existing-groups' style='margin-left: 20px;'>\n";
    $V .= "<tr><th>" . _("Group Name") . "</th></tr>\n";
    
    $existingGroups = $this->getExistingGroups();
    if (!empty($existingGroups)) {
      foreach ($existingGroups as $groupId => $groupName) {
        $V .= "<tr><td>" . htmlspecialchars($groupName) . "</td></tr>\n";
      }
    } else {
      $V .= "<tr><td>" . _("No existing groups found.") . "</td></tr>\n";
    }
    $V .= "</table>\n";
    $V .= "</div>\n";
    
    return $V;
  }
}
$NewPlugin = new group_add;