<?php
/***********************************************************
 * Copyright (C) 2008-2013 Hewlett-Packard Development Company, L.P.
 * Copyright (C) 2014-2015 Siemens AG
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
    $description = stripslashes($request->get(self::DESCRIPTION_INPUT_NAME));
    $description = $this->basicShEscaping($description);
    $uploadedFile = $request->files->get(self::FILE_INPUT_NAME);

    if ($uploadedFile === null)
    {
      return array(false,$uploadErrors[UPLOAD_ERR_NO_FILE],$description);
    }
    
    if ($request->getSession()->get(self::UPLOAD_FORM_BUILD_PARAMETER_NAME)
        != $request->get(self::UPLOAD_FORM_BUILD_PARAMETER_NAME))
    {
      return array(false, $uploadErrors[UPLOAD_ERR_RESEND], $description);
    }

    if ($uploadedFile->getSize() == 0 && $uploadedFile->getError() == 0) {
      return array(false, $uploadErrors[UPLOAD_ERR_EMPTY], $description);
    } else if ($uploadedFile->getSize() >= UploadedFile::getMaxFilesize()) {
      return array(false, $uploadErrors[UPLOAD_ERR_INI_SIZE] . _(" is  really ") . $uploadedFile->getSize() . " bytes.", $description);
    }

    if (empty($folderId)) {
      return array(false, $uploadErrors[UPLOAD_ERR_INVALID_FOLDER_PK], $description);
    }

    if(!$uploadedFile->isValid()) {
      return array(false, $uploadedFile->getErrorMessage(), $description);
    }

    $originalFileName = $uploadedFile->getClientOriginalName();
    $originalFileName = $this->basicShEscaping($originalFileName);

    $public = $request->get('public');
    $publicPermission = ($public == self::PUBLIC_ALL) ? Auth::PERM_READ : Auth::PERM_NONE;

    /* Create an upload record. */
    $uploadMode = (1 << 3); // code for "it came from web upload"
    $userId = Auth::getUserId();
    $groupId = Auth::getGroupId();
    $uploadId = JobAddUpload($userId, $groupId, $originalFileName, $originalFileName, $description, $uploadMode, $folderId, $publicPermission);

    if (empty($uploadId))
    {
      return array(false, _("Failed to insert upload record"), $description);
    }

    try
    {
      $uploadedTempFile = $uploadedFile->move($uploadedFile->getPath(), $uploadedFile->getFilename() . '-uploaded')->getPathname();
    } catch (FileException $e)
    {
      return array(false, _("Could not save uploaded file"), $description);
    }

    $projectGroup = $GLOBALS['SysConf']['DIRECTORIES']['PROJECTGROUP'] ?: 'fossy';
    $wgetAgentCall = "$MODDIR/wget_agent/agent/wget_agent -C -g $projectGroup -k $uploadId '$uploadedTempFile' -c '$SYSCONFDIR'";
    $wgetOutput = array();
    exec($wgetAgentCall, $wgetOutput, $wgetReturnValue);
    unlink($uploadedTempFile);

    if ($wgetReturnValue != 0)
    {
      $message = implode(' ', $wgetOutput);
      if (empty($message))
      {
        $message = _("File upload failed.  Error:") . $wgetReturnValue;
      }
      return array(false, $message, $description);
    }
    
    $message = $this->postUploadAddJobs($request, $originalFileName, $uploadId);
        
    return array(true, $message, $description);
  }

}

register_plugin(new UploadFilePage());
