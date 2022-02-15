<?php
/***********************************************************
 Copyright (C) 2014-2015, 2018 Siemens AG
 Author: Steffen Weber
 Copyright (c) 2021-2022 Orange
 Contributors: Piotr Pszczola, Bartlomiej Drozdz

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

use Fossology\Lib\Auth\Auth;
use Fossology\Lib\Dao\UserDao;
use Fossology\Lib\Plugin\DefaultPlugin;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * \class group_manage extends FO_Plugin
 * \brief edit group user permissions
 */
class AdminGroupUsers extends DefaultPlugin
{
  var $groupPermissions = array(-1 => "None", UserDao::USER => "User",
    UserDao::ADMIN => "Admin", UserDao::ADVISOR => "Advisor");
  const NAME = 'group_manage_users';

  function __construct()
  {
    parent::__construct(self::NAME, array(
        self::TITLE => _("Manage Group Users"),
        self::MENU_LIST => "Admin::Groups::Manage Group Users",
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
    /** @var UserDao */
    $userDao = $this->getObject('dao.user');
    $groupMap = $userDao->getAdminGroupMap($userId, $_SESSION[Auth::USER_LEVEL]);
    if (empty($groupMap)) {
      $text = _("You have no permission to manage any group.");
      return $this->render('include/base.html.twig', $this->mergeWithDefault(array('message' => $text)));
    }
    /** @var DbManager */
    $dbManager = $this->getObject('db.manager');
    $group_pk = intval($request->get('group'));
    if (empty($group_pk) || !array_key_exists($group_pk, $groupMap)) {
      $group_pk = key($groupMap);
    }

    $gum_pk = intval($request->get('gum_pk'));
    $text = "";
    if ($gum_pk) {
      $perm = intval($request->get('perm'));
      $atleastOneUserShouldBePart = $dbManager->getSingleRow("SELECT count(*) cnt FROM group_user_member WHERE group_fk = (SELECT group_fk FROM group_user_member WHERE group_user_member_pk = $1)",
      array($gum_pk), $stmt = __METHOD__ . ".atleastOneUserShouldBePart");
      if ($atleastOneUserShouldBePart['cnt'] <= 1) {
         $text = _("Error: atleast one user should be part of a group.");
      } else {
        $this->updateGUMPermission($gum_pk, $perm);
      }
      $groupMap = $userDao->getAdminGroupMap($userId,
        $_SESSION[Auth::USER_LEVEL]);
    }

    $newuser = intval($request->get('newuser'));
    $newperm = intval($request->get('newperm'));

    if ($newuser && $group_pk) {
      // do not produce duplicate
      $dbManager->prepare($stmt = __METHOD__ . ".delByGroupAndUser",
        "delete from group_user_member where group_fk=$1 and user_fk=$2");
      $dbManager->freeResult(
        $dbManager->execute($stmt, array($group_pk, $newuser)));
      if ($newperm >= 0) {
        $dbManager->prepare($stmt = __METHOD__ . ".insertGUP",
          "insert into group_user_member (group_fk, user_fk, group_perm) values ($1,$2,$3)");
        $dbManager->freeResult(
          $dbManager->execute($stmt, array($group_pk, $newuser, $newperm)));
      }
      if ($newuser == $userId) {
        $groupMap = $userDao->getAdminGroupMap($userId, $_SESSION[Auth::USER_LEVEL]);
      }
      $newperm = $newuser = 0;
    }

    natcasesort($groupMap);
    $baseUrl = Traceback_uri() . "?mod=" . $this->getName() . '&group=';
    $onchange = "onchange=\"js_url(this.value, '$baseUrl')\"";
    $baseUrl .= $group_pk;
    $vars = array('groupMap' => $groupMap,
        'groupId' => $group_pk,
        'permissionMap' => $this->groupPermissions,
        'baseUrl' => $baseUrl,
        'groupMapAction' => $onchange);

    $stmt = __METHOD__ . "getUsersWithGroup";
    $dbManager->prepare($stmt, "select user_pk, user_name, user_status, user_desc, group_user_member_pk, group_perm
         FROM users LEFT JOIN group_user_member gum ON gum.user_fk=users.user_pk AND gum.group_fk=$1 
         ORDER BY user_name");
    $result = $dbManager->execute($stmt, array($group_pk));
    $vars['usersWithGroup'] = $dbManager->fetchAll($result);
    $dbManager->freeResult($result);

    $otherUsers = array('0' => '');
    foreach ($vars['usersWithGroup'] as $row) {
      if ($row['group_user_member_pk'] || $row['user_status']!='active') {
        continue;
      }
      $otherUsers[$row['user_pk']] = !empty($row['user_desc']) ? $row['user_desc']. ' ('. $row['user_name'] .')' : $row['user_name'];
    }

    $vars['existsOtherUsers'] = count($otherUsers) - 1;
    if ($vars['existsOtherUsers']) {
      $vars['newPermissionMap'] = $this->groupPermissions;
      unset($vars['newPermissionMap'][-1]);
      $script = "var newpermurl;
      function setNewPermUrl(newperm){
         newpermurl='" . $baseUrl . "&newperm='+newperm+'&newuser=';
      }
      setNewPermUrl($newperm);";
      $scripts = js_url() . '<script type="text/javascript"> ' . $script . '</script>';
      $vars['otherUsers'] = $otherUsers;
    } else {
      $scripts = js_url();
    }

    $vars['scripts'] = $scripts;
    if (!empty($text)) {
      $vars['message'] .= $text;
    }
    return $this->render('admin_group_users.html.twig', $this->mergeWithDefault($vars));
  }

  private function updateGUMPermission($gum_pk, $perm)
  {
    $dbManager = $this->getObject('db.manager');
    if ($perm === -1) {
      $dbManager->prepare($stmt = __METHOD__ . ".delByGUM",
          "DELETE FROM group_user_member WHERE group_user_member_pk=$1 RETURNING user_fk, group_fk");
      $deletedEntry = $dbManager->execute($stmt, array($gum_pk));
      $effectedUser = $dbManager->fetchArray($deletedEntry);
      $isEffected = $dbManager->getSingleRow("SELECT count(*) cnt FROM users WHERE user_pk=$1 AND group_fk = $2",
        array($effectedUser['user_fk'], $effectedUser['group_fk']), $stmt = __METHOD__ . ".isUserEffectedFromRemoval");
      if ($isEffected['cnt'] == 1) {
        $dbManager->getSingleRow("UPDATE users SET group_fk = (
          SELECT group_fk FROM group_user_member WHERE user_fk = $1 AND group_perm >= 0 LIMIT 1)
          WHERE user_pk = $1",
          array($effectedUser['user_fk']), $stmt = __METHOD__ . ".setNewGroupId");
      }
      $dbManager->freeResult($deletedEntry);
    } else if (array_key_exists($perm, $this->groupPermissions)) {
      $dbManager->getSingleRow("UPDATE group_user_member SET group_perm=$1 WHERE group_user_member_pk=$2",
          array($perm, $gum_pk), $stmt = __METHOD__ . ".updatePermInGUM");
    }
  }
}

register_plugin(new AdminGroupUsers());
