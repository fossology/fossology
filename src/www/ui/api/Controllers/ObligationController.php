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

use Fossology\Lib\BusinessRules\ObligationMap;
use Fossology\UI\Api\Helper\ResponseHelper;
use Fossology\UI\Api\Models\Info;
use Fossology\UI\Api\Models\InfoType;
use Fossology\UI\Api\Models\Obligation;
use Psr\Http\Message\ServerRequestInterface;

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
   * @param  ServerRequestInterface $request
   * @param  ResponseHelper         $response
   * @param  array                  $args
   * @return ResponseHelper
   */

  function obligationsDetails($request, $response, $args)
  {
    $obligationId = intval($args['id']);
    $returnVal = null;
    if (!$this->dbHelper->doesIdExist("obligation_ref", "ob_pk", $obligationId)) {
      $returnVal = new Info(404, "Obligation does not exist", InfoType::ERROR);
    }
    if ($returnVal !== null) {
      return $response->withJson($returnVal->getArray(), $returnVal->getCode());
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
   * @param  ServerRequestInterface $request
   * @param  ResponseHelper         $response
   * @param  array                  $args
   * @return ResponseHelper
   */
  function deleteObligation($request, $response, $args)
  {
    $obligationId = intval($args['id']);
    $returnVal = null;
    if (!$this->dbHelper->doesIdExist("obligation_ref", "ob_pk", $obligationId)) {
      $returnVal = new Info(404, "Obligation does not exist", InfoType::ERROR);
    }
    if ($returnVal !== null) {
      return $response->withJson($returnVal->getArray(), $returnVal->getCode());
    }
    $this->obligationMap->deleteObligation($obligationId);
    $returnVal = new Info(200, "Successfully removed Obligation.", InfoType::INFO);
    return $response->withJson($returnVal->getArray(), $returnVal->getCode());
  }
}
