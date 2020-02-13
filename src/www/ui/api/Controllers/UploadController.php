<?php
/***************************************************************
 Copyright (C) 2018,2020 Siemens AG
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
 * @brief Controller for upload queries
 */

namespace Fossology\UI\Api\Controllers;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Fossology\DelAgent\UI\DeleteMessages;
use Fossology\UI\Page\UploadPageBase;
use Fossology\UI\Api\Models\Info;
use Fossology\UI\Api\Models\InfoType;
use Fossology\UI\Api\Helper\UploadHelper;

/**
 * @class UploadController
 * @brief Controller for Upload model
 */
class UploadController extends RestController
{

  /**
   * Get list of uploads for current user
   *
   * @param ServerRequestInterface $request
   * @param ResponseInterface $response
   * @param array $args
   * @return ResponseInterface
   */
  public function getUploads($request, $response, $args)
  {
    $id = null;
    if (isset($args['id'])) {
      $id = intval($args['id']);
      $upload = $this->uploadAccessible($this->restHelper->getGroupId(), $id);
      if ($upload !== true) {
        return $response->withJson($upload->getArray(), $upload->getCode());
      }
      $temp = $this->isAdj2nestDone($id, $response);
      if ($temp !== true) {
        return $temp;
      }
    }
    $uploads = $this->dbHelper->getUploads($this->restHelper->getUserId(), $id);
    if ($id !== null) {
      $uploads = $uploads[0];
    }
    return $response->withJson($uploads, 200);
  }

  /**
   * Get summary of given upload
   *
   * @param ServerRequestInterface $request
   * @param ResponseInterface $response
   * @param array $args
   * @return ResponseInterface
   */
  public function getUploadSummary($request, $response, $args)
  {
    $id = intval($args['id']);
    $upload = $this->uploadAccessible($this->restHelper->getGroupId(), $id);
    if ($upload !== true) {
      return $response->withJson($upload->getArray(), $upload->getCode());
    }
    $temp = $this->isAdj2nestDone($id, $response);
    if ($temp !== true) {
      return $temp;
    }
    $uploadHelper = new UploadHelper();
    $uploadSummary = $uploadHelper->generateUploadSummary($id,
      $this->restHelper->getGroupId());
    return $response->withJson($uploadSummary->getArray(), 200);
  }

  /**
   * Delete a given upload
   *
   * @param ServerRequestInterface $request
   * @param ResponseInterface $response
   * @param array $args
   * @return ResponseInterface
   */
  public function deleteUpload($request, $response, $args)
  {
    require_once dirname(dirname(dirname(dirname(__DIR__)))) .
      "/delagent/ui/delete-helper.php";
    $returnVal = null;
    $id = intval($args['id']);

    $upload = $this->uploadAccessible($this->restHelper->getGroupId(), $id);
    if ($upload !== true) {
      return $response->withJson($upload->getArray(), $upload->getCode());
    }
    $result = TryToDelete($id, $this->restHelper->getUserId(),
      $this->restHelper->getGroupId(), $this->restHelper->getUploadDao());
    if ($result->getDeleteMessageCode() !== DeleteMessages::SUCCESS) {
      $returnVal = new Info(500, $result->getDeleteMessageString(),
        InfoType::ERROR);
    } else {
      $returnVal = new Info(202, "Delete Job for file with id " . $id,
        InfoType::INFO);
    }
    return $response->withJson($returnVal->getArray(), $returnVal->getCode());
  }

  /**
   * Copy a given upload to a new folder
   *
   * @param ServerRequestInterface $request
   * @param ResponseInterface $response
   * @param array $args
   * @return ResponseInterface
   */
  public function copyUpload($request, $response, $args)
  {
    return $this->changeUpload($request, $response, $args, true);
  }

  /**
   * Move a given upload to a new folder
   *
   * @param ServerRequestInterface $request
   * @param ResponseInterface $response
   * @param array $args
   * @return ResponseInterface
   */
  public function moveUpload($request, $response, $args)
  {
    return $this->changeUpload($request, $response, $args, false);
  }

