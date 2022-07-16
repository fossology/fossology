<?php
/*
 SPDX-FileCopyrightText: © 2013 Hewlett-Packard Development Company, L.P.
 SPDX-FileCopyrightText: © 2014-2015 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\UI\Page;

use Fossology\Lib\Auth\Auth;
use Fossology\Lib\Dao\UserDao;
use Fossology\Lib\Plugin\DefaultPlugin;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * \class group_manage extends FO_Plugin
 * \brief edit group user permissions
 */
class AdminGroupDelete extends DefaultPlugin
{

  const NAME = 'group_delete';

  function __construct()
  {
    parent::__construct(self::NAME, array(
        self::TITLE => _("Delete Group"),
        self::MENU_LIST => "Admin::Groups::Delete Group",
        self::PERMISSION => Auth::PERM_WRITE,
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

    $groupId = $request->get('grouppk');
    if (! empty($groupId)) {
      try {
        $userDao->deleteGroup($groupId);
        $vars['message'] = _("Group") . ' ' . $groupMap[$groupId] . ' ' . _("deleted") . '.';
        unset($groupMap[$groupId]);
      } catch (\Exception $e) {
        $vars['message'] = $e->getMessage();
      }
    }

    if (empty($groupMap)) {
      $vars['content'] = _("You have no groups you can delete.");
      return $this->render('include/base.html.twig', $this->mergeWithDefault($vars));
    }
    $vars['groupMap'] = $groupMap;
    $vars['uri'] = Traceback_uri() . "?mod=group_delete";
    $vars['groupMap'] = $groupMap;
    return $this->render('admin_group_delete.html.twig', $this->mergeWithDefault($vars));
  }
}

register_plugin(new AdminGroupDelete());
