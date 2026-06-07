<?php
/*
 SPDX-FileCopyrightText: © Fossology contributors

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\UI\Page;

use Fossology\Lib\Auth\Auth;
use Fossology\Lib\Dao\UserDao;
use Fossology\Lib\Plugin\DefaultPlugin;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class GroupPage extends DefaultPlugin
{
  const NAME = 'group';

  private $groupPermissions = array(
    -1 => "None",
    UserDao::USER => "User",
    UserDao::ADMIN => "Admin",
    UserDao::ADVISOR => "Advisor"
  );

  public function __construct()
  {
    parent::__construct(self::NAME, array(
        self::TITLE => _("Manage Groups"),
        self::MENU_LIST => "Admin::Groups",
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
    $action = $request->get('action', 'add');
    if (!in_array($action, array('add', 'delete', 'edit', 'manage'))) {
      $action = 'add';
    }

    $userId = Auth::getUserId();
    /** @var UserDao */
    $userDao = $this->getObject('dao.user');
    /** @var DbManager */
    $dbManager = $this->getObject('db.manager');
    $vars = array();
    $vars['activeAction'] = $action;
    $vars['uri'] = Traceback_uri() . "?mod=" . self::NAME . "&action=" . $action;

    if ($action === 'add') {
      if ($request->isMethod('POST')) {
        $groupName = trim($request->get('groupname'));
        if (!empty($groupName)) {
          $validateGroup = $this->validateGroupName($groupName);
          if (empty($validateGroup)) {
            try {
              $escapedGroupName = htmlspecialchars(strip_tags($groupName), ENT_QUOTES, 'UTF-8');
              $groupId = $userDao->addGroup($escapedGroupName);
              $userDao->addGroupMembership($groupId, $userId);
              $vars['message'] = _("Group") . ' ' . $escapedGroupName . ' ' . _("added") . '.';
            } catch (\Exception $e) {
              $vars['message'] = $e->getMessage();
            }
          } else {
            $vars['message'] = $validateGroup;
          }
        }
      }
    } elseif ($action === 'delete') {
      $groupMap = $userDao->getDeletableAdminGroupMap($userId, $_SESSION[Auth::USER_LEVEL]);
      if ($request->isMethod('POST')) {
        $groupId = $request->get('grouppk');
        if (!empty($groupId) && isset($groupMap[$groupId])) {
          try {
            $userDao->deleteGroup($groupId);
            $vars['message'] = _("Group") . ' ' . $groupMap[$groupId] . ' ' . _("deleted") . '.';
            unset($groupMap[$groupId]);
          } catch (\Exception $e) {
            $vars['message'] = $e->getMessage();
          }
        }
      }
      $vars['groupMap'] = $groupMap;
    } elseif ($action === 'edit') {
      $groupMap = $userDao->getDeletableAdminGroupMap($userId, $_SESSION[Auth::USER_LEVEL]);
      if ($request->isMethod('POST')) {
        $groupId = $request->get('grouppk');
        $newGroupName = trim($request->get('newgroupname'));
        if (!empty($groupId) && !empty($newGroupName) && isset($groupMap[$groupId])) {
          $validateGroup = $this->validateGroupName($newGroupName);
          if (empty($validateGroup)) {
            try {
              $escapedGroupName = htmlspecialchars(strip_tags($newGroupName), ENT_QUOTES, 'UTF-8');
              $userDao->editGroup($groupId, $escapedGroupName);
              $vars['message'] = _("Group") . ' ' . $groupMap[$groupId] . ' ' . _("edited to " . $escapedGroupName) . '.';
              $groupMap[$groupId] = $escapedGroupName;
            } catch (\Exception $e) {
              $vars['message'] = $e->getMessage();
            }
          } else {
            $vars['message'] = $validateGroup;
          }
        }
      }
      $vars['groupMap'] = $groupMap;
    } elseif ($action === 'manage') {
      $groupMap = $userDao->getAdminGroupMap($userId, $_SESSION[Auth::USER_LEVEL]);
      if (empty($groupMap)) {
        $vars['content'] = _("You have no permission to manage any group.");
        return $this->render('include/base.html.twig', $this->mergeWithDefault($vars));
      }

      $group_pk = intval($request->get('group'));
      if (empty($group_pk) || !array_key_exists($group_pk, $groupMap)) {
        $group_pk = key($groupMap);
      }

      $gum_pk = intval($request->get('gum_pk'));
      $text = "";
      if ($gum_pk) {
        $perm = intval($request->get('perm'));
        $atleastOneUserShouldBePart = $dbManager->getSingleRow(
          "SELECT count(*) cnt FROM group_user_member WHERE group_fk = (SELECT group_fk FROM group_user_member WHERE group_user_member_pk = $1)",
          array($gum_pk),
          $stmt = __METHOD__ . ".atleastOneUserShouldBePart"
        );
        if ($atleastOneUserShouldBePart['cnt'] <= 1) {
           $text = _("Error: atleast one user should be part of a group.");
        } else {
          $this->updateGUMPermission($gum_pk, $perm, $dbManager);
        }
        $groupMap = $userDao->getAdminGroupMap($userId, $_SESSION[Auth::USER_LEVEL]);
      }

      $newuser = intval($request->get('newuser'));
      $newperm = intval($request->get('newperm'));

      if ($newuser && $group_pk) {
        // do not produce duplicate
        $dbManager->prepare(
          $stmt = __METHOD__ . ".delByGroupAndUser",
          "delete from group_user_member where group_fk=$1 and user_fk=$2"
        );
        $dbManager->freeResult(
          $dbManager->execute($stmt, array($group_pk, $newuser))
        );
        if ($newperm >= 0) {
          $dbManager->prepare(
            $stmt = __METHOD__ . ".insertGUP",
            "insert into group_user_member (group_fk, user_fk, group_perm) values ($1,$2,$3)"
          );
          $dbManager->freeResult(
            $dbManager->execute($stmt, array($group_pk, $newuser, $newperm))
          );
        }
        if ($newuser == $userId) {
          $groupMap = $userDao->getAdminGroupMap($userId, $_SESSION[Auth::USER_LEVEL]);
        }
        $newperm = $newuser = 0;
      }

      natcasesort($groupMap);
      $baseUrl = Traceback_uri() . "?mod=" . $this->getName() . '&action=manage&group=';
      $onchange = "onchange=\"js_url(this.value, '$baseUrl')\"";
      $baseUrl .= $group_pk;
      $vars = array_merge($vars, array(
          'groupMap' => $groupMap,
          'groupId' => $group_pk,
          'permissionMap' => $this->groupPermissions,
          'baseUrl' => $baseUrl,
          'groupMapAction' => $onchange
      ));

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
        $vars['message'] = (isset($vars['message']) ? $vars['message'] : "") . $text;
      }
    }

    return $this->render('group.html.twig', $this->mergeWithDefault($vars));
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
    } else if (preg_match('/^[\s\w_-]+$/', $groupName) !== 1) {
      return _("Invalid: Group name can only contain letters, numbers, hyphens and underscores");
    } else if (is_numeric($groupName)) {
      return _("Invalid: Group name cannot be numeric-only");
    } else {
      return "";
    }
  }

  public function updateGUMPermission($gum_pk, $perm, $dbManager)
  {
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

register_plugin(new GroupPage());
