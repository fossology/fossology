<?php
/***************************************************************
 Copyright (C) 2018 Siemens AG
 Author: Gaurav Mishra <mishra.gaurav@siemens.com>

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
 * @brief Controller for folder queries
 */

namespace Fossology\UI\Api\Controllers;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Fossology\Lib\Auth\Auth;
use Fossology\UI\Api\Models\Folder;
use Fossology\UI\Api\Models\Info;
use Fossology\UI\Api\Models\InfoType;

/**
 * @class FolderController
 * @brief Controller for Folder model
 */
class FolderController extends RestController
{

  /**
   * Get all folders accessible by the user
   *
   * @param ServerRequestInterface $request
   * @param ResponseInterface $response
   * @param array $args
   * @return ResponseInterface
   */
  public function getFolders($request, $response, $args)
  {
    $id = null;
    $allUserFolders = null;

    $folderDao = $this->restHelper->getFolderDao();
    if (isset($args['id'])) {
      $id = intval($args['id']);
      $returnVal = null;
      if (! $folderDao->isFolderAccessible($id)) {
        $returnVal = new Info(403, "Folder id $id is not accessible",
          InfoType::ERROR);
      }
      if ($folderDao->getFolder($id) === null) {
        $returnVal = new Info(404, "Folder id $id does not exists",
          InfoType::ERROR);
      }
      if ($returnVal !== null) {
        return $response->withJson($returnVal->getArray(), $returnVal->getCode());
      }
      $allUserFolders = [
        $id
      ];
    } else {
      $rootFolder = $folderDao->getRootFolder($this->restHelper->getUserId())->getId();
      $allUserFolders = array();
      GetFolderArray($rootFolder, $allUserFolders);
      $allUserFolders = array_keys($allUserFolders);
    }
    $foldersList = array();
    foreach ($allUserFolders as $folderId) {
      $folder = $folderDao->getFolder($folderId);
      $parentId = $folderDao->getFolderParentId($folderId);
      $folderModel = new Folder($folder->getId(), $folder->getName(),
        $folder->getDescription(), $parentId);
      $foldersList[] = $folderModel->getArray();
    }
    if ($id !== null) {
      $foldersList = $foldersList[0];
    }
    return $response->withJson($foldersList, 200);
  }

  /**
   * Create a new folder
   *
   * @param ServerRequestInterface $request
   * @param ResponseInterface $response
   * @param array $args
   * @return ResponseInterface
   */
  public function createFolder($request, $response, $args)
  {
    $info = null;
    $parentFolder = $request->getHeaderLine('parentFolder');
    $folderName = trim($request->getHeaderLine('folderName'));
    $folderDescription = trim($request->getHeaderLine('folderDescription'));

    if (! is_numeric($parentFolder) || $parentFolder < 0) {
      $info = new Info(400, "Parent folder id must be a positive integer!",
        InfoType::ERROR);
    }
    if (empty($folderName)) {
      $info = new Info(400, "Folder name can not be empty!", InfoType::ERROR);
    }
    if (! $this->restHelper->getFolderDao()->isFolderAccessible($parentFolder,
      $this->restHelper->getUserId())) {
      $info = new Info(403, "Parent folder can not be accessed!", InfoType::ERROR);
    }
    if ($info !== null) {
      return $response->withJson($info->getArray(), $info->getCode());
    }
    $folderCreate = $this->restHelper->getPlugin('folder_create');
    $rc = $folderCreate->create($parentFolder, $folderName, $folderDescription);
    if ($rc == 4) {
      $info = new Info(200, "Folder $folderName already exists!", InfoType::INFO);
    } elseif ($rc == 0) {
      $info = new Info(404, "Parent folder not found!", InfoType::ERROR);
    } else {
      $folderId = $this->restHelper->getFolderDao()->getFolderId($folderName, $parentFolder);
      $info = new Info(201, intval($folderId), InfoType::INFO);
    }
    return $response->withJson($info->getArray(), $info->getCode());
  }

