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

use Fossology\Lib\Exceptions\DuplicateTokenKeyException;
use Fossology\Lib\Exceptions\DuplicateTokenNameException;
use Fossology\UI\Api\Exceptions\HttpBadRequestException;
use Fossology\UI\Api\Exceptions\HttpConflictException;
use Fossology\UI\Api\Exceptions\HttpErrorException;
use Fossology\UI\Api\Exceptions\HttpInternalServerErrorException;
use Fossology\UI\Api\Exceptions\HttpNotFoundException;
use Fossology\UI\Api\Exceptions\HttpTooManyRequestException;
use Fossology\UI\Api\Helper\ResponseHelper;
use Fossology\UI\Api\Helper\RestHelper;
use Fossology\UI\Api\Helper\UserHelper;
use Fossology\UI\Api\Models\ApiVersion;
use Fossology\UI\Api\Models\Info;
use Fossology\UI\Api\Models\InfoType;
use Fossology\UI\Api\Models\TokenRequest;
use Psr\Http\Message\ServerRequestInterface;

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
   * @throws HttpNotFoundException
   */
  public function getUsers($request, $response, $args)
  {
    $this->throwNotAdminException();
    $apiVersion = ApiVersion::getVersion($request);
    $id = null;
    if (isset($args['pathParam'])) {
      if ($apiVersion == ApiVersion::V2) {
        $user = $this->restHelper->getUserDao()->getUserByName($args['pathParam']);
        if ($user === null) {
          throw new HttpNotFoundException("UserId doesn't exist");
        }
        $id = intval($user['user_pk']);
      } else {
        $id = intval($args['pathParam']);
      }
      if (! $this->dbHelper->doesIdExist("users", "user_pk", $id)) {
        throw new HttpNotFoundException("UserId doesn't exist");
      }
    }
    $users = $this->dbHelper->getUsers($id);

    $allUsers = array();
    foreach ($users as $user) {
      $allUsers[] = $user->getArray($apiVersion);
    }
    if ($id !== null) {
      $allUsers = $allUsers[0];
    }
    return $response->withJson($allUsers, 200);
  }

  /**
   * Create a user
   *
   * @param ServerRequestInterface $request
   * @param ResponseHelper $response
   * @param array $args
   * @return ResponseHelper
   * @throws HttpInternalServerErrorException
   */
  public function addUser($request, $response, $args)
  {
    $this->throwNotAdminException();
    $apiVersion = ApiVersion::getVersion($request);
    $userDetails = $this->getParsedBody($request);
    if ($userDetails === null || !is_array($userDetails)) {
      throw new HttpBadRequestException("Request body is empty or malformed.");
    }
    if (empty($userDetails['name'])) {
      throw new HttpBadRequestException("Username must be specified.");
    }

    list($success, $message) = $this->createUser($userDetails, $apiVersion);
    if (!$success) {
      throw new HttpInternalServerErrorException($message);
    }

    $returnVal = new Info(201, "User created successfully", InfoType::INFO);
    return $response->withJson($returnVal->getArray(), $returnVal->getCode());
  }

  /**
   * Build a legacy user_add compatible request from a DTO and create the
   * user through the user_add plugin.
   *
   * @param array $userDetails Associative array using the same keys as the
   *   REST createUser DTO (name, description, email, accessLevel,
   *   rootFolderId, emailNotification, defaultBucketpool, agents{...}).
   * @param string $apiVersion ApiVersion::V1 or ApiVersion::V2
   * @return array{0: bool, 1: string} [success, message or error]
   */
  private function createUser($userDetails, $apiVersion)
  {
    $userHelper = new UserHelper();
    // creating symphony request
    $symfonyRequest = new \Symfony\Component\HttpFoundation\Request();
    $symfonyRequest->request->set('username', $userDetails['name']);
    $pass = $userDetails[$apiVersion == ApiVersion::V2 ? 'userPass' : 'user_pass'] ?? '';
    $symfonyRequest->request->set('pass1', $pass);
    $symfonyRequest->request->set('pass2', $pass);
    $symfonyRequest->request->set('description', $userDetails['description'] ?? '');
    $symfonyRequest->request->set('permission', $userHelper->getEquivalentValueForPermission($userDetails['accessLevel'] ?? null));
    $symfonyRequest->request->set('folder', $userDetails['rootFolderId'] ?? '');
    $symfonyRequest->request->set('enote', !empty($userDetails['emailNotification']) ? 'y' : 'n');
    $symfonyRequest->request->set('email', $userDetails['email'] ?? '');
    $symfonyRequest->request->set('public', $userDetails['defaultVisibility'] ?? '');
    $symfonyRequest->request->set('default_bucketpool_fk', $userDetails['defaultBucketpool'] ?? 2);

    $agents = $this->buildAgentCheckboxes($userDetails['agents'] ?? null, $apiVersion);
    $symfonyRequest->request->set('user_agent_list', userAgents($agents));

    // initialising the user_add object
    global $container;
    $restHelper = $container->get('helper.restHelper');
    $userAddObj = $restHelper->getPlugin('user_add');

    // calling the add function
    $errMsg = $userAddObj->add($symfonyRequest);

    if ($errMsg != '') {
      return [false, $errMsg];
    }
    return [true, "User '" . $userDetails['name'] . "' created successfully."];
  }

  /**
   * Translate a REST agents DTO (bucket, copyrightEmailAuthor, ecc, ipra,
   * keyword, mime, monk, nomos, ojo, pkgagent, reso, softwareHeritage for
   * V2; the V1 equivalents otherwise) into the legacy Check_agent_* map
   * expected by userAgents().
   *
   * Previously this mapping used the wrong (V1) key names for pkgagent and
   * softwareHeritage when handling a V2 request -- 'package'/'heritage'
   * instead of 'pkgagent'/'softwareHeritage' -- so those two agent flags
   * were silently ignored for every V2 createUser() call. The ipra agent
   * had no mapping at all, for either version, so it was always ignored.
   *
   * @param array|string|null $agentsDto
   * @param string $apiVersion ApiVersion::V1 or ApiVersion::V2
   * @return array<string, int>
   */
  private function buildAgentCheckboxes($agentsDto, $apiVersion)
  {
    if (empty($agentsDto)) {
      return array();
    }
    if (is_string($agentsDto)) { // If 'x-www-form-urlencoded', inner elements are not decoded
      $agentsDto = json_decode($agentsDto, true);
    }
    $copyrightKey = $apiVersion == ApiVersion::V2 ? 'copyrightEmailAuthor' : 'copyright_email_author';
    $pkgagentKey = $apiVersion == ApiVersion::V2 ? 'pkgagent' : 'package';
    $heritageKey = $apiVersion == ApiVersion::V2 ? 'softwareHeritage' : 'heritage';
    $ipraKey = $apiVersion == ApiVersion::V2 ? 'ipra' : 'patent';

    $isChecked = function ($key) use ($agentsDto) {
      return isset($agentsDto[$key]) && $agentsDto[$key] ? 1 : 0;
    };

    return array(
      'Check_agent_mimetype' => $isChecked('mime'),
      'Check_agent_monk' => $isChecked('monk'),
      'Check_agent_ojo' => $isChecked('ojo'),
      'Check_agent_bucket' => $isChecked('bucket'),
      'Check_agent_copyright' => $isChecked($copyrightKey),
      'Check_agent_ecc' => $isChecked('ecc'),
      'Check_agent_keyword' => $isChecked('keyword'),
      'Check_agent_nomos' => $isChecked('nomos'),
      'Check_agent_pkgagent' => $isChecked($pkgagentKey),
      'Check_agent_reso' => $isChecked('reso'),
      'Check_agent_shagent' => $isChecked($heritageKey),
      'Check_agent_ipra' => $isChecked($ipraKey),
    );
  }

  /**
   * Delete a given user
   *
   * @param ServerRequestInterface $request
   * @param ResponseHelper $response
   * @param array $args
   * @return ResponseHelper
   * @throws HttpNotFoundException
   */
  public function deleteUser($request, $response, $args)
  {
    $this->throwNotAdminException();
    $apiVersion = ApiVersion::getVersion($request);
    if ($apiVersion == ApiVersion::V2) {
      $user = $this->restHelper->getUserDao()->getUserByName($args['pathParam']);
      if ($user === null) {
        throw new HttpNotFoundException("UserId doesn't exist");
      }
      $id = intval($user['user_pk']);
    } else {
      $id = intval($args['pathParam']);
    }
    if (!$this->dbHelper->doesIdExist("users","user_pk", $id)) {
      throw new HttpNotFoundException("UserId doesn't exist");
    }

    $this->dbHelper->deleteUser($id);
    $returnVal = new Info(202, "User will be deleted", InfoType::INFO);
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
    $apiVersion = ApiVersion::getVersion($request);
    $user = $this->dbHelper->getUsers($this->restHelper->getUserId())[0]->getArray($apiVersion);
    if ($apiVersion == ApiVersion::V2) {
      return $response->withJson($user, 200);
    }
    $userDao = $this->restHelper->getUserDao();
    $defaultGroup = $userDao->getUserAndDefaultGroupByUserName($user["name"])["group_name"];
    $user['default_group'] = $defaultGroup;
    return $response->withJson($user, 200);
  }

  /**
   * Updates the user details
   *
   * @param ServerRequestInterface $request
   * @param ResponseHelper $response
   * @param array $args
   * @return ResponseHelper
   * @throws HttpNotFoundException
   */
  public function updateUser($request, $response, $args)
  {
    $apiVersion = ApiVersion::getVersion($request);
    if ($apiVersion == ApiVersion::V2) {
      $user = $this->restHelper->getUserDao()->getUserByName($args['pathParam']);
      if ($user === null) {
        throw new HttpNotFoundException("UserId doesn't exist");
      }
      $id = intval($user['user_pk']);
    } else {
      $id = intval($args['pathParam']);
    }
    if ($id !== intval($this->restHelper->getUserId())) {
      $this->throwNotAdminException();
    }
    if (!$this->dbHelper->doesIdExist("users","user_pk", $id)) {
      throw new HttpNotFoundException("UserId doesn't exist");
    }
    $reqBody = $this->getParsedBody($request);
    $userHelper = new UserHelper($id);
    $returnVal = $userHelper->modifyUserDetails($reqBody, $apiVersion);
    return $response->withJson($returnVal->getArray(), $returnVal->getCode());
  }

  /**
   * Create a new REST API Token
   *
   * @param ServerRequestInterface $request
   * @param ResponseHelper $response
   * @param array $args
   * @return ResponseHelper
   * @throws HttpErrorException
   */
  public function createRestApiToken($request, $response, $args)
  {
    $reqBody = $this->getParsedBody($request);
    $tokenRequest = TokenRequest::fromArray($reqBody,
      ApiVersion::getVersion($request));
    $symfonyRequest = new \Symfony\Component\HttpFoundation\Request();

    // translating values for symfony request
    $symfonyRequest->request->set('pat_name', $tokenRequest->getTokenName());
    $symfonyRequest->request->set('pat_expiry', $tokenRequest->getTokenExpire());
    $symfonyRequest->request->set('pat_scope', $tokenRequest->getTokenScope());

    // initialising the user_edit plugin
    global $container;
    /** @var RestHelper $restHelper */
    $restHelper = $container->get('helper.restHelper');
    /** @var \UserEditPage $userEditObj */
    $userEditObj = $restHelper->getPlugin('user_edit');

    // creating the REST token
    try {
      $token = $userEditObj->generateNewToken($symfonyRequest);
    } catch (DuplicateTokenKeyException $e) {
      throw new HttpTooManyRequestException("Please try again later.", $e);
    } catch (DuplicateTokenNameException $e) {
      throw new HttpConflictException($e->getMessage(), $e);
    } catch (\UnexpectedValueException $e) {
      throw new HttpBadRequestException($e->getMessage(), $e);
    }

    $returnVal = new Info(201, "Token created successfully", InfoType::INFO);
    $res = $returnVal->getArray();
    $res['token'] = $token;
    return $response->withJson($res, $returnVal->getCode());
  }

  /**
   * Get all the REST API tokens (active | expired)
   *
   * @param ServerRequestInterface $request
   * @param ResponseHelper $response
   * @param array $args
   * @return ResponseHelper
   * @throws HttpBadRequestException
   */
  public function getTokens($request, $response, $args)
  {
    $apiVersion = ApiVersion::getVersion($request);
    $tokenType = $args['type'];
    if ($tokenType != "active" && $tokenType != "expired") {
      throw new HttpBadRequestException("Invalid request!");
    }
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
    $res[$tokenType . ($apiVersion == ApiVersion::V2 ? 'Tokens' : '_tokens')] = $finalTokens;
    return $response->withJson($res, $returnVal->getCode());
  }
}
