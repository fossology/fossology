<?php
/*
 SPDX-FileCopyrightText: © 2008-2013 Hewlett-Packard Development Company, L.P.
 SPDX-FileCopyrightText: © 2015 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

use Fossology\Lib\Auth\Auth;
use Fossology\Lib\Dao\FolderDao;
use Fossology\Lib\Dao\UploadDao;
use Fossology\Lib\Data\Upload\UploadProgress;
use Fossology\Lib\Db\DbManager;

class upload_properties extends FO_Plugin
{
  /** @var UploadDao */
  private $uploadDao;
  /** @var DbManager */
  private $dbManager;
  /** @var FolderDao */
  private $folderDao;

  function __construct()
  {
    $this->Name = "upload_properties";
    $this->Title = _("Edit Uploaded File Properties");
    $this->MenuList = "Organize::Uploads::Edit Properties";
    $this->DBaccess = PLUGIN_DB_WRITE;
    parent::__construct();
    $this->uploadDao = $GLOBALS['container']->get('dao.upload');
    $this->dbManager = $GLOBALS['container']->get('db.manager');
    $this->folderDao = $GLOBALS['container']->get('dao.folder');
  }

  /**
   * @brief Update upload properties (name, description)
   *
   * @param $uploadId upload.upload_pk of record to update
   * @param $newName New upload.upload_filename, and uploadtree.ufle_name
   *        If null, old value is not changed.
   * @param $newDesc New upload description (upload.upload_desc)
   *        If null, old value is not changed.
   *
   * @return int 1 if the upload record is updated, 0 if not, 2 if no inputs
   **/
  public function UpdateUploadProperties($uploadId, $newName, $newDesc)
  {
    if (is_null($newName) && is_null($newDesc)) {
      return 2;
    }

    if (!empty($newName)) {
      /*
       * Use pfile_fk to select the correct entry in the upload tree, artifacts
       * (e.g. directories of the upload do not have pfiles).
       */
      $row = $this->dbManager->getSingleRow(
        "SELECT pfile_fk FROM upload WHERE upload_pk=$1",array($uploadId),__METHOD__.'.getPfileId');
      if (empty($row)) {
        return 0;
      }
      $pfileFk = $row['pfile_fk'];
      $trimNewName = trim($newName);

      /* Always keep uploadtree.ufile_name and upload.upload_filename in sync */
      $this->dbManager->getSingleRow(
        "UPDATE uploadtree SET ufile_name=$3 WHERE upload_fk=$1 AND pfile_fk=$2",
        array($uploadId, $pfileFk, $trimNewName),
        __METHOD__ . '.updateItem');
      $this->dbManager->getSingleRow(
        "UPDATE upload SET upload_filename=$3 WHERE upload_pk=$1 AND pfile_fk=$2",
        array($uploadId, $pfileFk, $trimNewName),
        __METHOD__ . '.updateUpload.name');
    }

    if (!is_null($newDesc)) {
      $trimNewDesc = trim($newDesc);
      $this->dbManager->getSingleRow("UPDATE upload SET upload_desc=$2 WHERE upload_pk=$1",
        array($uploadId, $trimNewDesc), __METHOD__ . '.updateUpload.desc');
    }
    return 1;
  }

  public function Output()
  {
    $groupId = Auth::getGroupId();
    $rootFolder = $this->folderDao->getRootFolder(Auth::getUserId());
    $folderStructure = $this->folderDao->getFolderStructure($rootFolder->getId());

    $V = "";
    $folder_pk = GetParm('folder', PARM_INTEGER);
    if (empty($folder_pk)) {
      $folder_pk = $rootFolder->getId();
    }

    $NewName = GetParm("newname", PARM_TEXT);
    $NewDesc = GetParm("newdesc", PARM_TEXT);
    $upload_pk = GetParm("upload_pk", PARM_INTEGER);
    if (empty($upload_pk)) {
      $upload_pk = GetParm('upload', PARM_INTEGER);
    }
    /* Check Upload permission */
    if (! empty($upload_pk) && !$this->uploadDao->isEditable($upload_pk, $groupId)) {
      $text = _("Permission Denied");
      return "<h2>$text</h2>";
    }
    $rc = $this->UpdateUploadProperties($upload_pk, $NewName, $NewDesc);
    if ($rc == 0) {
      $text = _("Nothing to Change");
      $this->vars['message'] = $text;
    } else if ($rc == 1) {
      $text = _("Upload Properties successfully changed");
      $this->vars['message'] = $text;
    }

    $this->vars['folderStructure'] = $folderStructure;
    $this->vars['folderId'] = $folder_pk;
    $this->vars['baseUri'] = $Uri = Traceback_uri() . "?mod=" . $this->Name . "&folder=";

    $folderUploads = $this->folderDao->getFolderUploads($folder_pk, $groupId);
    $uploadsById = array();
    /* @var $uploadProgress UploadProgress */
    foreach ($folderUploads as $uploadProgress) {
      if ($uploadProgress->getGroupId() != $groupId) {
        continue;
      }
      if (! $this->uploadDao->isEditable($uploadProgress->getId(), $groupId)) {
        continue;
      }
      $display = $uploadProgress->getFilename() . _(" from ") . Convert2BrowserTime(date("Y-m-d H:i:s",$uploadProgress->getTimestamp()));
      $uploadsById[$uploadProgress->getId()] = $display;
    }
    $this->vars['uploadList'] = $uploadsById;
    if (empty($upload_pk)) {
      reset($uploadsById);
      $upload_pk = key($uploadsById);
    }
    $this->vars['uploadId'] = $upload_pk;

    if ($upload_pk) {
      $upload = $this->uploadDao->getUpload($upload_pk);
      if (empty($upload)) {
        $this->vars['message'] = _("Missing upload.");
        return 0;
      }
    } else {
      $upload = null;
    }

    $baseFolderUri = $this->vars['baseUri']."$folder_pk&upload=";
    $this->vars['uploadAction'] = "onchange=\"js_url(this.value, '$baseFolderUri')\"";

    $this->vars['uploadFilename'] = $upload ? $upload->getFilename() : '';
    $this->vars['uploadDesc'] = $upload ? $upload->getDescription() : '';
    $this->vars['content'] = $V;

    return $this->render('admin_upload_edit.html.twig');
  }
}
$NewPlugin = new upload_properties;
