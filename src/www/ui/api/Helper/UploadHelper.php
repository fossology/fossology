<?php
/**
 * *************************************************************
 * Copyright (C) 2018 Siemens AG
 * Author: Gaurav Mishra <mishra.gaurav@siemens.com>
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * version 2 as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 * *************************************************************
 */

/**
 * @file
 * @brief Helper to handle package uploads
 */
namespace Fossology\UI\Api\Helper;

use Psr\Http\Message\ServerRequestInterface;
use Fossology\UI\Api\Helper\UploadHelper\HelperToUploadFilePage;
use Fossology\UI\Api\Helper\UploadHelper\HelperToUploadVcsPage;

/**
 * @class UploadHelper
 * @brief Handle new file uploads from Slim framework and move to FOSSology
 */
class UploadHelper
{
  /**
   * @var HelperToUploadFilePage $uploadFilePage
   * Object to handle file based uploads
   */
  private $uploadFilePage;

  /**
   * @var HelperToUploadVcsPage $uploadVcsPage
   * Object to handle VCS based uploads
   */
  private $uploadVcsPage;

  /**
   * @var array VALID_VCS_TYPES
   * Array of valid inputs for vcsType parameter
   */
  const VALID_VCS_TYPES = array(
    "git",
    "svn"
  );

  /**
   * Constructor to get UploadFilePage and UploadVcsPage objects.
   */
  public function __construct()
  {
    $this->uploadFilePage = new HelperToUploadFilePage();
    $this->uploadVcsPage = new HelperToUploadVcsPage();
  }

  /**
   * Get a request from Slim and translate to Symfony request to be
   * processed by FOSSology
   *
   * @param ServerRequestInterface $request
   * @param string $folderName Name of the folder to upload the file
   * @param string $fileDescription Description of file uploaded
   * @param string $isPublic   Upload is `public, private or protected`
   * @param boolean $ignoreScm True if the SCM should be ignored.
   * @return array Array with status, message and upload id
   * @see createVcsUpload()
   * @see createFileUpload()
   */
  public function createNewUpload(ServerRequestInterface $request, $folderName,
    $fileDescription, $isPublic, $ignoreScm)
  {
    $uploadedFile = $request->getUploadedFiles();
    $vcsData = $request->getParsedBody();

    if (! empty($ignoreScm) && ($ignoreScm == "true" || $ignoreScm)) {
      // If SCM should be ignored
      $ignoreScm = 1;
    } else {
      $ignoreScm = 0;
    }

    if (empty($uploadedFile) ||
      ! isset($uploadedFile[$this->uploadFilePage::FILE_INPUT_NAME])) {
      if (empty($vcsData)) {
        return array(false, "Missing input",
          "Send file with parameter " . $this->uploadFilePage::FILE_INPUT_NAME .
          " or JSON with VCS parameters.",
          - 1
        );
      }
      return $this->createVcsUpload($vcsData, $folderName, $fileDescription,
        $isPublic, $ignoreScm);
    } else {
      $uploadedFile = $uploadedFile[$this->uploadFilePage::FILE_INPUT_NAME];
      return $this->createFileUpload($uploadedFile, $folderName,
        $fileDescription, $isPublic, $ignoreScm);
    }
  }

  /**
   * Create request required by UploadFilePage
   *
   * @param array $uploadedFile Uploaded file object by Slim
   * @param string $folderName  Name of the folder to upload the file
   * @param string $fileDescription Description of file uploaded
   * @param string $isPublic    Upload is `public, private or protected`
   * @param boolean $ignoreScm  True if the SCM should be ignored.
   * @return array Array with status, message and upload id
   */
  private function createFileUpload($uploadedFile, $folderName, $fileDescription,
    $isPublic, $ignoreScm = 0)
  {
    $path = $uploadedFile->file;
    $originalName = $uploadedFile->getClientFilename();
    $originalMime = $uploadedFile->getClientMediaType();
    $originalError = $uploadedFile->getError();
    $symfonyFile = new \Symfony\Component\HttpFoundation\File\UploadedFile(
      $path, $originalName, $originalMime, $originalError);
    $symfonyRequest = new \Symfony\Component\HttpFoundation\Request();
    $symfonySession = $GLOBALS['container']->get('session');
    $symfonySession->set(
      $this->uploadFilePage::UPLOAD_FORM_BUILD_PARAMETER_NAME, "restUpload");

    $symfonyRequest->request->set($this->uploadFilePage::FOLDER_PARAMETER_NAME,
      $folderName);
    $symfonyRequest->request->set($this->uploadFilePage::DESCRIPTION_INPUT_NAME,
      $fileDescription);
    $symfonyRequest->files->set($this->uploadFilePage::FILE_INPUT_NAME,
      $symfonyFile);
    $symfonyRequest->setSession($symfonySession);
    $symfonyRequest->request->set(
      $this->uploadFilePage::UPLOAD_FORM_BUILD_PARAMETER_NAME, "restUpload");
    $symfonyRequest->request->set('public', $isPublic);
    $symfonyRequest->request->set('scm', $ignoreScm);

    return $this->uploadFilePage->handleRequest($symfonyRequest);
  }

