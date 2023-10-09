<?php
/*
 Author: Soham Banerjee <sohambanerjee4abc@hotmail.com>
 SPDX-FileCopyrightText: © 2023 Soham Banerjee <sohambanerjee4abc@hotmail.com>
 SPDX-License-Identifier: GPL-2.0-only
*/

/**
 * @file
 * @brief Controller for copyright queries
 */

namespace Fossology\UI\Api\Controllers;

use Fossology\Lib\Application\ObligationCsvExport;
use Fossology\Lib\BusinessRules\ObligationMap;
use Fossology\UI\Api\Exceptions\HttpBadRequestException;
use Fossology\UI\Api\Exceptions\HttpErrorException;
use Fossology\UI\Api\Exceptions\HttpNotFoundException;
use Fossology\UI\Api\Helper\ResponseHelper;
use Fossology\UI\Api\Models\Info;
use Fossology\UI\Api\Models\InfoType;
use Fossology\UI\Api\Models\Obligation;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Psr7\Factory\StreamFactory;

class ObligationController extends RestController
{
  /**
   * @var ObligationMap $obligationMap
   * Obligation Map object
   */
  private $obligationMap;

  public function __construct($container)
  {
    parent::__construct($container);
    $this->obligationMap = $this->container->get('businessrules.obligationmap');
  }

  /**
   * Get all list of obligations
   *
   * @param  ServerRequestInterface $request
   * @param  ResponseHelper         $response
   * @param  array                  $args
   * @return ResponseHelper
   */
  function obligationsList($request, $response, $args)
  {
    $finVal = [];
    $listVal = $this->obligationMap->getObligations();
    foreach ($listVal as $val) {
      $row['id'] = intval($val['ob_pk']);
      $row['obligation_topic'] = $val['ob_topic'];
      $finVal[] = $row;
    }
    return $response->withJson($finVal, 200);
  }

  /**
   * Get details of obligations based on id
   *
   * @param ServerRequestInterface $request
   * @param ResponseHelper $response
   * @param array $args
   * @return ResponseHelper
   * @throws HttpNotFoundException
   */
  function obligationsDetails($request, $response, $args)
  {
    $obligationId = intval($args['id']);
    if (!$this->dbHelper->doesIdExist("obligation_ref", "ob_pk", $obligationId)) {
      throw new HttpNotFoundException("Obligation does not exist");
    }

    $obligation = $this->createExtendedObligationFromId($obligationId);
    return $response->withJson($obligation->getArray(), 200);
  }

  /**
   * Get details of all obligations
   *
   * @param  ServerRequestInterface $request
   * @param  ResponseHelper         $response
   * @param  array                  $args
   * @return ResponseHelper
   */
  function obligationsAllDetails($request, $response, $args)
  {
    $obligationArray = [];
    $listVal = $this->obligationMap->getObligations();
    foreach ($listVal as $val) {
      $obligationId = intval($val['ob_pk']);
      $obligationArray[] = $this->createExtendedObligationFromId($obligationId)
        ->getArray();
    }
    return $response->withJson($obligationArray, 200);
  }

  /**
   * Create extended Obligation Model object for a given obligation ID.
   *
   * @param int $obligationId Obligation ID to get object for
   * @return Obligation Obligation model object for given id
   */
  private function createExtendedObligationFromId($obligationId)
  {
    $obligationInfo = $this->obligationMap->getObligationById($obligationId);
    $licenses = $this->obligationMap->getLicenseList($obligationId);
    $candidateLicenses = $this->obligationMap->getLicenseList($obligationId,
      true);
    $associatedLicenses = explode(";", $licenses);
    $associatedCandidateLicenses = explode(";", $candidateLicenses);

    return Obligation::fromArray($obligationInfo, true,
      $associatedLicenses, $associatedCandidateLicenses);
  }

  /**
   * Delete obligation based on id
   *
   * @param ServerRequestInterface $request
   * @param ResponseHelper $response
   * @param array $args
   * @return ResponseHelper
   * @throws HttpNotFoundException
   */
  function deleteObligation($request, $response, $args)
  {
    $obligationId = intval($args['id']);
    if (!$this->dbHelper->doesIdExist("obligation_ref", "ob_pk", $obligationId)) {
      throw new HttpNotFoundException("Obligation does not exist");
    }
    $this->obligationMap->deleteObligation($obligationId);
    $returnVal = new Info(200, "Successfully removed Obligation.", InfoType::INFO);
    return $response->withJson($returnVal->getArray(), $returnVal->getCode());
  }

  /**
   * Import Admin License Obligations from CSV
   *
   * @param ServerRequestInterface $request
   * @param ResponseHelper $response
   * @param array $args
   * @return ResponseHelper
   * @throws HttpErrorException
   */
  public function importObligationsFromCSV($request, $response, $args)
  {
    $this->throwNotAdminException();

    $symReq = \Symfony\Component\HttpFoundation\Request::createFromGlobals();
    /** @var \Fossology\UI\Page\AdminObligationFromCSV $adminLicenseObligationFromCsv */
    $adminLicenseObligationFromCsv = $this->restHelper->getPlugin('admin_obligation_from_csv');

    $uploadedFile = $symReq->files->get($adminLicenseObligationFromCsv->getFileInputName(),
      null);

    $requestBody = $this->getParsedBody($request);
    $delimiter = ',';
    $enclosure = '"';
    if (array_key_exists("delimiter", $requestBody) && !empty($requestBody["delimiter"])) {
      $delimiter = $requestBody["delimiter"];
    }
    if (array_key_exists("enclosure", $requestBody) && !empty($requestBody["enclosure"])) {
      $enclosure = $requestBody["enclosure"];
    }

    $res = $adminLicenseObligationFromCsv->handleFileUpload($uploadedFile, $delimiter, $enclosure, true);
    if (!$res[0]) {
      throw new HttpBadRequestException($res[1]);
    }

    $newInfo = new Info($res[2], $res[1], InfoType::INFO);
    return $response->withJson($newInfo->getArray(), $newInfo->getCode());
  }

  /**
   * Export Obligations to CSV
   *
   * @param ServerRequestInterface $request
   * @param ResponseHelper $response
   * @param array $args
   * @return ResponseHelper
   * @throws HttpErrorException
   */
  public function exportObligationsToCSV($request, $response, $args)
  {
    $this->throwNotAdminException();
    $query = $request->getQueryParams();
    $obPk = 0;
    if (array_key_exists('id', $query)) {
      $obPk = intval($query['id']);
    }
    if ($obPk != 0 &&
      ! $this->dbHelper->doesIdExist("obligation_ref", "ob_pk", $obPk)) {
      throw new HttpNotFoundException("Obligation does not exist");
    }

    $dbManager = $this->dbHelper->getDbManager();
    $obligationCsvExport = new ObligationCsvExport($dbManager);
    $content = $obligationCsvExport->createCsv($obPk);
    $fileName = "fossology-obligations-export-".date("YMj-Gis");
    $newResponse = $response->withHeader('Content-type', 'text/csv, charset=UTF-8')
      ->withHeader('Content-Disposition', 'attachment; filename=' . $fileName . '.csv')
      ->withHeader('Pragma', 'no-cache')
      ->withHeader('Cache-Control', 'no-cache, must-revalidate, maxage=1, post-check=0, pre-check=0')
      ->withHeader('Expires', 'Expires: Thu, 19 Nov 1981 08:52:00 GMT');
    $sf = new StreamFactory();
    return $newResponse->withBody(
      $content ? $sf->createStream($content) : $sf->createStream('')
    );
  }
}
