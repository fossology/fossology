<?php

/**
 * SPDX-FileCopyrightText: © 2022 Krishna Mahato <krishhtrishh9304@gmail.com>
 *
 * SPDX-License-Identifier: GPL-2.0-only
 */

/**
 * @user
 * @brief Helper for User related queries
 */

namespace Fossology\UI\Api\Helper;

use Fossology\Lib\Auth\Auth;
use Fossology\Lib\Dao\UserDao;
use Fossology\UI\Api\Models\Info;
use Fossology\UI\Api\Models\InfoType;
use Symfony\Component\HttpFoundation\Request;

/**
 * @class UserHelper
 * @brief Handle user related queries
 */
class UserHelper
{
  /**
   * @var $user_pk
   */
  private $user_pk;

  /**
   * Constructor for UserHelper
   *
   * @param $user_pk
   */
  public function __construct($user_pk=null)
  {
    $this->user_pk = $user_pk;
  }

  public function modifyUserDetails($reqBody)
  {
    global $container;
    $restHelper = $container->get('helper.restHelper');
    $userEditObj = $restHelper->getPlugin('user_edit');
    /* Is the session owner an admin? */
    $sessionOwnerUser_pk = $restHelper->getUserId();
    $SessionUserRec = $userEditObj->GetUserRec($sessionOwnerUser_pk);
    $SessionIsAdmin = $userEditObj->IsSessionAdmin($SessionUserRec);

    $symReq = $this->createSymRequest($reqBody);
    if (!$SessionIsAdmin) {
      $returnVal = new Info(403, "The session owner is not an admin!", InfoType::INFO);
    } else {
      $userRec = $userEditObj->CreateUserRec($symReq);
      $ErrMsgs = $userEditObj->UpdateUser($userRec, $SessionIsAdmin);

      if ($ErrMsgs == null) {
        $returnVal = new Info(200, "User updated succesfully!", InfoType::INFO);
      } else {
        $returnVal = new Info(400, $ErrMsgs, InfoType::INFO);
      }
    }
    return $returnVal;
  }

  /**
   * @param array $userDetails parsed from request body
   * @return Request $symfonyRequest
   */
  public function createSymRequest($userDetails)
  {
    global $container;
    $restHelper = $container->get('helper.restHelper');

    /**
     * @var UserDao $userDao
     * User dao
     */
    $userDao = $restHelper->getUserDao();
    $user = $userDao->getUserByPk($this->user_pk);

    $symfonyRequest = new Request();
    $symfonyRequest->request->set('user_pk', $userDetails['id'] ?? $this->user_pk);
    $symfonyRequest->request->set('user_name', $userDetails['name'] ?? $user['user_name']);
    $symfonyRequest->request->set('root_folder_fk', $userDetails['rootFolderId'] ?? $user['root_folder_fk']);
    $symfonyRequest->request->set('default_group_fk', $userDetails['defaultGroup'] ?? $user['group_fk']);
    $symfonyRequest->request->set('public', $userDetails['defaultVisibility'] ?? $user['upload_visibility']);
    $symfonyRequest->request->set('default_folder_fk', $userDetails['defaultFolderId'] ?? $user['default_folder_fk']);
    $symfonyRequest->request->set('user_desc', $userDetails['description'] ?? $user['user_desc']);
    $symfonyRequest->request->set('_pass1', $userDetails['user_pass'] ?? null);
    $symfonyRequest->request->set('_pass2', $userDetails['user_pass'] ?? null);
    $symfonyRequest->request->set('_blank_pass', $userDetails['_blank_pass'] ?? "");
    $symfonyRequest->request->set('user_status', $userDetails['user_status'] ?? $user['user_status']);
    $symfonyRequest->request->set('user_email', $userDetails['email'] ?? $user['user_email']);
    $symfonyRequest->request->set('email_notify', isset($userDetails['emailNotification']) && $userDetails['emailNotification'] ? "y" : $user['email_notify']);
    $symfonyRequest->request->set('default_bucketpool_fk', $userDetails['defaultBucketpool'] ?? $user['default_bucketpool_fk']);

    if (isset($userDetails['accessLevel'])) {
      $user_perm = $this->getEquivalentValueForPermission($userDetails['accessLevel']);
      $symfonyRequest->request->set('user_perm', $user_perm);
    } else {
      $symfonyRequest->request->set('user_perm', $user['user_perm']);
    }

    $agentsExists = array();
    // setting previous values from db
    $agentsTempVal = explode(',', $user['user_agent_list']);
    foreach ($agentsTempVal as $agent) {
      $agentsExists['Check_' . $agent] = 1;
    }
    $newAgents = array();
    if (isset($userDetails['agents'])) {
      if (is_string($userDetails['agents'])) {
        $userDetails['agents'] = json_decode($userDetails['agents'], true);
      }
      if (isset($userDetails['agents']['mime'])) {
        $newAgents['Check_agent_mimetype'] = $userDetails['agents']['mime'] ? 1 : 0;
      }
      if (isset($userDetails['agents']['monk'])) {
        $newAgents['Check_agent_monk'] = $userDetails['agents']['monk'] ? 1 : 0;
      }
      if (isset($userDetails['agents']['ojo'])) {
        $newAgents['Check_agent_ojo'] = $userDetails['agents']['ojo'] ? 1 : 0;
      }
      if (isset($userDetails['agents']['copyright_email_author'])) {
        $newAgents['Check_agent_copyright'] = $userDetails['agents']['copyright_email_author'] ? 1 : 0;
      }
      if (isset($userDetails['agents']['ecc'])) {
        $newAgents['Check_agent_ecc'] = $userDetails['agents']['ecc'] ? 1 : 0;
      }
      if (isset($userDetails['agents']['keyword'])) {
        $newAgents['Check_agent_keyword'] = $userDetails['agents']['keyword'] ? 1 : 0;
      }
      if (isset($userDetails['agents']['nomos'])) {
        $newAgents['Check_agent_nomos'] = $userDetails['agents']['nomos'] ? 1 : 0;
      }
      if (isset($userDetails['agents']['package'])) {
        $newAgents['Check_agent_pkgagent'] = $userDetails['agents']['package'] ? 1 : 0;
      }
      if (isset($userDetails['agents']['reso'])) {
        $newAgents['Check_agent_reso'] = $userDetails['agents']['reso'] ? 1 : 0;
      }
      if (isset($userDetails['agents']['heritage'])) {
        $newAgents['Check_agent_shagent'] = $userDetails['agents']['heritage'] ? 1 : 0;
      }
      // Make sure all agents are in the list
      $agentList = listAgents();
      foreach (array_keys($agentList) as $agentName) {
        if (!array_key_exists("Check_$agentName", $newAgents)) {
          $newAgents["Check_$agentName"] = 0;
        }
      }
    }
    $agents = array_replace($agentsExists, $newAgents);

    $symfonyRequest->request->set('user_agent_list', userAgents($agents));

    return $symfonyRequest;
  }

  /**
   * @param string $perm which is user permission
   * @return int permission value
   */
  public function getEquivalentValueForPermission($perm)
  {
    switch ($perm) {
      case 'read_only':
        return Auth::PERM_READ;
      case 'read_write':
        return Auth::PERM_WRITE;
      case 'clearing_admin':
        return Auth::PERM_CADMIN;
      case 'admin':
        return Auth::PERM_ADMIN;
      default:
        return Auth::PERM_NONE;
    }
  }
}
