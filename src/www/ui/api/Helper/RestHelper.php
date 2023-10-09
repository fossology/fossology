<?php
/*
 SPDX-FileCopyrightText: © 2017 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

/**
 * @file
 * @brief DAO helper functions for REST api.
 */
namespace Fossology\UI\Api\Helper;

use Fossology\Lib\Auth\Auth;
use Fossology\Lib\Dao\FolderDao;
use Fossology\Lib\Dao\JobDao;
use Fossology\Lib\Dao\ShowJobsDao;
use Fossology\Lib\Dao\UploadDao;
use Fossology\Lib\Dao\UploadPermissionDao;
use Fossology\Lib\Dao\UserDao;
use Fossology\Lib\Plugin\Plugin;
use Fossology\UI\Api\Exceptions\HttpBadRequestException;
use Fossology\UI\Api\Exceptions\HttpForbiddenException;
use Fossology\UI\Api\Models\Info;
use Fossology\UI\Api\Models\InfoType;

/**
 * @class RestHelper
 * @brief Provides various DAO helper functions for REST api
 */
class RestHelper
{
  /**
   * @var array VALID_SCOPES
   * Valid scopes for REST authentication tokens.
   */
  const VALID_SCOPES = ["read", "write"];
  /**
   * @var array SCOPE_DB_MAP
   * Maps a user readable scope to DB value.
   */
  const SCOPE_DB_MAP = ["read" => "r", "write" => "w"];
  /**
   * @var int TOKEN_KEY_LENGTH
   * Length of the token secret key.
   */
  const TOKEN_KEY_LENGTH = 40;
  /**
   * @var UploadDao $uploadDao
   * Upload DAO object
   */
  private $uploadDao;
  /**
   * @var DbHelper $dbHelper
   * DB helper object
   */
  private $dbHelper;
  /**
   * @var UploadPermissionDao $uploadPermissionDao
   * Upload permission DAO object
   */
  private $uploadPermissionDao;
  /**
   * @var FolderDao $folderDao
   * Folder DAO object
   */
  private $folderDao;
  /**
   * @var UserDao $userDao
   * User DAO object
   */
  private $userDao;
  /**
   * @var JobDao $jobDao
   * Job DAO object
   */
  private $jobDao;
  /**
   * @var ShowJobsDao $showJobDao
   * Show job DAO object
   */
  private $showJobDao;
  /**
   * @var AuthHelper $authHelper
   * Auth helper to provide authentication
   */
  private $authHelper;

  /**
   * @brief RestHelper constructor.
   *
   * This constructor initialize all the members
   */
  public function __construct(UploadPermissionDao $uploadPermissionDao,
    UploadDao $uploadDao, UserDao $userDao, FolderDao $folderDao,
    DbHelper $dbHelper, AuthHelper $authHelper, JobDao $jobDao,
    ShowJobsDao $showJobDao)
  {
    $this->uploadPermissionDao = $uploadPermissionDao;
    $this->uploadDao = $uploadDao;
    $this->userDao = $userDao;
    $this->folderDao = $folderDao;
    $this->dbHelper = $dbHelper;
    $this->authHelper = $authHelper;
    $this->jobDao = $jobDao;
    $this->showJobDao = $showJobDao;
  }

  /**
   * @return integer Current user id
   */
  public function getUserId()
  {
    $session = $this->authHelper->getSession();
    return $session->get(Auth::USER_ID);
  }

  /**
   * @return integer Current group id
   */
  public function getGroupId()
  {
    $session = $this->authHelper->getSession();
    return $session->get(Auth::GROUP_ID);
  }

  /**
   * @return UploadDao
   */
  public function getUploadDao()
  {
    return $this->uploadDao;
  }

  /**
   * @return UserDao
   */
  public function getUserDao()
  {
    return $this->userDao;
  }

  /**
   * @return FolderDao
   */
  public function getFolderDao()
  {
    return $this->folderDao;
  }

  /**
   * @return UploadPermissionDao
   */
  public function getUploadPermissionDao()
  {
    return $this->uploadPermissionDao;
  }

  /**
   * @return AuthHelper
   */
  public function getAuthHelper()
  {
    return $this->authHelper;
  }

  /**
   * @return DbHelper
   */
  public function getDbHelper()
  {
    return $this->dbHelper;
  }

  /**
   * @return JobDao
   */
  public function getJobDao()
  {
    return $this->jobDao;
  }

  /**
   * @return ShowJobsDao
   */
  public function getShowJobDao()
  {
    return $this->showJobDao;
  }

