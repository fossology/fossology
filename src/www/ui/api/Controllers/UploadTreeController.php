<?php
/*
 SPDX-FileCopyrightText: © 2023 Samuel Dushimimana <dushsam100@gmail.com>

 SPDX-License-Identifier: GPL-2.0-only
*/

/**
 * @file
 * @brief Controller for uploadtree queries
 */

namespace Fossology\UI\Api\Controllers;

use Fossology\Lib\Dao\ClearingDao;
use Fossology\Lib\Dao\LicenseDao;
use Fossology\Lib\Data\Clearing\ClearingEventTypes;
use Fossology\UI\Api\Helper\ResponseHelper;
use Fossology\UI\Api\Models\Info;
use Fossology\UI\Api\Models\InfoType;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ServerRequestInterface;


/**
 * @class UploadTreeController
 * @brief Controller for UploadTree model
 */
class UploadTreeController extends RestController
{
  /**
   * @var ContainerInterface $container
   * Slim container
   */
  protected $container;

  /** @var ClearingDao */
  private $clearingDao;

  /**
   * @var LicenseDao $licenseDao
   * License Dao object
   */
  private $licenseDao;

  public function __construct($container)
  {
    parent::__construct($container);
    $this->container = $container;
    $this->clearingDao = $this->container->get('dao.clearing');
    $this->licenseDao = $this->container->get('dao.license');
  }

  /**
   * Add a new license to a particular upload-tree
   *
   * @param ServerRequestInterface $request
   * @param ResponseHelper $response
   * @param array $args
   * @return ResponseHelper
   */
  public function addLicenseDecision($request, $response, $args)
  {
    $body = $this->getParsedBody($request);
    $uploadTreeId = intval($args['itemId']);
    $shortName = $body['shortName'];
    $returnVal = null;
    $license = null;
    $uploadDao = $this->restHelper->getUploadDao();

    try {
      // check if the license and the uploadTreeId exist
      if (!$this->dbHelper->doesIdExist($uploadDao->getUploadtreeTableName($uploadTreeId), "uploadtree_pk", $uploadTreeId)) {
        $returnVal = new Info(404, "Item does not exist", InfoType::ERROR);
      } else if (empty($shortName)) {
        $returnVal = new Info(400, "Short name missing from request.",
          InfoType::ERROR);
      } else {
        $license = $this->licenseDao->getLicenseByShortName($shortName,
          $this->restHelper->getGroupId());

        if ($license === null) {
          $returnVal = new Info(404, "License file with short name '$shortName' not found.",
            InfoType::ERROR);
        }
      }

      if ($returnVal !== null) {
        return $response->withJson($returnVal->getArray(), $returnVal->getCode());
      }

      $this->clearingDao->insertClearingEvent($uploadTreeId, $this->restHelper->getUserId(), $this->restHelper->getGroupId(), $license->getId(), false);
      $returnVal = new Info(200, "Successfully added license decision.", InfoType::INFO);
      return $response->withJson($returnVal->getArray(), 200);

    } catch (\Exception $e) {
      $returnVal = new Info(500, $e->getMessage(), InfoType::ERROR);
      return $response->withJson($returnVal->getArray(), $returnVal->getCode());
    }
  }
}
