<?php
/*
 SPDX-FileCopyrightText: © 2018, 2021 Siemens AG
 Author: Gaurav Mishra <mishra.gaurav@siemens.com>

 SPDX-License-Identifier: GPL-2.0-only
*/
/**
 * @file
 * @brief Controller for user queries
 */

namespace Fossology\UI\Api\Controllers;

use Fossology\UI\Api\Helper\ResponseHelper;
use Psr\Http\Message\ServerRequestInterface;
use Fossology\UI\Api\Models\Info;
use Fossology\UI\Api\Models\InfoType;
use Fossology\UI\Api\Helper\UserHelper;

/**
 * @class UserController
 * @brief Controller for User model
 */
class UserController extends RestController
{
  /**
   * Get list of Users
   *
   * @param ServerRequestInterface $request
   * @param ResponseHelper $response
   * @param array $args
   * @return ResponseHelper
   */
  public function getUsers($request, $response, $args)
  {
    $id = null;
    if (isset($args['id'])) {
      $id = intval($args['id']);
      if (! $this->dbHelper->doesIdExist("users", "user_pk", $id)) {
        $returnVal = new Info(404, "UserId doesn't exist", InfoType::ERROR);
        return $response->withJson($returnVal->getArray(), $returnVal->getCode());
      }
    }
    $users = $this->dbHelper->getUsers($id);
    if ($id !== null) {
      $users = $users[0];
    }
    return $response->withJson($users, 200);
  }

  /**
   * Create a user
   *
   * @param ServerRequestInterface $request
   * @param ResponseHelper $response
   * @param array $args
   * @return ResponseHelper
   */
  public function addUser($request, $response, $args)
  {
    $userDetails = $this->getParsedBody($request);
    $userHelper = new UserHelper();
    // creating symphony request
    $symfonyRequest = new \Symfony\Component\HttpFoundation\Request();
    $symfonyRequest->request->set('username', $userDetails['name']);
    $symfonyRequest->request->set('pass1', $userDetails['user_pass']);
    $symfonyRequest->request->set('pass2', $userDetails['user_pass']);
    $symfonyRequest->request->set('description', $userDetails['description']);
    $symfonyRequest->request->set('permission', $userHelper->getEquivalentValueForPermission($userDetails['accessLevel']));
    $symfonyRequest->request->set('folder', $userDetails['rootFolderId']);
    $symfonyRequest->request->set('enote', $userDetails['emailNotification'] ? 'y' : 'n');
    $symfonyRequest->request->set('email', $userDetails['email']);
    $symfonyRequest->request->set('public', $userDetails['defaultVisibility']);
    $symfonyRequest->request->set('default_bucketpool_fk', isset($userDetails['defaultBucketpool']) ? $userDetails['defaultBucketpool'] : 2);

    $agents = array();
    if (isset($userDetails['agents'])) {
      if (is_string($userDetails['agents'])) { // If 'x-www-form-urlencoded', inner elements are not decoded
        $userDetails['agents'] = json_decode($userDetails['agents'], true);
      }
      $agents['Check_agent_mimetype'] = isset($userDetails['agents']['mime']) && $userDetails['agents']['mime'] ? 1 : 0;
      $agents['Check_agent_monk'] = isset($userDetails['agents']['monk']) && $userDetails['agents']['monk'] ? 1 : 0;
      $agents['Check_agent_ojo'] = isset($userDetails['agents']['ojo']) && $userDetails['agents']['ojo'] ? 1 : 0;
      $agents['Check_agent_bucket'] = isset($userDetails['agents']['bucket']) && $userDetails['agents']['bucket'] ? 1 : 0 ;
      $agents['Check_agent_copyright'] = isset($userDetails['agents']['copyright_email_author']) && $userDetails['agents']['copyright_email_author'] ? 1 : 0;
      $agents['Check_agent_ecc'] = isset($userDetails['agents']['ecc']) && $userDetails['agents']['ecc'] ? 1 : 0;
      $agents['Check_agent_keyword'] = isset($userDetails['agents']['keyword']) && $userDetails['agents']['keyword'] ? 1 : 0;
      $agents['Check_agent_nomos'] = isset($userDetails['agents']['nomos']) && $userDetails['agents']['nomos'] ? 1 : 0;
      $agents['Check_agent_pkgagent'] = isset($userDetails['agents']['package']) && $userDetails['agents']['package'] ? 1 : 0;
      $agents['Check_agent_reso'] = isset($userDetails['agents']['reso']) && $userDetails['agents']['reso'] ? 1 : 0;
      $agents['Check_agent_shagent'] = isset($userDetails['agents']['heritage']) && $userDetails['agents']['heritage'] ? 1 : 0 ;
    }

    $symfonyRequest->request->set('user_agent_list', userAgents($agents));

    // initialising the user_add object
    global $container;
    $restHelper = $container->get('helper.restHelper');
    $userAddObj = $restHelper->getPlugin('user_add');

    // calling the add function
    $ErrMsg = $userAddObj->add($symfonyRequest);

    if ($ErrMsg != '') {
      $returnVal = new Info(500, $ErrMsg, InfoType::ERROR);
    } else {
      $returnVal = new Info(201, "User created successfully", InfoType::INFO);
    }
    return $response->withJson($returnVal->getArray(), $returnVal->getCode());
  }

