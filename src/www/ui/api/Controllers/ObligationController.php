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
}
