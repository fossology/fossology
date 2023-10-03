<?php
/*
 SPDX-FileCopyrightText: © 2015, 2022 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\UI\Ajax;

use Fossology\Lib\Auth\Auth;
use Fossology\Lib\Dao\FolderDao;
use Fossology\Lib\Dao\UploadDao;
use Fossology\Lib\Data\UploadStatus;
use Fossology\Lib\Plugin\DefaultPlugin;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

class AjaxFolderContents extends DefaultPlugin
{
  const NAME = "foldercontents";

  /**
   * @var FolderDao $folderDao
   * FolderDao object
   */
  private $folderDao;

  function __construct()
  {
    parent::__construct(self::NAME, array(
        self::PERMISSION => Auth::PERM_WRITE
    ));
    $this->folderDao = $this->getObject('dao.folder');
  }

  public function handle(Request $request)
  {
    $folderId = intval($request->get('folder'));
    $uploadName = $request->get('upload');
    if (!empty($uploadName)) {
      return $this->uploadExists(Auth::getGroupId(), $folderId, $uploadName);
    }
    $results = array();
    $childFolders = $this->folderDao->getFolderChildFolders($folderId);
    foreach ($childFolders as $folder) {
      $results[$folder['foldercontents_pk']] = '/'.$folder['folder_name'];
    }
    $childUploads = $this->folderDao->getFolderChildUploads($folderId, Auth::getGroupId());
    foreach ($childUploads as $upload) {
      $uploadStatus = new UploadStatus();
      $uploadDate = explode(".", $upload['upload_ts'])[0];
      $uploadStatus = " (" . $uploadStatus->getTypeName($upload['status_fk']) . ")";
      $results[$upload['foldercontents_pk']] = $upload['upload_filename'] . _(" from ") . Convert2BrowserTime($uploadDate) . $uploadStatus;
    }

    if (!$request->get('removable')) {
      if ($request->get('fromRest')) {
        return array_map(function($key, $value) {
          return array(
            'id' => $key,
            'content' => $value,
            'removable' => false
          );
        }, array_keys($results), $results);
      }
      return new JsonResponse($results);
    }

    $filterResults = array();
    foreach ($this->folderDao->getRemovableContents($folderId) as $content) {
      $filterResults[$content] = $results[$content];
    }

    if ($request->get('fromRest')) {
      return array_map(function ($key, $value) {
        return array(
          'id' => $key,
          'content' => $value,
          'removable' => true
        );
      }, array_keys($filterResults), $filterResults);
    }

    if (empty($filterResults)) {
      $filterResults["-1"] = "No removable content found";
    }

    return new JsonResponse($filterResults);
  }

  /**
   * Check if upload with given name exists in given folder.
   *
   * @param int $groupId       Relevant group id
   * @param int $folderId      Folder to search in
   * @param string $uploadName Upload name to search
   *
   * @return JsonResponse Json response with upload id in `upload` key and
   *                      upload date in `date` key. Upload will be false
   *                      and date will be null if not found.
   */
  private function uploadExists($groupId, $folderId, $uploadName)
  {
    $childUploads = $this->folderDao->getFolderChildUploads($folderId, $groupId);
    $found = false;
    $date = null;
    foreach ($childUploads as $upload) {
      if (strcasecmp($upload['upload_filename'], $uploadName) === 0) {
        $found = $upload['upload_pk'];
        $date = Convert2BrowserTime($upload['upload_ts']);
        break;
      }
    }
    if ($found) {
      /**
       * @var UploadDao $uploadDao
       * UploadDao object
       */
      $uploadDao = $this->getObject('dao.upload');
      $parent = $uploadDao->getUploadParent($found);
      $found = tracebackTotalUri() . "?mod=browse&upload=$found&item=$parent";
    }
    return new JsonResponse(["upload" => $found, "date" => $date]);
  }
}

register_plugin(new AjaxFolderContents());
