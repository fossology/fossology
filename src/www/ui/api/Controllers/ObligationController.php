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

use Fossology\Lib\Auth\Auth;
use Fossology\Lib\Dao\UploadDao;
use Fossology\Lib\Db\DbManager;
use Fossology\UI\Api\Helper\ResponseHelper;
use Fossology\UI\Api\Models\Info;
use Fossology\UI\Api\Models\InfoType;
use Psr\Http\Message\ServerRequestInterface;
use Fossology\Lib\Data\Tree\ItemTreeBounds;


class ObligationController extends RestController
{
  /**
   * @var ContainerInterface $container
   * Slim container
   */
  protected $container;

  /**
   * @var obligationFile $obligationFile
   * Obligation File object
   */
  private $obligationFile;

  public function __construct($container)
  {
    parent::__construct($container);
    $this->obligationFile = $this->restHelper->getPlugin('admin_obligation');
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
    $listVal = $this->obligationFile->getObligationsList();
    foreach ($listVal as $val) {
      $row['id'] = intval($val['ob_pk']);
      $row['obligation_topic'] = $val['ob_topic'];
      $finVal[] = $row;
    }
    return $response->withJson($finVal, 200);
  }
}
