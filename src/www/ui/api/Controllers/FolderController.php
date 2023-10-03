<?php
/*
 SPDX-FileCopyrightText: © 2018 Siemens AG
 Author: Gaurav Mishra <mishra.gaurav@siemens.com>

 SPDX-License-Identifier: GPL-2.0-only
*/
/**
 * @file
 * @brief Controller for folder queries
 */

namespace Fossology\UI\Api\Controllers;

use Fossology\Lib\Dao\FolderDao;
use Fossology\UI\Ajax\AjaxFolderContents;
use Fossology\UI\Api\Helper\ResponseHelper;
use Fossology\UI\Api\Models\Folder;
use Fossology\UI\Api\Models\Info;
use Fossology\UI\Api\Models\InfoType;
use Psr\Http\Message\ServerRequestInterface;

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
   * @param ResponseHelper $response
   * @param array $args
   * @return ResponseHelper
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
   * @param ResponseHelper $response
   * @param array $args
   * @return ResponseHelper
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
   * @param ResponseHelper $response
   * @param array $args
   * @return ResponseHelper
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

      $rc = $folderDelete->Delete($folderId, $this->restHelper->getUserId());
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
   * @param ResponseHelper $response
   * @param array $args
   * @return ResponseHelper
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
   * @param ResponseHelper $response
   * @param array $args
   * @return ResponseHelper
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
          $folderDao->getFolderContentsId($folderId, $folderDao::MODE_FOLDER)
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

  /**
   * Get the unlinkable folder contents (contents which are copied)
   *
   * @param ServerRequestInterface $request
   * @param ResponseHelper $response
   * @param array $args
   * @return ResponseHelper
   */
  public function getUnlinkableFolderContents($request, $response, $args)
  {
    $folderId = $args['id'];
    $folderDao = $this->restHelper->getFolderDao();

    if ($folderDao->getFolder($folderId) === null) {
      $error = new Info(404, "Folder id not found!", InfoType::ERROR);
    } else if (! $folderDao->isFolderAccessible($folderId, $this->restHelper->getUserId())) {
      $error = new Info(403, "Folder is not accessible!", InfoType::ERROR);
    }

    if (isset($error)) {
      return $response->withJson($error->getArray(), $error->getCode());
    }

    /** @var AjaxFolderContents $folderContents */
    $folderContents = $this->restHelper->getPlugin('foldercontents');
    $symfonyRequest = new \Symfony\Component\HttpFoundation\Request();
    $symfonyRequest->request->set('folder', $folderId);
    $symfonyRequest->request->set('removable', 1);
    $symfonyRequest->request->set('fromRest', true);
    $res = $folderContents->handle($symfonyRequest);
    return $response->withJson($res, 200);
  }

  /**
   * Unlink the folder content from the parent
   *
   * @param ServerRequestInterface $request
   * @param ResponseHelper $response
   * @param array $args
   * @return ResponseHelper
   */
  public function unlinkFolder($request, $response, $args)
  {
    $folderContentId = $args['contentId'];
    if (!$this->dbHelper->doesIdExist("foldercontents", "foldercontents_pk", $folderContentId)) {
      $info = new Info(404, "Folder content id not found!", InfoType::ERROR);
    } else {
      /** @var FolderDao $folderDao */
      $folderDao = $this->container->get('dao.folder');
      if ($folderDao->removeContent($folderContentId)) {
        $info = new Info(200, "Folder unlinked successfully.", InfoType::INFO);
      } else {
        $info = new Info(400, "Content cannot be unlinked.", InfoType::ERROR);
      }
    }
    return $response->withJson($info->getArray(), $info->getCode());
  }
}
