<?php
/*
 SPDX-FileCopyrightText: © 2024 Divij Sharma <divijs75@gmail.com>

 SPDX-License-Identifier: GPL-2.0-only
*/

/**
 * @file
 * @brief Controller for OneShot Analysis
 */

namespace Fossology\UI\Api\Controllers;

use Fossology\Lib\Data\Highlight;
use Fossology\Lib\Dao\LicenseDao;
use Fossology\Lib\View\HighlightProcessor;
use Fossology\UI\Api\Models\OneShot;
use Fossology\UI\Api\Helper\ResponseHelper;
use Fossology\UI\Api\Exceptions\HttpBadRequestException;
use Fossology\UI\Api\Exceptions\HttpPayloadTooLargeException;
use Psr\Http\Message\ServerRequestInterface;

/**
 * @class OneShotController
 * @brief Controller for OneShot Analysis
 */
class OneShotController extends RestController
{
  const FILE_INPUT_NAME = 'fileInput';
  const ALT_FILE_INPUT_NAME = 'analysisFile';

  /** @var HighlightProcessor */
  private $highlightProcessor;

  /** @var LicenseDao */
  private $licenseDao;

  public function __construct($container)
  {
    parent::__construct($container);
    $this->highlightProcessor = $this->container->get('view.highlight_processor');
    $this->licenseDao = $this->container->get('dao.license');
  }

  /**
   * Run OneShot Analysis using Nomos
   *
   * @param ServerRequestInterface $request
   * @param ResponseHelper $response
   * @param array $args
   * @return ResponseHelper
   * @throws HttpBadRequestException
   */
  public function runOneShotNomos($request, $response, $args)
  {
    $uploadedFile = $this->getUploadedFileOrThrow();
    list($licenses, $highlightInfoKeywords, $highlightInfoLicenses) = $this->restHelper->getPlugin('agent_nomos_once')->
      AnalyzeFile($uploadedFile->getPathname(), true);

    $highlights = array();

    for ($index = 0; $index < count($highlightInfoKeywords['position']); $index ++) {
      $position = $highlightInfoKeywords['position'][$index];
      $length = $highlightInfoKeywords['length'][$index];

      $highlights[] = new Highlight($position, $position + $length,
        Highlight::KEYWORD);
    }
    for ($index = 0; $index < count($highlightInfoLicenses['position']); $index ++) {
      $position = $highlightInfoLicenses['position'][$index];
      $length = $highlightInfoLicenses['length'][$index];
      $name = $highlightInfoLicenses['name'][$index];

      $highlights[] = new Highlight($position, $position + $length,
        Highlight::SIGNATURE, $name);
    }
    $this->highlightProcessor->sortHighlights($highlights);
    $returnVal = new OneShot($licenses, $highlights);
    return $response->withJson($returnVal->getArray(), 200);
  }

  /**
   * Run OneShot Analysis using Monk
   *
   * @param ServerRequestInterface $request
   * @param ResponseHelper $response
   * @param array $args
   * @return ResponseHelper
   * @throws HttpBadRequestException
   */
  public function runOneShotMonk($request, $response, $args)
  {
    $uploadedFile = $this->getUploadedFileOrThrow();

    list($licenseIds, $highlights) = $this->restHelper->getPlugin('oneshot-monk')->
      scanMonk($uploadedFile->getPathname());
    $this->highlightProcessor->addReferenceTexts($highlights);
    $licenseArray = array_map(function($licenseIds) {
      return ($this->licenseDao->getLicenseById($licenseIds))
        ->getShortName();
    }, $licenseIds);
    $returnVal = new OneShot($licenseArray, $highlights);
    return $response->withJson($returnVal->getArray(), 200);
  }

  /**
   * Run OneShot Analysis for Copyright, Email, URL
   *
   * @param ServerRequestInterface $request
   * @param ResponseHelper $response
   * @param array $args
   * @return ResponseHelper
   * @throws HttpBadRequestException
   */
  public function runOneShotCEU($request, $response, $args)
  {
    $uploadedFile = $this->getUploadedFileOrThrow();
    list($copyrights, $highlights) = $this->restHelper->getPlugin('agent_copyright_once')->
      AnalyzeOne(true, $uploadedFile->getPathname());
    $this->highlightProcessor->sortHighlights($highlights);
    $returnVal = new OneShot($copyrights, $highlights);
    return $response->withJson($returnVal->getArray('copyrights'), 200);
  }

  /**
   * Get uploaded file from accepted form field names.
   *
   * @return \Symfony\Component\HttpFoundation\File\UploadedFile
   * @throws HttpBadRequestException
   * @throws HttpPayloadTooLargeException
   */
  private function getUploadedFileOrThrow()
  {
    $symReq = \Symfony\Component\HttpFoundation\Request::createFromGlobals();
    $uploadedFile = $symReq->files->get($this::FILE_INPUT_NAME, null);
    if (is_null($uploadedFile)) {
      $uploadedFile = $symReq->files->get($this::ALT_FILE_INPUT_NAME, null);
    }
    if (is_null($uploadedFile)) {
      throw new HttpBadRequestException("No file uploaded");
    }

    $uploadError = $uploadedFile->getError();
    if ($uploadError === UPLOAD_ERR_OK) {
      return $uploadedFile;
    }

    if ($uploadError === UPLOAD_ERR_INI_SIZE || $uploadError === UPLOAD_ERR_FORM_SIZE) {
      throw new HttpPayloadTooLargeException("Uploaded file is too large");
    }
    if ($uploadError === UPLOAD_ERR_PARTIAL) {
      throw new HttpBadRequestException("File was only partially uploaded");
    }
    if ($uploadError === UPLOAD_ERR_NO_FILE) {
      throw new HttpBadRequestException("No file uploaded");
    }
    if ($uploadError === UPLOAD_ERR_NO_TMP_DIR) {
      throw new HttpBadRequestException("Temporary upload directory is missing");
    }
    if ($uploadError === UPLOAD_ERR_CANT_WRITE) {
      throw new HttpBadRequestException("Failed to write uploaded file to disk");
    }
    if ($uploadError === UPLOAD_ERR_EXTENSION) {
      throw new HttpBadRequestException("File upload stopped by server extension");
    }

    throw new HttpBadRequestException("Error uploading file");
  }
}
