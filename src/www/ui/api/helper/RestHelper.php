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

require_once "StringHelper.php";
require_once __DIR__ . "/../models/File.php";
require_once dirname(dirname(__FILE__)) . "/page/AdminContentMove.php";

use \Fossology\Lib\Dao\UploadPermissionDao;
use \Fossology\Lib\Dao\UploadDao;
use Monolog\Logger;
use www\ui\api\helper\DbHelper;
use \www\ui\api\models\File;
use \www\ui\api\models\Info;
use \www\ui\api\models\InfoType;
use \www\ui\api\helper\StringHelper;
use \Fossology\Lib\Dao\FolderDao;
use \Fossology\Lib\Dao\UserDao;
use \Fossology\Lib\Auth\Auth;
use Symfony\Component\HttpFoundation\Request;

class RestHelper
{
  private $stringHelper;
  private $logger;
  private $uploadDao;
  private $dbHelper;
  private $uploadPermissionDao;
  private $folderDao;
  private $userDao;
  private $authHelper;
  private $request;

  /**
   * RestHelper constructor.
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
   * @param $rawOutput
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
   * @return \Monolog\Logger
   */
  public function getLogger()
  {
    return $this->logger;
  }

  /**
   * @return User ID
   */
  public function getUserId()
  {
    $session = $this->authHelper->getSession();
    return $session->get(Auth::USER_ID);
  }

  /**
   * @return Group ID
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
