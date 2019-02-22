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
 * @brief Helper to handle file uploads
 */

namespace Fossology\UI\Api\Helper;

use Fossology\UI\Page\UploadFilePage;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Http\UploadedFile;

/**
 * @class UploadHelper
 * @brief Handle new file uploads from Slim framework and move to FOSSology
 */
class UploadHelper extends UploadFilePage
{

  /**
   * Get a request from Slim and translate to Symfony request to be
   * processed by FOSSology
   *
   * @param ServerRequestInterface $request
   * @param string $folderName
   * @param string $fileDescription
   * @param string $isPublic
   * @return boolean[]|string[]|unknown[]|NULL[]|mixed[]
   */
  public function createNewUpload(ServerRequestInterface $request, $folderName,
    $fileDescription, $isPublic)
  {
    $uploadedFile = $request->getUploadedFiles();
    if (! isset($uploadedFile[self::FILE_INPUT_NAME])) {
      return array(
        false,
        "Missing file",
        "File " . self::FILE_INPUT_NAME . " missing from request",
        -1
      );
    } else {
      $uploadedFile = $uploadedFile[self::FILE_INPUT_NAME];
    }
    $path = $uploadedFile->file;
    $originalName = $uploadedFile->getClientFilename();
    $originalMime = $uploadedFile->getClientMediaType();
    $originalError = $uploadedFile->getError();
    $symfonyFile = new \Symfony\Component\HttpFoundation\File\UploadedFile($path,
      $originalName, $originalMime, $originalError);
    $symfonyRequest = new \Symfony\Component\HttpFoundation\Request();
    $symfonySession = $GLOBALS['container']->get('session');
    $symfonySession->set(self::UPLOAD_FORM_BUILD_PARAMETER_NAME, "restUpload");

    $symfonyRequest->request->set(self::FOLDER_PARAMETER_NAME, $folderName);
    $symfonyRequest->request->set(self::DESCRIPTION_INPUT_NAME, $fileDescription);
    $symfonyRequest->files->set(self::FILE_INPUT_NAME, $symfonyFile);
    $symfonyRequest->setSession($symfonySession);
    $symfonyRequest->request->set(self::UPLOAD_FORM_BUILD_PARAMETER_NAME,
      "restUpload");
    $symfonyRequest->request->set('public', $isPublic);

    return $this->handleUpload($symfonyRequest);
  }
}