  /**
   * Delete a given user
   *
   * @param ServerRequestInterface $request
   * @param ResponseHelper $response
   * @param array $args
   * @return ResponseHelper
   */
  public function deleteUser($request, $response, $args)
  {
    $id = intval($args['id']);
    $returnVal = null;
    if ($this->dbHelper->doesIdExist("users","user_pk", $id)) {
      $this->dbHelper->deleteUser($id);
      $returnVal = new Info(202, "User will be deleted", InfoType::INFO);
    } else {
      $returnVal = new Info(404, "UserId doesn't exist", InfoType::ERROR);
    }
    return $response->withJson($returnVal->getArray(), $returnVal->getCode());
  }

  /**
   * Get information of current user
   *
   * @param ServerRequestInterface $request
   * @param ResponseHelper $response
   * @param array $args
   * @return ResponseHelper
   */
  public function getCurrentUser($request, $response, $args)
  {
    $user = $this->dbHelper->getUsers($this->restHelper->getUserId())[0];
    $userDao = $this->restHelper->getUserDao();
    $defaultGroup = $userDao->getUserAndDefaultGroupByUserName($user["name"])["group_name"];
    $user["default_group"] = $defaultGroup;
    return $response->withJson($user, 200);
  }

  /**
   * Updates the user details
   *
   * @param ServerRequestInterface $request
   * @param ResponseHelper $response
   * @param array $args
   * @return ResponseHelper
   */
  public function updateUser($request, $response, $args)
  {
    $id = intval($args['id']);
    $returnVal = null;
    if ($this->dbHelper->doesIdExist("users","user_pk", $id)) {
      $reqBody = $this->getParsedBody($request);
      $userHelper = new UserHelper($id);
      $returnVal = $userHelper->modifyUserDetails($reqBody);
    } else {
      $returnVal = new Info(404, "UserId doesn't exist!", InfoType::ERROR);
    }
    return $response->withJson($returnVal->getArray(), $returnVal->getCode());
  }

  /**
   * Create a new REST API Token
   *
   * @param ServerRequestInterface $request
   * @param ResponseHelper $response
   * @param array $args
   * @return ResponseHelper
   */
  public function createRestApiToken($request, $response, $args)
  {
    $symfonyRequest = new \Symfony\Component\HttpFoundation\Request();
    $reqBody = $this->getParsedBody($request);
    $paramsReq = [
      "token_name",
      "token_scope",
      "token_expire"
    ];
    $returnVal = null;
    if (array_diff_key(array_flip($paramsReq), $reqBody)) {
      $returnVal = new Info(400,
      "Following parameters are required in the request body: " .
      join(",", $paramsReq), InfoType::ERROR);
      return $response->withJson($returnVal->getArray(), $returnVal->getCode());
    } else {
      $symfonyRequest = new \Symfony\Component\HttpFoundation\Request();

      // translating values for symfony request
      $symfonyRequest->request->set('pat_name', $reqBody['token_name']);
      $symfonyRequest->request->set('pat_expiry', $reqBody['token_expire']);
      $symfonyRequest->request->set('pat_scope', $reqBody['token_scope'] == "write" ? "w" : "r");

      // initialising the user_edit plugin
      global $container;
      $restHelper = $container->get('helper.restHelper');
      $userEditObj = $restHelper->getPlugin('user_edit');

      // creating the REST token
      $token = $userEditObj->generateNewToken($symfonyRequest);

      $returnVal = new Info(201, "Token created successfully", InfoType::INFO);
      $res = $returnVal->getArray();
      $res['token'] = $token;
      return $response->withJson($res, $returnVal->getCode());
    }
  }

  /**
   * Get all the REST API tokens (active | expired)
   *
   * @param ServerRequestInterface $request
   * @param ResponseHelper $response
   * @param array $args
   * @return ResponseHelper
   */
  public function getTokens($request, $response, $args)
  {
    $tokenType = $args['type'];
    if ($tokenType == "active" || $tokenType == "expired") {
      // initialising the user_edit plugin
      global $container;
      $restHelper = $container->get('helper.restHelper');
      $userEditObj = $restHelper->getPlugin('user_edit');

      // getting the list of tokens based on the type of token requested
      $tokens = $tokenType == "active" ? $userEditObj->getListOfActiveTokens() : $userEditObj->getListOfExpiredTokens();
      $manageTokenObj = $restHelper->getPlugin('manage-token');

      $finalTokens = array();
      foreach ($tokens as $token) {
        list($tokenPk) = explode(".", $token['id']);
        $tokenVal = $manageTokenObj->revealToken($tokenPk);
        $finalTokens[] = array_merge($token, ['token' => $tokenVal['token']]);
      }

      $returnVal = new Info(200, "Success", InfoType::INFO);
      $res = $returnVal->getArray();
      $res[$tokenType . '_tokens'] = $finalTokens;
      return $response->withJson($res, $returnVal->getCode());
    } else {
      $returnVal = new Info(400, "Invalid request!", InfoType::ERROR);
      return $response->withJson($returnVal->getArray(), $returnVal->getCode());
    }
  }
}
