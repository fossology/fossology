<?php
/*
 SPDX-FileCopyrightText: © 2025 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\UI\Page;

use Fossology\Lib\Auth\Auth;
use Fossology\Lib\Dao\UserDao;
use Fossology\Lib\Plugin\DefaultPlugin;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * \class AdminGroupEdit extends DefaultPlugin
 * \brief edit group name
 */
class AdminGroupEdit extends DefaultPlugin
{

  const NAME = 'group_edit';

  function __construct()
  {
    parent::__construct(self::NAME, array(
        self::TITLE => _("Edit Group"),
        self::MENU_LIST => "Admin::Groups::Edit Group",
        self::PERMISSION => Auth::PERM_ADMIN,
        self::REQUIRES_LOGIN => TRUE
    ));
  }

  /**
   * @param Request $request
   * @return Response
   */
  protected function handle(Request $request)
  {
    $userId = Auth::getUserId();
    $vars = array();

    /** @var UserDao $userDao */
    $userDao = $this->getObject('dao.user');
    $groupMap = $userDao->getDeletableAdminGroupMap($userId,
      $_SESSION[Auth::USER_LEVEL]);

    if (empty($groupMap)) {
      $vars['content'] = _("You have no groups you can edit.");
      return $this->render('include/base.html.twig', $this->mergeWithDefault($vars));
    }

    $groupId = $request->get('grouppk');
    $newGroupName = trim($request->get('newgroupname'));
    if (! empty($groupId) && ! empty($newGroupName)) {
      $validateGroup = $this->validateGroupName($newGroupName);
      if (empty($validateGroup)) {
        try {
          $escapedGroupName = htmlspecialchars(strip_tags($newGroupName), ENT_QUOTES, 'UTF-8');
          $userDao->editGroup($groupId, $escapedGroupName);
          $vars['message'] = _("Group") . ' ' . $groupMap[$groupId] . ' ' . _("edited to ".$escapedGroupName ) . '.';
          $groupMap[$groupId] = $escapedGroupName;
        } catch (\Exception $e) {
          $vars['message'] = $e->getMessage();
        }
      } else {
        $vars['message'] = $validateGroup;
      }
    }

    $vars['groupMap'] = $groupMap;
    $vars['uri'] = Traceback_uri() . "?mod=group_edit";
    return $this->render('admin_group_edit.html.twig', $this->mergeWithDefault($vars));
  }

  /**
   * \brief validateGroupName.
   * verify if the group is empty or numeric
   * @param string $groupName
   * @return empty on success
   */
  function validateGroupName($groupName)
  {
    if (empty($groupName)) {
      return _("Invalid: Group name cannot be whitespace only");
    } else if (preg_match('/^[\s\w_-]$/', $groupName) !== 1) {
      return _("Invalid: Group name can only contain letters, numbers, hyphens and underscores");
    } else if (is_numeric($groupName)) {
      return _("Invalid: Group name cannot be numeric-only");
    } else {
      return "";
    }
  }
}

register_plugin(new AdminGroupEdit());
