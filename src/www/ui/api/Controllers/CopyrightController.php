<?php
/*
 SPDX-FileCopyrightText: © 2018 Siemens AG
 Author: Gaurav Mishra <mishra.gaurav@siemens.com>
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


class CopyrightController extends RestController
{
  /**
   * @var ContainerInterface $container
   * Slim container
   */
  protected $container;

  /**
   * @var ClearingDao
   */
  private $clearingDao;

  /**
   * @var LicenseDao $licenseDao
   * License Dao object
   */
  private $licenseDao;

  /**
   * @var LicenseDao $licenseDao
   * Upload Dao object
   */
  private $uploadDao;

  /**
   * @var CopyrightHist $licenseDao
   * Copyright Histogram object
   */
  private $CopyrightHist;

  public function __construct($container)
  {
    parent::__construct($container);
    $this->container = $container;
    $this->clearingDao = $this->container->get('dao.clearing');
    $this->licenseDao = $this->container->get('dao.license');
    $this->uploadDao = $container->get('dao.upload');
    $this->restHelper = $container->get('helper.restHelper');
    $this->CopyrightHist = $this->restHelper->getPlugin('ajax-copyright-hist');
  }

  /**
   * Get all copyrights for a particular upload-tree
   *
   * @param  ServerRequestInterface $request
   * @param  ResponseHelper         $response
   * @param  array                  $args
   * @return ResponseHelper
   */
  public function getFileCopyrights($request, $response, $args)
  {
    $uploadPk = $args["id"];
    $uploadTreeId = $args["itemId"];
    $agentId = $this->CopyrightHist->getAgentId($uploadPk, 'copyright_ars');
    $UploadTreeTableName = $this->uploadDao->getUploadtreeTableName($uploadPk);
    $returnVal = null;
    try {
      if (!$this->dbHelper->doesIdExist("upload", "upload_pk", $uploadPk)) {
        $returnVal = new Info(404, "Upload does not exist", InfoType::ERROR);
      } else if (!$this->dbHelper->doesIdExist($this->uploadDao->getUploadtreeTableName($uploadPk), "uploadtree_pk", $uploadTreeId)) {
        $returnVal = new Info(404, "Item does not exist", InfoType::ERROR);
      }
      if ($returnVal !== null) {
        return $response->withJson($returnVal->getArray(), $returnVal->getCode());
      }
      $returnVal = $this->CopyrightHist->getCopyrights($uploadPk, $uploadTreeId, $UploadTreeTableName, $agentId, 'statement', 'active', true);
      return $response->withJson($returnVal, 200);
    } catch (\Exception $e) {
      $returnVal = new Info(500, $e->getMessage(), InfoType::ERROR);
      return $response->withJson($returnVal->getArray(), $returnVal->getCode());
    }
  }
}
