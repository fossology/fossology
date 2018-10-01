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
use Fossology\UI\Api\Models\Info;
use Fossology\UI\Api\Models\InfoType;
use Fossology\UI\Api\Models\Analysis;
use Fossology\UI\Api\Models\Decider;
use Fossology\UI\Api\Models\Reuser;
use Fossology\UI\Api\Models\ScanOptions;
use Fossology\Lib\Auth\Auth;
use Fossology\UI\Api\Models\Folder;

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

    $folderDao = $this->container->get('dao.folder');
    if (isset($args['id'])) {
      $id = intval($args['id']);
      $returnVal = null;
      if (! $folderDao->isFolderAccessible($id)) {
        $returnVal = new Info(403, "Folder id $id is not accessible",
          InfoType::ERROR);
      }
      if (! $this->dbHelper->doesIdExist("folder", "folder_pk", $id)) {
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
      $rootFolder = $folderDao->getRootFolder(Auth::getUserId())->getId();
      $allUserFolders = array();
      GetFolderArray($rootFolder, $allUserFolders);
      $allUserFolders = array_keys($allUserFolders);
    }
    $foldersList = array();
    foreach ($allUserFolders as $folderId) {
      $folder = $folderDao->getFolder($folderId);
      $folderModel = new Folder($folder->getId(), $folder->getName(), $folder->getDescription());
      $foldersList[] = $folderModel->getArray();
    }
    if ($id !== null) {
      $foldersList = $foldersList[0];
    }
    return $response->withJson($foldersList, 200);
  }
}
