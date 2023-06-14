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


class CopyrightController extends RestController
{
  /**
   * @var ContainerInterface $container
   * Slim container
   */
  protected $container;

  /**
   * @var copyrightHist $copyrightHist
   * Copyright Histogram object
   */
  private $copyrightHist;

  public function __construct($container)
  {
    parent::__construct($container);
    $this->restHelper = $container->get('helper.restHelper');
    $this->copyrightHist = $this->restHelper->getPlugin('ajax-copyright-hist');
  }

  /**
   * Get inactive copyrights for a particular upload-tree
   *
   * @param  ServerRequestInterface $request
   * @param  ResponseHelper         $response
   * @param  array                  $args
   * @return ResponseHelper
   */
  public function getInactiveFileCopyrights($request, $response, $args)
  {
    $uploadPk = $args["id"];
    $uploadTreeId = $args["itemId"];
    $returnVal = null;
    try {
      if (!$this->dbHelper->doesIdExist("upload", "upload_pk", $uploadPk)) {
        $returnVal = new Info(404, "Upload does not exist", InfoType::ERROR);
      } else if (!$this->dbHelper->doesIdExist($this->restHelper->getUploadDao()->getuploadTreeTableName($uploadPk), "uploadtree_pk", $uploadTreeId)) {
        $returnVal = new Info(404, "Item does not exist", InfoType::ERROR);
      }
      if ($returnVal !== null) {
        return $response->withJson($returnVal->getArray(), $returnVal->getCode());
      }
      $agentId = $this->copyrightHist->getAgentId($uploadPk, 'copyright_ars');
      $uploadTreeTableName = $this->restHelper->getUploadDao()->getuploadTreeTableName($uploadPk);
      $returnVal = $this->copyrightHist->getCopyrights($uploadPk, $uploadTreeId, $uploadTreeTableName, $agentId, 'statement', 'inactive', false);
      return $response->withJson($returnVal, 200);
    } catch (\Exception $e) {
      $returnVal = new Info(500, $e->getMessage(), InfoType::ERROR);
      return $response->withJson($returnVal->getArray(), $returnVal->getCode());
    }
  }
}