  /**
   * Copy/move a given upload id to a new folder id.
   * @param integer $uploadId Upload to copy/move
   * @param integer $newFolderId New folder id
   * @param boolean $isCopy Set true to perform copy, false to move
   * @return Fossology::UI::Api::Models::Info
   * @throws HttpForbiddenException If upload or folder is not accessible
   * @throws HttpBadRequestException If folder id is not a positive integer
   */
  public function copyUpload($uploadId, $newFolderId, $isCopy)
  {
    if (! is_numeric($newFolderId) || $newFolderId <= 0) {
      throw new HttpBadRequestException("Folder id should be a positive integer");
    }
    if (!$this->folderDao->isFolderAccessible($newFolderId, $this->getUserId())) {
      throw new HttpForbiddenException("Folder is not accessible.");
    }
    if (!$this->uploadPermissionDao->isAccessible($uploadId, $this->getGroupId())) {
      throw new HttpForbiddenException("Upload is not accessible.");
    }
    $uploadContentId = $this->folderDao->getFolderContentsId($uploadId,
      $this->folderDao::MODE_UPLOAD);
    /** @var \AdminContentMove $contentMove */
    $contentMove = $this->getPlugin('content_move');

    $errors = $contentMove->copyContent([$uploadContentId], $newFolderId, $isCopy);
    if (empty($errors)) {
      $action = $isCopy ? "copied" : "moved";
      $info = new Info(202, "Upload $uploadId will be $action to folder $newFolderId",
        InfoType::INFO);
    } else {
      $info = new Info(202, "Exceptions occurred: $errors",
        InfoType::ERROR);
    }
    return $info;
  }

  /**
   * @brief A safe wrapper around plugin_find
   *
   * Get the FOSSology plugin from the plugin array.
   *
   * @param string $pluginName The required plugin
   * @return Plugin The required plugin if found, else throws an exception.
   * @throws \UnexpectedValueException Throws exception when plugin is not
   *         found.
   * @uses plugin_find()
   */
  public function getPlugin($pluginName)
  {
    $plugin = plugin_find($pluginName);
    if (! $plugin) {
      throw new \UnexpectedValueException(
        "Unable to find plugin " . $pluginName);
    }
    return $plugin;
  }

  /**
   * @brief Check if the token request contains valid parameters.
   *
   * The function checks for following properties:
   * - The format of expiry parameter should be YYYY-MM-DD and should be +1
   *   from now().
   * - The length of token name should be between 0 and 40.
   * - The scope of token should be valid.
   *
   * @param string $tokenExpire The expiry of token requested.
   * @param string $tokenName The name of the token requested.
   * @param string $tokenScope The scope of the token requested.
   * @return void
   * @throws HttpBadRequestException If request is invalid.
   */
  public function validateTokenRequest($tokenExpire, $tokenName, $tokenScope)
  {
    $tokenValidity = $this->authHelper->getMaxTokenValidity();

    if (strtotime($tokenExpire) < strtotime("tomorrow") ||
      ! preg_match("/^[0-9]{4}-(0[1-9]|1[0-2])-(0[1-9]|[1-2][0-9]|3[0-1])$/",
        $tokenExpire) ||
      strtotime($tokenExpire) > strtotime("+$tokenValidity days")) {
      throw new HttpBadRequestException(
        "The token should have at least 1 day and max $tokenValidity days " .
        "of validity and should follow YYYY-MM-DD format.");
    } elseif (! in_array($tokenScope, RestHelper::VALID_SCOPES)) {
      throw new HttpBadRequestException(
        "Invalid token scope, allowed only " .
        join(",", RestHelper::VALID_SCOPES));
    } elseif (empty($tokenName) || strlen($tokenName) > 40) {
      throw new HttpBadRequestException(
        "The token name must be a valid string of max 40 character length");
    }
  }

  /**
   * @brief Check if the new oauth client is valid.
   *
   * The function checks for following properties:
   * - The length of client name should be between 0 and 40.
   * - The scope of client should be valid.
   * - Same client should not exist for the user.
   *
   * @param integer $userId User id
   * @param string $clientName The name of the new client.
   * @param string $clientScope The scope of the new client.
   * @param string $clientId New client id.
   * @return void
   * @throws HttpBadRequestException If request is invalid.
   */
  public function validateNewOauthClient($userId, $clientName, $clientScope,
                                        $clientId)
  {
    if (!in_array($clientScope, RestHelper::SCOPE_DB_MAP)) {
      throw new HttpBadRequestException("Invalid client scope, allowed only " .
        join(",", RestHelper::VALID_SCOPES));
    }
    if (empty($clientName) || strlen($clientName) > 40) {
      throw new HttpBadRequestException(
        "The client name must be a valid string of max 40 character length");
    }
    $sql = "SELECT 1 FROM personal_access_tokens " .
      "WHERE user_fk = $1 AND client_id = $2;";
    $rows = $this->dbHelper->getDbManager()->getSingleRow($sql, [
      $userId,
      $clientId
    ], __METHOD__);
    if (!empty($rows)) {
      throw new HttpBadRequestException("Client already added for the user.");
    }
  }
}
