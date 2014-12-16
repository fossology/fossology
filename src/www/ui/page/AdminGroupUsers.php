<?php
/***********************************************************
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
class AdminGroupUsers extends DefaultPlugin {
  var $groupPermissions = array(-1 => "None", UserDao::USER=>"User", UserDao::ADMIN=>"Admin", UserDao::ADVISOR=>"Advisor");
  const NAME = 'group_manage_users';
          
  function __construct(){
        parent::__construct(self::NAME, array(
        self::TITLE => _("Manage Group Users"),
        self::MENU_LIST => "Admin::Groups::Manage Group Users",
        self::DEPENDENCIES => array(\ui_menu::NAME),
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
        /** @var UserDao */
    $userDao = $this->getObject('dao.user');
    $groupMap = $userDao->getAdminGroupMap($userId,@$_SESSION['UserLevel']);
    if (empty($groupMap))
    {
      $text = _("You have no permission to manage any group.");
      return $this->render('include/base.html.twig', $this->mergeWithDefault(array('message'=>$text)));
    }
    /** @var DbManager */
    $dbManager = $this->getObject('db.manager');
    $group_pk = intval($request->get('group'));
    if (empty($group_pk) || !array_key_exists($group_pk, $groupMap))
    {
      $group_pk = key($groupMap);
    }

    $gum_pk = intval($request->get('gum_pk'));
    if ($gum_pk)
    {
      $perm = intval($request->get('perm'));
      $this->updateGUMPermission($gum_pk,$perm);
      $groupMap = $userDao->getAdminGroupMap($userId,@$_SESSION['UserLevel']);
    }

    $newuser = intval($request->get('newuser'));
    $newperm = intval($request->get('newperm'));

    if ($newuser && $group_pk)
    {
      // do not produce duplicate
      $dbManager->prepare($stmt=__METHOD__.".delByGroupAndUser",
              "delete from group_user_member where group_fk=$1 and user_fk=$2");
      $dbManager->freeResult($dbManager->execute($stmt,array($group_pk,$newuser)));
      if ($newperm >= 0)
      {
        $dbManager->prepare($stmt=__METHOD__.".insertGUP",
                "insert into group_user_member (group_fk, user_fk, group_perm) values ($1,$2,$3)");
        $dbManager->freeResult($dbManager->execute($stmt,array($group_pk, $newuser, $newperm)));
      }
      if ($newuser == $userId)
      {
        $groupMap = $userDao->getAdminGroupMap($userId,@$_SESSION['UserLevel']);
      }
      $newperm = $newuser = 0;
    }

    natcasesort($groupMap);
    $baseUrl = Traceback_uri() . "?mod=".$this->getName() .'&group=';
    $onchange = "onchange=\"js_url(this.value, '$baseUrl')\"";
    $baseUrl .= $group_pk;
    $vars = array('groupMap'=>$groupMap,
        'groupId'=>$group_pk,
        'permissionMap'=> $this->groupPermissions,
        'baseUrl'=>$baseUrl,
        'groupMapAction'=>$onchange);

    $stmt = __METHOD__."getUsersWithGroup";
    $dbManager->prepare($stmt,"select  user_pk, user_name, group_user_member_pk, group_perm
         FROM users LEFT JOIN group_user_member gum ON gum.user_fk=users.user_pk AND gum.group_fk=$1
         ORDER BY user_name");
    $result = $dbManager->execute($stmt,array($group_pk));
    $vars['usersWithGroup'] = $dbManager->fetchAll($result);
    $dbManager->freeResult($result);
    
    $otherUsers = array('0'=>'');
    foreach($vars['usersWithGroup'] as $row)
    {
      if ($row['group_user_member_pk'])
      {
        continue;
      }
      $otherUsers[$row['user_pk']] = $row['user_name'];
    }

    $vars['existsOtherUsers'] = count($otherUsers)-1;
    if ($vars['existsOtherUsers'])
    {
      $vars['newPermissionMap'] = $this->groupPermissions;
      unset($vars['newPermissionMap'][-1]);
      $script = "var newpermurl;
      function setNewPermUrl(newperm){
         newpermurl='" . $baseUrl . "&newperm='+newperm+'&newuser=';
      }
      setNewPermUrl($newperm);";
      $scripts = js_url(). '<script type="text/javascript"> ' . $script . '</script>';
      $vars['otherUsers'] = $otherUsers;
    }
    else
    {
      $scripts = js_url();
    }

    $vars['scripts'] = $scripts;
    return $this->render('admin_group_users.html.twig', $this->mergeWithDefault($vars));
  }

  private function updateGUMPermission($gum_pk,$perm)
  {
    $dbManager = $this->getObject('db.manager');
    if ($perm === -1)
    {
      $dbManager->prepare($stmt=__METHOD__.".delByGUM",
                        "delete from group_user_member where group_user_member_pk=$1");
      $dbManager->freeResult($dbManager->execute($stmt,array($gum_pk)));
    }
    else if (array_key_exists ($perm, $this->groupPermissions))
    {
      $dbManager->getSingleRow("update group_user_member set group_perm=$1 where group_user_member_pk=$2",
              array($perm,$gum_pk),$stmt=__METHOD__.".updatePermInGUM");
    }
  }

}


register_plugin(new AdminGroupUsers());