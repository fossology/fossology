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
use Psr\Http\Message\ServerRequestInterface;

/**
 * @class OneShotController
 * @brief Controller for OneShot Analysis
 */
class OneShotController extends RestController
{
  const FILE_INPUT_NAME = 'fileInput';

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
    $symReq = \Symfony\Component\HttpFoundation\Request::createFromGlobals();
    $uploadedFile = $symReq->files->get($this::FILE_INPUT_NAME, null);
    if (is_null($uploadedFile)) {
      throw new HttpBadRequestException("No file uploaded");
    }
    if ($uploadedFile->getError() !== UPLOAD_ERR_OK) {
      throw new HttpBadRequestException("Error uploading file");
    }
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
    $symReq = \Symfony\Component\HttpFoundation\Request::createFromGlobals();
    $uploadedFile = $symReq->files->get($this::FILE_INPUT_NAME, null);
    if (is_null($uploadedFile)) {
      throw new HttpBadRequestException("No file uploaded");
    }
    if ($uploadedFile->getError() !== UPLOAD_ERR_OK) {
      throw new HttpBadRequestException("Error uploading file");
    }

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
    $symReq = \Symfony\Component\HttpFoundation\Request::createFromGlobals();
    $uploadedFile = $symReq->files->get($this::FILE_INPUT_NAME, null);
    if (is_null($uploadedFile)) {
      throw new HttpBadRequestException("No file uploaded");
    }
    if ($uploadedFile->getError() !== UPLOAD_ERR_OK) {
      throw new HttpBadRequestException("Error uploading file");
    }
    list($copyrights, $highlights) = $this->restHelper->getPlugin('agent_copyright_once')->
      AnalyzeOne(true, $uploadedFile->getPathname());
    $this->highlightProcessor->sortHighlights($highlights);
    $returnVal = new OneShot($copyrights, $highlights);
    return $response->withJson($returnVal->getArray('copyrights'), 200);
  }
}
