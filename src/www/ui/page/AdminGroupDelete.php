<?php
/***********************************************************
 Copyright (C) 2013 Hewlett-Packard Development Company, L.P.
 Copyright (C) 2014, Siemens AG

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

namespace Fossology\UI\Page;

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
          
  function __construct(){
        parent::__construct(self::NAME, array(
        self::TITLE =>  _("Delete Group"),
        self::MENU_LIST => "Admin::Groups::Delete Group",
        self::PERMISSION => self::PERM_WRITE
    ));
    $this->LoginFlag = 1;  /* Don't allow Default User to add a group */
  }
  
  /**
   * @param Request $request
   * @return Response
   */
  protected function handle(Request $request)
  {
    global $SysConf;
    $userId = $SysConf['auth']['UserId'];
    $vars = array();

    /** @var UserDao $userDao */
    $userDao = $this->getObject('dao.user');
    $groupMap = $userDao->getDeletableAdminGroupMap($userId);

    $groupId = $request->get('grouppk');
    if (!empty($groupId)) 
    {
      try
      {
        $userDao->deleteGroup($groupId);
        $vars['message'] = _("Group").' '.$groupMap[$groupId]. ' '. _("deleted").'.';
        unset($groupMap[$groupId]);
      }
      catch (\Exception $e)
      {
        $vars['message'] = $e->getMessage();
      }
    }

    if (empty($groupMap))
    {
      $vars['content'] = _("You have no groups you can delete.");
      return $this->render('include/base.html.twig',$this->mergeWithDefault($vars));
    }
    $vars['groupMap'] = $groupMap;
    $vars['uri'] = Traceback_uri() ."?mod=group_delete";
    $vars['groupMap'] = $groupMap;
    return $this->render('admin_group_delete.html.twig',$this->mergeWithDefault($vars));
  }
}

register_plugin(new AdminGroupDelete());