  /**
   * Create request required by UploadVcsPage
   *
   * @param array $vcsData     Parsed VCS object from request
   * @param string $folderName Name of the folder to upload the file
   * @param string $fileDescription Description of file uploaded
   * @param string $isPublic   Upload is `public, private or protected`
   * @param boolean $ignoreScm True if the SCM should be ignored.
   * @return array Array with status, message and upload id
   */
  private function createVcsUpload($vcsData, $folderName, $fileDescription,
    $isPublic, $ignoreScm = 0)
  {
    $sanity = $this->sanitizeVcsData($vcsData);
    if ($sanity !== true) {
      return $sanity;
    }
    $vcsType = $vcsData["vcsType"];
    $vcsUrl = $vcsData["vcsUrl"];
    $vcsName = $vcsData["vcsName"];
    $vcsUsername = $vcsData["vcsUsername"];
    $vcsPasswd = $vcsData["vcsPassword"];

    $symfonySession = $GLOBALS['container']->get('session');
    $symfonySession->set($this->uploadVcsPage::UPLOAD_FORM_BUILD_PARAMETER_NAME,
      "restUpload");

    $symfonyRequest = new \Symfony\Component\HttpFoundation\Request();
    $symfonyRequest->setSession($symfonySession);

    $symfonyRequest->request->set($this->uploadVcsPage::FOLDER_PARAMETER_NAME,
      $folderName);
    $symfonyRequest->request->set($this->uploadVcsPage::DESCRIPTION_INPUT_NAME,
      $fileDescription);
    $symfonyRequest->request->set($this->uploadVcsPage::GETURL_PARAM, $vcsUrl);
    $symfonyRequest->request->set(
      $this->uploadVcsPage::UPLOAD_FORM_BUILD_PARAMETER_NAME, "restUpload");
    $symfonyRequest->request->set('public', $isPublic);
    $symfonyRequest->request->set('name', $vcsName);
    $symfonyRequest->request->set('vcstype', $vcsType);
    $symfonyRequest->request->set('username', $vcsUsername);
    $symfonyRequest->request->set('passwd', $vcsPasswd);
    $symfonyRequest->request->set('scm', $ignoreScm);

    return $this->uploadVcsPage->handleRequest($symfonyRequest);
  }

  /**
   * @brief Check if the passed VCS object is correct or not.
   *
   * 1. Check if all the required parameters are passed by user.
   * 2. Translate the `vcsType` to required values.
   * 3. Add missing keys with empty data to prevent warnings.
   *
   * @param array $vcsData Parsed VCS object to be sanitized
   * @return array|boolean True if everything is correct, error array otherwise
   */
  private function sanitizeVcsData(&$vcsData)
  {
    $message = "";
    $statusDescription = "";
    $code = 0;

    if (! array_key_exists("vcsType", $vcsData) ||
      ! in_array($vcsData["vcsType"], self::VALID_VCS_TYPES)) {
      $message = "Missing vcsType";
      $statusDescription = "vcsType should be any of (" .
        implode(", ", self::VALID_VCS_TYPES) . ")";
      $code = 400;
    }
    $vcsType = "";
    if ($vcsData["vcsType"] == "git") {
      $vcsType = "Git";
    } else {
      $vcsType = "SVN";
    }

    if (! array_key_exists("vcsUrl", $vcsData)) {
      $message = "Missing vcsUrl";
      $statusDescription = "vcsUrl should be passed.";
      $code = 400;
    }

    if (! array_key_exists("vcsName", $vcsData)) {
      $vcsData["vcsName"] = "";
    }
    if (! array_key_exists("vcsUsername", $vcsData)) {
      $vcsData["vcsUsername"] = "";
    }
    if (! array_key_exists("vcsPassword", $vcsData)) {
      $vcsData["vcsPassword"] = "";
    }
    $vcsData["vcsType"] = $vcsType;
    if ($code !== 0) {
      return array(false, $message, $statusDescription, $code);
    } else {
      return true;
    }
  }
}
