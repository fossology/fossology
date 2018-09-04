<?php
/***************************************************************
Copyright (C) 2017 Siemens AG

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
 ***************************************************************/

/**
 * @file
 * @brief DAO helper functions for REST api.
 */
namespace Fossology\UI\Api\Helper;

use Fossology\Lib\Auth\Auth;
use Fossology\Lib\Dao\UploadPermissionDao;
use Fossology\Lib\Dao\UploadDao;
use Fossology\Lib\Dao\FolderDao;
use Fossology\Lib\Dao\UserDao;
use Fossology\UI\Api\Helper\StringHelper;
use Fossology\UI\Api\Helper\DbHelper;
use Fossology\UI\Api\Models\File;
use Fossology\UI\Api\Models\Info;
use Fossology\UI\Api\Models\InfoType;
use Monolog\Logger;
use Symfony\Component\HttpFoundation\Request;

require_once dirname(dirname(__FILE__)) . "/page/AdminContentMove.php";

/**
 * @class RestHelper
 * @brief Provides various DAO helper functions for REST api
 */
class RestHelper
{
  /**
   * @var StringHelper $stringHelper
   * String helper object
   */
  private $stringHelper;
  /**
   * @var Logger $logger
   * Logger to use
   */
  private $logger;
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
   * @var AuthHelper $authHelper
   * Auth helper to provide authentication
   */
  private $authHelper;
  /**
   * @var Request $request
   * Current Synfony request object
   */
  private $request;

  /**
   * @brief RestHelper constructor.
   *
   * This constructor initialize all the members
   * @param Request $request Current Synfony request object
   */
  public function __construct($request)
  {
    $this->dbHelper = new DbHelper();
    $this->stringHelper = new StringHelper();
    $this->logger = new Logger(__FILE__);
    $this->uploadPermissionDao = new UploadPermissionDao($this->dbHelper->getDbManager(), $this->logger);
    $this->uploadDao = new UploadDao($this->dbHelper->getDbManager(), $this->logger, $this->uploadPermissionDao);
    $this->userDao = new UserDao($this->dbHelper->getDbManager(), $this->logger);
    $this->folderDao = new FolderDao($this->dbHelper->getDbManager(), $this->userDao, $this->uploadDao);
    $this->authHelper = new AuthHelper($this->userDao);
    $this->request = $request;
  }

  /**
   * This method filters content that starts with ------WebKitFormBoundaryXXXXXXXXX
   * and ends with ------WebKitFormBoundaryXXXXXXXXX---
   * This is required, because the silex framework can't do that natively on put request
   * @param string $rawOutput
   * @return string
   */
  public function getFilteredFile($rawOutput)
  {
    $cutString = explode("\n",$rawOutput);
    $webKitBoundaryString = trim(str_replace("-", "",$cutString[0]));
    $contentDispositionString = trim(str_replace("-", "",$cutString[1]));
    $contentTypeString = trim($cutString[2]);

    $filename = explode("filename=", str_replace("\"", "",$contentDispositionString))[1];
    $contentTypeCut = explode("Content-Type:", $contentTypeString)[1];
    $content = $this->stringHelper->getContentBetweenString($rawOutput, array(0,1,2,3), $webKitBoundaryString);
    return new File($filename, $contentTypeCut, $content);
  }

  /**
   * @brief Check if the user is logged in.
   *
   * This method currently supports SIMPLE HTTP Auth.
   * @param string $authMethod Authentication method to use
   * @return boolean True if the user has access, false otherwise.
   * @sa Fossology::UI::Api::Helper::checkUsernameAndPassword()
   */
  public function hasUserAccess($authMethod)
  {
    if($authMethod === "SIMPLE_KEY") {
      $username = $this->request->headers->get("php-auth-user");
      $password = $this->request->headers->get("php-auth-pw");
      return $this->authHelper->checkUsernameAndPassword($username, $password);
    } else {
      return false;
    }
  }

  /**
   * @return Logger
   */
  public function getLogger()
  {
    return $this->logger;
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
   * Copy/move a given upload id to a new folder id.
   * @param integer $uploadId    Upload to copy/move
   * @param integer $newFolderId New folder id
   * @param boolean $isCopy      Set true to perform copy, false to move
   * @return Fossology::UI::Api::Models::Info
   */
  public function copyUpload($uploadId, $newFolderId, $isCopy)
  {
    if(is_numeric($newFolderId) && $newFolderId > 0)
    {
      if(!$this->folderDao->isFolderAccessible($newFolderId, $this->getUserId()))
      {
        return new Info(403, "Folder is not accessible.",
          InfoType::ERROR);
      }
      if(!$this->uploadPermissionDao->isAccessible($uploadId, $this->getGroupId()))
      {
        return new Info(403, "Upload is not accessible.",
          InfoType::ERROR);
      }
      $errors = (new AdminContentMove())->copyContent([$uploadId], $newFolderId, $isCopy);
      if(empty($errors))
      {
        $action = $isCopy ? "copied" : "moved";
        $info = new Info(202, "Upload $uploadId will be $action to $newFolderId",
          InfoType::INFO);
      }
      else
      {
        $info = new Info(202, "Exceptions occurred: $errors",
          InfoType::ERROR);
      }
      return $info;
    }
    else
    {
      return new Info(400, "Bad Request. Folder id should be a positive integer",
        InfoType::ERROR);
    }
  }
}