  /**
   * Delete a folder all sub-folders and uploads within the folder
   *
   * @param ServerRequestInterface $request
   * @param ResponseInterface $response
   * @param array $args
   * @return ResponseInterface
   */
  public function deleteFolder($request, $response, $args)
  {
    $info = null;
    $folderDao = $this->restHelper->getFolderDao();
    $folderId = $args['id'];

    if (! is_numeric($folderId) || $folderId < 0) {
      $info = new Info(400, "Folder id must be a positive integer!",
        InfoType::ERROR);
    } elseif ($folderDao->getFolder($folderId) === null) {
      $info = new Info(404, "Folder id not found!", InfoType::ERROR);
    } else {
      $folderDelete = $this->restHelper->getPlugin('admin_folder_delete');
      $folderName = FolderGetName($folderId);
      $folderArray = Folder2Path($folderId);
      $folderParent = intval($folderArray[count($folderArray) - 2]['folder_pk']);
      $folderId = "$folderParent $folderId";

      $rc = $folderDelete->Delete($folderId, Auth::getUserId());
      if ($rc == "No access to delete this folder") {
        $info = new Info(403, $rc, InfoType::ERROR);
      } elseif ($rc !== null) {
        $info = new Info(500, $rc, InfoType::ERROR);
      } else {
        $info = new Info(202, "Folder, \"$folderName\" deleted.", InfoType::INFO);
      }
    }
    return $response->withJson($info->getArray(), $info->getCode());
  }

  /**
   * Change the description/name of the folder
   *
   * @param ServerRequestInterface $request
   * @param ResponseInterface $response
   * @param array $args
   * @return ResponseInterface
   */
  public function editFolder($request, $response, $args)
  {
    $info = null;
    $folderDao = $this->restHelper->getFolderDao();
    $folderId = $args['id'];
    $newName = $request->getHeaderLine('name');
    $newDesc = $request->getHeaderLine('description');

    if ($folderDao->getFolder($folderId) === null) {
      $info = new Info(404, "Folder id not found!", InfoType::ERROR);
    } elseif (! $folderDao->isFolderAccessible($folderId, $this->restHelper->getUserId())) {
      $info = new Info(403, "Folder is not accessible!", InfoType::ERROR);
    } else {
      $folderEdit = $this->restHelper->getPlugin('folder_properties');
      $folderName = FolderGetName($folderId);
      $folderEdit->Edit($folderId, $newName, $newDesc);
      $info = new Info(200, "Folder \"$folderName\" updated.", InfoType::INFO);
    }
    return $response->withJson($info->getArray(), $info->getCode());
  }

  /**
   * Copy/move the folder
   *
   * @param ServerRequestInterface $request
   * @param ResponseInterface $response
   * @param array $args
   * @return ResponseInterface
   */
  public function copyFolder($request, $response, $args)
  {
    $info = null;
    $folderDao = $this->restHelper->getFolderDao();
    $folderId = $args['id'];
    $newParent = $request->getHeaderLine('parent');
    $action = strtolower($request->getHeaderLine('action'));

    if (! is_numeric($newParent) || $newParent < 0) {
      $info = new Info(400, "Parent id must be a positive integer!",
        InfoType::ERROR);
    } elseif ($folderDao->getFolder($folderId) === null) {
      $info = new Info(404, "Folder id not found!", InfoType::ERROR);
    } elseif ($folderDao->getFolder($newParent) === null) {
      $info = new Info(404, "Parent folder not found!", InfoType::ERROR);
    } elseif (! $folderDao->isFolderAccessible($folderId,
      $this->restHelper->getUserId())) {
      $info = new Info(403, "Folder is not accessible!", InfoType::ERROR);
    } elseif (! $folderDao->isFolderAccessible($newParent,
      $this->restHelper->getUserId())) {
      $info = new Info(403, "Parent folder is not accessible!", InfoType::ERROR);
    } elseif (strcmp($action, "copy") != 0 && strcmp($action, "move") != 0) {
      $info = new Info(400, "Action can be one of [copy,move]!", InfoType::ERROR);
    } else {
      $folderMove = $this->restHelper->getPlugin('content_move');
      $folderName = FolderGetName($folderId);
      $parentFolderName = FolderGetName($newParent);
      $isCopy = (strcmp($action, "copy") == 0);
      $message = $folderMove->copyContent(
        [
          $folderDao->getFolderContentsId($folderId)
        ], $newParent, $isCopy);
      if (empty($message)) {
        $info = new Info(202,
          "Folder \"$folderName\" $action(ed) under \"$parentFolderName\".",
          InfoType::INFO);
      } else {
        $info = new Info(500, $message, InfoType::ERROR);
      }
    }
    return $response->withJson($info->getArray(), $info->getCode());
  }
}
