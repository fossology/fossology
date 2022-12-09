<?php
/*
 SPDX-FileCopyrightText: © 2008-2013 Hewlett-Packard Development Company, L.P.
 SPDX-FileCopyrightText: © 2014-2017 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\UI\Page;

use Fossology\UI\Page\UploadPageBase;
use Fossology\Lib\Auth\Auth;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * \brief Upload a file from the users computer using the UI.
 */
class UploadFilePage extends UploadPageBase
{
  const FILE_INPUT_NAME = 'fileInput';


  public function __construct()
  {
    parent::__construct(self::NAME, array(
        self::TITLE => _("Upload a New File"),
        self::MENU_LIST => "Upload::From File",
        self::DEPENDENCIES => array("agent_unpack", "showjobs"),
        self::PERMISSION => Auth::PERM_WRITE
    ));
  }


  /**
   * @param Request $request
   * @return Response
   */
  protected function handleView(Request $request, $vars)
  {
    $vars['fileInputName'] = self::FILE_INPUT_NAME;
    return $this->render("upload_file.html.twig", $this->mergeWithDefault($vars));
  }

  /**
   * @brief Process the upload request.
   */
  protected function handleUpload(Request $request)
  {
    global $MODDIR;
    global $SYSCONFDIR;

    define("UPLOAD_ERR_EMPTY", 5);
    define("UPLOAD_ERR_INVALID_FOLDER_PK", 100);
    define("UPLOAD_ERR_RESEND", 200);
    $uploadErrors = array(
        UPLOAD_ERR_OK => _("No errors."),
        UPLOAD_ERR_INI_SIZE => _("Larger than upload_max_filesize ") . ini_get('upload_max_filesize'),
        UPLOAD_ERR_FORM_SIZE => _("Larger than form MAX_FILE_SIZE."),
        UPLOAD_ERR_PARTIAL => _("Partial upload."),
        UPLOAD_ERR_NO_FILE => _("No file selected."),
        UPLOAD_ERR_NO_TMP_DIR => _("No temporary directory."),
        UPLOAD_ERR_CANT_WRITE => _("Can't write to disk."),
        UPLOAD_ERR_EXTENSION => _("File upload stopped by extension."),
        UPLOAD_ERR_EMPTY => _("File is empty or you don't have permission to read the file."),
        UPLOAD_ERR_INVALID_FOLDER_PK => _("Invalid Folder."),
        UPLOAD_ERR_RESEND => _("This seems to be a resent file.")
    );

    $folderId = intval($request->get(self::FOLDER_PARAMETER_NAME));
    $descriptions = $request->get(self::DESCRIPTION_INPUT_NAME);
    for ($i = 0; $i < count($descriptions); $i++) {
      $descriptions[$i] = stripslashes($descriptions[$i]);
      $descriptions[$i] = $this->basicShEscaping($descriptions[$i]);
    }
    $uploadedFiles = $request->files->get(self::FILE_INPUT_NAME);
    $uploadFiles = [];
    for ($i = 0; $i < count($uploadedFiles); $i++) {
      $uploadFiles[] = [
        'file' => $uploadedFiles[$i],
        'description' => $descriptions[$i]
      ];
    }

    if (empty($uploadedFiles)) {
      return array(false, $uploadErrors[UPLOAD_ERR_NO_FILE], "");
    }

    if (
      $request->getSession()->get(self::UPLOAD_FORM_BUILD_PARAMETER_NAME)
      != $request->get(self::UPLOAD_FORM_BUILD_PARAMETER_NAME)
    ) {
      return array(false, $uploadErrors[UPLOAD_ERR_RESEND], "");
    }

    foreach ($uploadFiles as $uploadedFile) {
      if (
        $uploadedFile['file']->getSize() == 0 &&
        $uploadedFile['file']->getError() == 0
      ) {
        return array(false, $uploadErrors[UPLOAD_ERR_EMPTY], "");
      } else if ($uploadedFile['file']->getSize() >= UploadedFile::getMaxFilesize()) {
        return array(false, $uploadErrors[UPLOAD_ERR_INI_SIZE] .
          _(" is  really ") . $uploadedFile['file']->getSize() . " bytes.", "");
      }
      if (!$uploadedFile['file']->isValid()) {
        return array(false, $uploadedFile['file']->getErrorMessage(), "");
      }
    }

    if (empty($folderId)) {
      return array(false, $uploadErrors[UPLOAD_ERR_INVALID_FOLDER_PK], "");
    }

    $setGlobal = ($request->get('globalDecisions')) ? 1 : 0;

    $public = $request->get('public');
    $publicPermission = ($public == self::PUBLIC_ALL) ? Auth::PERM_READ : Auth::PERM_NONE;

    $uploadMode = (1 << 3); // code for "it came from web upload"
    $userId = Auth::getUserId();
    $groupId = Auth::getGroupId();
    $projectGroup = $GLOBALS['SysConf']['DIRECTORIES']['PROJECTGROUP'] ?: 'fossy';

    $errors = [];
    $success = [];
    foreach ($uploadFiles as $uploadedFile) {
      $originalFileName = $uploadedFile['file']->getClientOriginalName();
      $originalFileName = $this->basicShEscaping($originalFileName);
      /* Create an upload record. */
      $uploadId = JobAddUpload($userId, $groupId, $originalFileName,
        $originalFileName, $uploadedFile['description'], $uploadMode,
        $folderId, $publicPermission, $setGlobal);
      if (empty($uploadId)) {
        $errors[] = _("Failed to insert upload record: ") .
          $originalFileName;
        continue;
      }

      try {
        $uploadedTempFile = $uploadedFile['file']->move(
          $uploadedFile['file']->getPath(),
          $uploadedFile['file']->getFilename() . '-uploaded'
        )->getPathname();
      } catch (FileException $e) {
        $errors[] = _("Could not save uploaded file: ") . $originalFileName;
        continue;
      }
      $success[] = [
        "tempfile" => $uploadedTempFile,
        "orignalfile" => $originalFileName,
        "uploadid" => $uploadId
      ];
    }

    if (!empty($errors)) {
      return [false, implode(" ; ", $errors), ""];
    }

    $messages = [];
    foreach ($success as $row) {
      $uploadedTempFile = $row["tempfile"];
      $originalFileName = $row["orignalfile"];
      $uploadId = $row["uploadid"];

      $wgetAgentCall = "$MODDIR/wget_agent/agent/wget_agent -C -g " .
        "$projectGroup -k $uploadId '$uploadedTempFile' -c '$SYSCONFDIR'";
      $wgetOutput = array();
      exec($wgetAgentCall, $wgetOutput, $wgetReturnValue);
      unlink($uploadedTempFile);

      if ($wgetReturnValue != 0) {
        $message = implode(' ', $wgetOutput);
        if (empty($message)) {
          $message = _("File upload failed. Error:") . $wgetReturnValue;
        }
        $errors[] = $message;
      } else {
        $messages[] = $this->postUploadAddJobs($request, $originalFileName,
          $uploadId);
      }
    }

    if (!empty($errors)) {
      return [false, implode(" ; ", $errors), ""];
    }

    return array(true, implode("", $messages), "",
      array_column($success, "uploadid"));
  }
}

register_plugin(new UploadFilePage());