  /**
   * Perform copy/move based on $isCopy
   *
   * @param ServerRequestInterface $request
   * @param ResponseInterface $response
   * @param array $args
   * @param boolean $isCopy True to perform copy, else false
   * @return ResponseInterface
   */
  private function changeUpload($request, $response, $args, $isCopy)
  {
    $returnVal = null;
    if ($request->hasHeader('folderId') &&
      is_numeric($newFolderID = $request->getHeaderLine('folderId'))) {
      $id = intval($args['id']);
      $returnVal = $this->restHelper->copyUpload($id, $newFolderID, $isCopy);
    } else {
      $returnVal = new Info(400, "folderId header should be an integer!",
        InfoType::ERROR);
    }
    return $response->withJson($returnVal->getArray(), $returnVal->getCode());
  }

  /**
   * Get a new upload from the POST method
   *
   * @param ServerRequestInterface $request
   * @param ResponseInterface $response
   * @param array $args
   * @return ResponseInterface
   */
  public function postUpload($request, $response, $args)
  {
    $uploadHelper = new UploadHelper();
    if ($request->hasHeader('folderId') &&
      is_numeric($folderId = $request->getHeaderLine('folderId')) && $folderId > 0) {

      $allFolderIds = $this->restHelper->getFolderDao()->getAllFolderIds();
      if (!in_array($folderId, $allFolderIds)) {
        $error = new Info(404, "folderId $folderId does not exists!", InfoType::ERROR);
        return $response->withJson($error->getArray(), $error->getCode());
      }
      if (!$this->restHelper->getFolderDao()->isFolderAccessible($folderId)) {
        $error = new Info(403, "folderId $folderId is not accessible!",
          InfoType::ERROR);
        return $response->withJson($error->getArray(), $error->getCode());
      }

      $description = $request->getHeaderLine('uploadDescription');
      $public = $request->getHeaderLine('public');
      $public = empty($public) ? 'protected' : $public;
      $ignoreScm = $request->getHeaderLine('ignoreScm');
      list ($status, $message, $statusDescription, $uploadId) = $uploadHelper->createNewUpload(
        $request, $folderId, $description, $public, $ignoreScm);
      if (! $status) {
        $info = new Info($uploadId != -1 ? $uploadId : 500,
          $message . "\n" . $statusDescription,
          InfoType::ERROR);
      } else {
        $info = new Info(201, intval($uploadId), InfoType::INFO);
      }
      return $response->withJson($info->getArray(), $info->getCode());
    } else {
      $error = new Info(400, "folderId must be a positive integer!",
        InfoType::ERROR);
      return $response->withJson($error->getArray(), $error->getCode());
    }
  }

  /**
   * Check if upload is accessible
   * @param integer $groupId Group ID
   * @param integer $id      Upload ID
   * @return Fossology::UI::Api::Models::Info|boolean Info object on failure or
   *         true otherwise
   */
  private function uploadAccessible($groupId, $id)
  {
    if (! $this->dbHelper->doesIdExist("upload", "upload_pk", $id)) {
      return new Info(404, "Upload does not exist", InfoType::ERROR);
    } else if (! $this->restHelper->getUploadDao()->isAccessible($id, $groupId)) {
      return new Info(403, "Upload is not accessible", InfoType::ERROR);
    }
    return true;
  }

  /**
   * Check if adj2nest agent finished on upload
   * @param integer $id Upload ID
   * @param ResponseInterface $response
   * @return ResponseInterface|boolean Response if failure, true otherwise
   */
  private function isAdj2nestDone($id, $response)
  {
    $itemTreeBounds = $this->restHelper->getUploadDao()->getParentItemBounds(
      $id);
    if ($itemTreeBounds === false || empty($itemTreeBounds->getLeft())) {
      $returnVal = new Info(503,
        "Ununpack job not started. Please check job status at " .
        "/api/v1/jobs?upload=" . $id, InfoType::INFO);
      return $response->withHeader('Retry-After', '60')
        ->withHeader('Look-at', "/api/v1/jobs?upload=" . $id)
        ->withJson($returnVal->getArray(), $returnVal->getCode());
    }
    return true;
  }
}
