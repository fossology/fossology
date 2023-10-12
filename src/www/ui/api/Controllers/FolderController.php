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
use Fossology\UI\Api\Exceptions\HttpBadRequestException;
use Fossology\UI\Api\Exceptions\HttpErrorException;
use Fossology\UI\Api\Exceptions\HttpForbiddenException;
use Fossology\UI\Api\Exceptions\HttpInternalServerErrorException;
use Fossology\UI\Api\Exceptions\HttpNotFoundException;
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
   * @throws HttpErrorException
   */
  public function getFolders($request, $response, $args)
  {
    $id = null;
    $allUserFolders = null;

    $folderDao = $this->restHelper->getFolderDao();
    if (isset($args['id'])) {
      $id = intval($args['id']);
      if (! $folderDao->isFolderAccessible($id)) {
        throw new HttpForbiddenException("Folder id $id is not accessible");
      }
      if ($folderDao->getFolder($id) === null) {
        throw new HttpNotFoundException("Folder id $id does not exists");
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
   * @throws HttpErrorException
   */
  public function createFolder($request, $response, $args)
  {
    $parentFolder = $request->getHeaderLine('parentFolder');
    $folderName = trim($request->getHeaderLine('folderName'));
    $folderDescription = trim($request->getHeaderLine('folderDescription'));

    if (! is_numeric($parentFolder) || $parentFolder < 0) {
      throw new HttpBadRequestException(
        "Parent folder id must be a positive integer!");
    }
    if (empty($folderName)) {
      throw new HttpBadRequestException("Folder name can not be empty!");
    }
    if (! $this->restHelper->getFolderDao()->isFolderAccessible($parentFolder,
        $this->restHelper->getUserId())) {
      throw new HttpForbiddenException("Parent folder is not accessible!");
    }
    /** @var \folder_create $folderCreate */
    $folderCreate = $this->restHelper->getPlugin('folder_create');
    $rc = $folderCreate->create($parentFolder, $folderName, $folderDescription);
    if ($rc == 4) {
      $info = new Info(200, "Folder $folderName already exists!", InfoType::INFO);
    } elseif ($rc == 0) {
      throw new HttpNotFoundException("Parent folder not found!");
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
   * @throws HttpErrorException
   */
  public function deleteFolder($request, $response, $args)
  {
    $info = null;
    $folderDao = $this->restHelper->getFolderDao();
    $folderId = $args['id'];

    if (! is_numeric($folderId) || $folderId < 0) {
      throw new HttpBadRequestException(
        "Folder id must be a positive integer!");
    }
    if ($folderDao->getFolder($folderId) === null) {
      throw new HttpNotFoundException("Folder id not found!");
    }
    /** @var \admin_folder_delete $folderDelete */
    $folderDelete = $this->restHelper->getPlugin('admin_folder_delete');
    $folderName = FolderGetName($folderId);
    $folderArray = Folder2Path($folderId);
    $folderParent = intval($folderArray[count($folderArray) - 2]['folder_pk']);
    $folderId = "$folderParent $folderId";

    $rc = $folderDelete->Delete($folderId, $this->restHelper->getUserId());
    if ($rc == "No access to delete this folder") {
      throw new HttpForbiddenException($rc);
    } elseif ($rc !== null) {
      throw new HttpInternalServerErrorException($rc);
    }
    $info = new Info(202, "Folder, \"$folderName\" deleted.", InfoType::INFO);
    return $response->withJson($info->getArray(), $info->getCode());
  }

  /**
   * Change the description/name of the folder
   *
   * @param ServerRequestInterface $request
   * @param ResponseHelper $response
   * @param array $args
   * @return ResponseHelper
   * @throws HttpErrorException
   */
  public function editFolder($request, $response, $args)
  {
    $folderDao = $this->restHelper->getFolderDao();
    $folderId = $args['id'];
    $newName = $request->getHeaderLine('name');
    $newDesc = $request->getHeaderLine('description');

    if ($folderDao->getFolder($folderId) === null) {
      throw new HttpNotFoundException("Folder id not found!");
    }
    if (! $folderDao->isFolderAccessible($folderId, $this->restHelper->getUserId())) {
      throw new HttpForbiddenException("Folder is not accessible!");
    }
    /** @var \folder_properties $folderEdit */
    $folderEdit = $this->restHelper->getPlugin('folder_properties');
    $folderName = FolderGetName($folderId);
    $folderEdit->Edit($folderId, $newName, $newDesc);
    $info = new Info(200, "Folder \"$folderName\" updated.", InfoType::INFO);
    return $response->withJson($info->getArray(), $info->getCode());
  }

  /**
   * Copy/move the folder
   *
   * @param ServerRequestInterface $request
   * @param ResponseHelper $response
   * @param array $args
   * @return ResponseHelper
   * @throws HttpErrorException
   */
  public function copyFolder($request, $response, $args)
  {
    $folderDao = $this->restHelper->getFolderDao();
    $folderId = $args['id'];
    $newParent = $request->getHeaderLine('parent');
    $action = strtolower($request->getHeaderLine('action'));

    if (! is_numeric($newParent) || $newParent < 0) {
      throw new HttpBadRequestException(
        "Parent id must be a positive integer!");
    }
    if ($folderDao->getFolder($folderId) === null) {
      throw new HttpNotFoundException("Folder id not found!");
    }
    if ($folderDao->getFolder($newParent) === null) {
      throw new HttpNotFoundException("Parent folder id not found!");
    }
    if (! $folderDao->isFolderAccessible($folderId,
        $this->restHelper->getUserId())) {
      throw new HttpForbiddenException("Folder is not accessible!");
    }
    if (! $folderDao->isFolderAccessible($newParent,
        $this->restHelper->getUserId())) {
      throw new HttpForbiddenException("Parent folder is not accessible!");
    }
    if (strcmp($action, "copy") != 0 && strcmp($action, "move") != 0) {
      throw new HttpBadRequestException(
        "Action can be one of [copy,move]!");
    }
    /** @var \AdminContentMove $folderMove */
    $folderMove = $this->restHelper->getPlugin('content_move');
    $folderName = FolderGetName($folderId);
    $parentFolderName = FolderGetName($newParent);
    $isCopy = (strcmp($action, "copy") == 0);
    $message = $folderMove->copyContent(
      [
        $folderDao->getFolderContentsId($folderId, $folderDao::MODE_FOLDER)
      ], $newParent, $isCopy);
    if (!empty($message)) {
      throw new HttpInternalServerErrorException($message);
    }
    $info = new Info(202,
      "Folder \"$folderName\" $action(ed) under \"$parentFolderName\".",
      InfoType::INFO);
    return $response->withJson($info->getArray(), $info->getCode());
  }

  /**
   * Get the unlinkable folder contents (contents which are copied)
   *
   * @param ServerRequestInterface $request
   * @param ResponseHelper $response
   * @param array $args
   * @return ResponseHelper
   * @throws HttpErrorException
   */
  public function getUnlinkableFolderContents($request, $response, $args)
  {
    $folderId = $args['id'];
    $folderDao = $this->restHelper->getFolderDao();

    if ($folderDao->getFolder($folderId) === null) {
      throw new HttpNotFoundException("Folder id not found!");
    }
    if (! $folderDao->isFolderAccessible($folderId, $this->restHelper->getUserId())) {
      throw new HttpForbiddenException("Folder is not accessible!");
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
   * @throws HttpErrorException
   */
  public function unlinkFolder($request, $response, $args)
  {
    $folderContentId = $args['contentId'];
    if (!$this->dbHelper->doesIdExist("foldercontents", "foldercontents_pk", $folderContentId)) {
      throw new HttpNotFoundException("Folder content id not found!");
    }
    /** @var FolderDao $folderDao */
    $folderDao = $this->container->get('dao.folder');
    if (!$folderDao->removeContent($folderContentId)) {
      throw new HttpBadRequestException("Content cannot be unlinked.");
    }
    $info = new Info(200, "Folder unlinked successfully.", InfoType::INFO);
    return $response->withJson($info->getArray(), $info->getCode());
  }

  /**
   * Get the all folder contents
   *
   * @param ServerRequestInterface $request
   * @param ResponseHelper $response
   * @param array $args
   * @return ResponseHelper
   * @throws HttpErrorException
   */
  public function getAllFolderContents($request, $response, $args)
  {
    $folderId = $args['id'];
    $folderDao = $this->restHelper->getFolderDao();

    if ($folderDao->getFolder($folderId) === null) {
      throw new HttpNotFoundException("Folder id not found!");
    }
    if (! $folderDao->isFolderAccessible($folderId, $this->restHelper->getUserId())) {
      throw new HttpForbiddenException("Folder is not accessible!");
    }

    /** @var AjaxFolderContents $folderContents */
    $folderContents = $this->restHelper->getPlugin('foldercontents');
    $symfonyRequest = new \Symfony\Component\HttpFoundation\Request();
    $symfonyRequest->request->set('folder', $folderId);
    $symfonyRequest->request->set('fromRest', true);
    $contentList = $folderContents->handle($symfonyRequest);
    $removableContents = $folderDao->getRemovableContents($folderId);

    foreach ($contentList as &$value) {
      if (in_array($value['id'], $removableContents)) {
        $value['removable'] = true;
      }
    }
    return $response->withJson($contentList, 200);
  }
}
