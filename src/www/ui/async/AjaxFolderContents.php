<?php
/***********************************************************
 * Copyright (C) 2015 Siemens AG
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * version 2 as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 ***********************************************************/

namespace Fossology\UI\Ajax;

use Fossology\Lib\Auth\Auth;
use Fossology\Lib\Dao\FolderDao;
use Fossology\Lib\Data\UploadStatus;
use Fossology\Lib\Plugin\DefaultPlugin;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

class AjaxFolderContents extends DefaultPlugin
{
  const NAME = "foldercontents";

  function __construct()
  {
    parent::__construct(self::NAME, array(
        self::PERMISSION => Auth::PERM_WRITE
    ));
  }

  protected function handle(Request $request)
  {
    $folderId = intval($request->get('folder'));
    /* @var $folderDao FolderDao */
    $folderDao = $this->getObject('dao.folder');
    $results = array();
    $childFolders = $folderDao->getFolderChildFolders($folderId);
    foreach ($childFolders as $folder) {
      $results[$folder['foldercontents_pk']] = '/'.$folder['folder_name'];
    }
    $childUploads = $folderDao->getFolderChildUploads($folderId, Auth::getGroupId());
    foreach ($childUploads as $upload) {
      $uploadStatus = new UploadStatus();
      $uploadDate = explode(".",$upload['upload_ts'])[0];
      $uploadStatus = " (" . $uploadStatus->getTypeName($upload['status_fk']) . ")";
      $results[$upload['foldercontents_pk']] = $upload['upload_filename'] . _(" from ") . Convert2BrowserTime($uploadDate) . $uploadStatus;
    }

    if (!$request->get('removable')) {
      return new JsonResponse($results);
    }

    $filterResults = array();
    foreach ($folderDao->getRemovableContents($folderId) as $content) {
      $filterResults[$content] = $results[$content];
    }
    if (empty($filterResults)) {
      $filterResults["-1"] = "No removable content found";
    }
    return new JsonResponse($filterResults);
  }
}

register_plugin(new AjaxFolderContents());
