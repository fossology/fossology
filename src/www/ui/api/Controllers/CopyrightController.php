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
use PhpOffice\PhpSpreadsheet\Calculation\LookupRef\Offset;
use Psr\Http\Message\ServerRequestInterface;


class CopyrightController extends RestController
{
  /**
   * @var ContainerInterface $container
   * Slim container
   */
  protected $container;

  /**
   * Get query parameter name for copyright filtering
   */
  const COPYRIGHT_PARAM = "status";

  /**
   * Get header parameter name for limiting listing
   */
  const LIMIT_PARAM = "limit";

  /**
   * Get header parameter name for page listing
   */
  const PAGE_PARAM = "page";

  /**
   * Limit of copyrights in get query
   */
  const COPYRIGHT_FETCH_LIMIT = 100;


  /**
   * @var copyrightHist $copyrightHist
   * Copyright Histogram object
   */
  private $copyrightHist;

  public function __construct($container)
  {
    parent::__construct($container);
    $this->copyrightHist = $this->restHelper->getPlugin('ajax-copyright-hist');
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
    $query = $request->getQueryParams();
    $limit = $request->getHeaderLine(self::LIMIT_PARAM);
    $statusVal = true;
    $returnVal = null;
    $finalVal = [];
    if (!empty($limit)) {
      $limit = filter_var($limit, FILTER_VALIDATE_INT);
      if ($limit < 1) {
        $info = new Info(
          400,
          "limit should be positive integer > 1",
          InfoType::ERROR
        );
        $limit = self::COPYRIGHT_FETCH_LIMIT;
      }
    } else {
      $limit = self::COPYRIGHT_FETCH_LIMIT;
    }
    if (!array_key_exists(self::COPYRIGHT_PARAM, $query)) {
      $returnVal = new Info(400, "Bad Request. 'status' is a required query param with expected values 'active' or 'inactive", InfoType::ERROR);
      return $response->withJson($returnVal->getArray(), $returnVal->getCode());
    }
    $status = $query[self::COPYRIGHT_PARAM];
    if ($status == "active") {
      $statusVal = true;
    } else if ($status == "inactive") {
      $statusVal = false;
    } else {
      $returnVal = new Info(400, "Bad Request. Invalid query parameter, expected values 'active' or 'inactive", InfoType::ERROR);
      return $response->withJson($returnVal->getArray(), $returnVal->getCode());
    }
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
    $page = $request->getHeaderLine(self::PAGE_PARAM);
    if (empty($page) && $page != "0") {
      $page = 1;
    }
    if (!empty($page) || $page == "0") {
      $page = filter_var($page, FILTER_VALIDATE_INT);
      if ($page <= 0) {
        $info = new Info(
          400,
          "page should be positive integer > 0",
          InfoType::ERROR
        );
      }
      $offset = $limit * ($page - 1);
      if ($info !== null) {
        $retVal = $response->withJson($info->getArray(), $info->getCode());
        return $retVal;
      }
      list($rows, $iTotalDisplayRecords, $iTotalRecords)  = $this->copyrightHist->getCopyrights($uploadPk, $uploadTreeId, $uploadTreeTableName, $agentId, 'statement', 'active', $statusVal, $offset, $limit);
      foreach ($rows as $row) {
        $row['copyright_count'] = intval($row['copyright_count']);
        $finalVal[] = $row;
      }
      $totalPages = intval(ceil($iTotalRecords / $limit));
      if ($page > $totalPages) {
        $info = new Info(
          400,
          "Can not exceed total pages: $totalPages",
          InfoType::ERROR
        );
        $errorHeader = ["X-Total-Pages", $totalPages];
      }
    }
    if ($info !== null) {
      $retVal = $response->withJson($info->getArray(), $info->getCode());
      if ($errorHeader) {
        $retVal = $retVal->withHeader($errorHeader[0], $errorHeader[1]);
      }
      return $retVal;
    }
    return $response->withHeader("X-Total-Pages", $totalPages)->withJson($finalVal, 200);
  }
}
