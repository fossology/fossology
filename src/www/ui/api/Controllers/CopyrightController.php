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

use Fossology\Lib\Dao\CopyrightDao;
use Fossology\UI\Api\Exceptions\HttpBadRequestException;
use Fossology\UI\Api\Exceptions\HttpErrorException;
use Fossology\UI\Api\Helper\ResponseHelper;
use Fossology\UI\Api\Models\Info;
use Fossology\UI\Api\Models\InfoType;
use Psr\Http\Message\ServerRequestInterface;


class CopyrightController extends RestController
{
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
   * @var \CopyrightHistogram $copyrightHist
   * Copyright Histogram object
   */
  private $copyrightHist;

  /**
   * @var CopyrightDao $copyrightDao
   * Copyright Dao object
   */
  private $copyrightDao;
  const TYPE_COPYRIGHT = 1;
  const TYPE_EMAIL = 2;
  const TYPE_URL = 4;
  const TYPE_AUTHOR = 8;
  const TYPE_ECC = 16;
  const TYPE_KEYWORD = 32;
  const TYPE_IPRA = 64;

  public function __construct($container)
  {
    parent::__construct($container);
    $this->copyrightDao = $this->container->get('dao.copyright');
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
    return $this->getFileCX($request, $response, $args, self::TYPE_COPYRIGHT);
  }

  /**
   * Get all emails for a particular upload-tree
   *
   * @param  ServerRequestInterface $request
   * @param  ResponseHelper         $response
   * @param  array                  $args
   * @return ResponseHelper
   */
  public function getFileEmail($request, $response, $args)
  {
    return $this->getFileCX($request, $response, $args, self::TYPE_EMAIL);
  }

  /**
   * Get all urls for a particular upload-tree
   *
   * @param  ServerRequestInterface $request
   * @param  ResponseHelper         $response
   * @param  array                  $args
   * @return ResponseHelper
   */
  public function getFileUrl($request, $response, $args)
  {
    return $this->getFileCX($request, $response, $args, self::TYPE_URL);
  }

  /**
   * Get all authors for a particular upload-tree
   *
   * @param  ServerRequestInterface $request
   * @param  ResponseHelper         $response
   * @param  array                  $args
   * @return ResponseHelper
   */
  public function getFileAuthor($request, $response, $args)
  {
    return $this->getFileCX($request, $response, $args, self::TYPE_AUTHOR);
  }

  /**
   * Get all ecc for a particular upload-tree
   *
   * @param  ServerRequestInterface $request
   * @param  ResponseHelper         $response
   * @param  array                  $args
   * @return ResponseHelper
   */
  public function getFileEcc($request, $response, $args)
  {
    return $this->getFileCX($request, $response, $args, self::TYPE_ECC);
  }

  /**
   * Get all keywords for a particular upload-tree
   *
   * @param  ServerRequestInterface $request
   * @param  ResponseHelper         $response
   * @param  array                  $args
   * @return ResponseHelper
   */
  public function getFileKeyword($request, $response, $args)
  {
    return $this->getFileCX($request, $response, $args, self::TYPE_KEYWORD);
  }

  /**
   * Get all ipra for a particular upload-tree
   *
   * @param  ServerRequestInterface $request
   * @param  ResponseHelper         $response
   * @param  array                  $args
   * @return ResponseHelper
   */
  public function getFileIpra($request, $response, $args)
  {
    return $this->getFileCX($request, $response, $args, self::TYPE_IPRA);
  }

  /**
   * Delete copyright for a particular file
   *
   * @param  ServerRequestInterface $request
   * @param  ResponseHelper         $response
   * @param  array                  $args
   * @return ResponseHelper
   */
  public function deleteFileCopyright($request, $response, $args)
  {
    return $this->deleteFileCX($args, $response, self::TYPE_COPYRIGHT);
  }

  /**
   * Delete email for a particular file
   *
   * @param  ServerRequestInterface $request
   * @param  ResponseHelper         $response
   * @param  array                  $args
   * @return ResponseHelper
   */
  public function deleteFileEmail($request, $response, $args)
  {
    return $this->deleteFileCX($args, $response, self::TYPE_EMAIL);
  }

  /**
   * Delete URL for a particular file
   *
   * @param  ServerRequestInterface $request
   * @param  ResponseHelper         $response
   * @param  array                  $args
   * @return ResponseHelper
   */
  public function deleteFileUrl($request, $response, $args)
  {
    return $this->deleteFileCX($args, $response, self::TYPE_URL);
  }

  /**
   * Delete author for a particular file
   *
   * @param  ServerRequestInterface $request
   * @param  ResponseHelper         $response
   * @param  array                  $args
   * @return ResponseHelper
   */
  public function deleteFileAuthor($request, $response, $args)
  {
    return $this->deleteFileCX($args, $response, self::TYPE_AUTHOR);
  }

  /**
   * Delete ECC for a particular file
   *
   * @param  ServerRequestInterface $request
   * @param  ResponseHelper         $response
   * @param  array                  $args
   * @return ResponseHelper
   */
  public function deleteFileEcc($request, $response, $args)
  {
    return $this->deleteFileCX($args, $response, self::TYPE_ECC);
  }

  /**
   * Delete keyword for a particular file
   *
   * @param  ServerRequestInterface $request
   * @param  ResponseHelper         $response
   * @param  array                  $args
   * @return ResponseHelper
   */
  public function deleteFileKeyword($request, $response, $args)
  {
    return $this->deleteFileCX($args, $response, self::TYPE_KEYWORD);
  }

  /**
   * Delete IPRA for a particular file
   *
   * @param  ServerRequestInterface $request
   * @param  ResponseHelper         $response
   * @param  array                  $args
   * @return ResponseHelper
   */
  public function deleteFileIpra($request, $response, $args)
  {
    return $this->deleteFileCX($args, $response, self::TYPE_IPRA);
  }

  /**
   * Update copyright for a particular file
   *
   * @param  ServerRequestInterface $request
   * @param  ResponseHelper         $response
   * @param  array                  $args
   * @return ResponseHelper
   */
  public function updateFileCopyright($request, $response, $args)
  {
    return $this->updateFileCx($request, $response, $args, self::TYPE_COPYRIGHT);
  }

  /**
   * Update email for a particular file
   *
   * @param  ServerRequestInterface $request
   * @param  ResponseHelper         $response
   * @param  array                  $args
   * @return ResponseHelper
   */
  public function updateFileEmail($request, $response, $args)
  {
    return $this->updateFileCx($request, $response, $args, self::TYPE_EMAIL);
  }

  /**
   * Update URL for a particular file
   *
   * @param  ServerRequestInterface $request
   * @param  ResponseHelper         $response
   * @param  array                  $args
   * @return ResponseHelper
   */
  public function updateFileUrl($request, $response, $args)
  {
    return $this->updateFileCx($request, $response, $args, self::TYPE_URL);
  }

  /**
   * Update author for a particular file
   *
   * @param  ServerRequestInterface $request
   * @param  ResponseHelper         $response
   * @param  array                  $args
   * @return ResponseHelper
   */
  public function updateFileAuthor($request, $response, $args)
  {
    return $this->updateFileCx($request, $response, $args, self::TYPE_AUTHOR);
  }

  /**
   * Update ECC for a particular file
   *
   * @param  ServerRequestInterface $request
   * @param  ResponseHelper         $response
   * @param  array                  $args
   * @return ResponseHelper
   */
  public function updateFileEcc($request, $response, $args)
  {
    return $this->updateFileCx($request, $response, $args, self::TYPE_ECC);
  }

  /**
   * Update keyword for a particular file
   *
   * @param  ServerRequestInterface $request
   * @param  ResponseHelper         $response
   * @param  array                  $args
   * @return ResponseHelper
   */
  public function updateFileKeyword($request, $response, $args)
  {
    return $this->updateFileCx($request, $response, $args, self::TYPE_KEYWORD);
  }

  /**
   * Update IPRA for a particular file
   *
   * @param  ServerRequestInterface $request
   * @param  ResponseHelper         $response
   * @param  array                  $args
   * @return ResponseHelper
   */
  public function updateFileIpra($request, $response, $args)
  {
    return $this->updateFileCx($request, $response, $args, self::TYPE_IPRA);
  }

  /**
   * Restore copyright for a particular file
   *
   * @param  ServerRequestInterface $request
   * @param  ResponseHelper         $response
   * @param  array                  $args
   * @return ResponseHelper
   */
  public function restoreFileCopyright($request, $response, $args)
  {
    return $this->restoreFileCx($args, $response, self::TYPE_COPYRIGHT);
  }

  /**
   * Restore email for a particular file
   *
   * @param  ServerRequestInterface $request
   * @param  ResponseHelper         $response
   * @param  array                  $args
   * @return ResponseHelper
   */
  public function restoreFileEmail($request, $response, $args)
  {
    return $this->restoreFileCx($args, $response, self::TYPE_EMAIL);
  }

  /**
   * Restore URL for a particular file
   *
   * @param  ServerRequestInterface $request
   * @param  ResponseHelper         $response
   * @param  array                  $args
   * @return ResponseHelper
   */
  public function restoreFileUrl($request, $response, $args)
  {
    return $this->restoreFileCx($args, $response, self::TYPE_URL);
  }

  /**
   * Restore author for a particular file
   *
   * @param  ServerRequestInterface $request
   * @param  ResponseHelper         $response
   * @param  array                  $args
   * @return ResponseHelper
   */
  public function restoreFileAuthor($request, $response, $args)
  {
    return $this->restoreFileCx($args, $response, self::TYPE_AUTHOR);
  }

  /**
   * Restore ECC for a particular file
   *
   * @param  ServerRequestInterface $request
   * @param  ResponseHelper         $response
   * @param  array                  $args
   * @return ResponseHelper
   */
  public function restoreFileEcc($request, $response, $args)
  {
    return $this->restoreFileCx($args, $response, self::TYPE_ECC);
  }

  /**
   * Restore keyword for a particular file
   *
   * @param  ServerRequestInterface $request
   * @param  ResponseHelper         $response
   * @param  array                  $args
   * @return ResponseHelper
   */
  public function restoreFileKeyword($request, $response, $args)
  {
    return $this->restoreFileCx($args, $response, self::TYPE_KEYWORD);
  }

  /**
   * Restore IPRA for a particular file
   *
   * @param  ServerRequestInterface $request
   * @param  ResponseHelper         $response
   * @param  array                  $args
   * @return ResponseHelper
   */
  public function restoreFileIpra($request, $response, $args)
  {
    return $this->restoreFileCx($args, $response, self::TYPE_IPRA);
  }

  /**
   * Get total number of copyrights for a particular upload-tree
   *
   * @param ServerRequestInterface $request
   * @param ResponseHelper $response
   * @param array $args
   * @return ResponseHelper
   * @throws HttpErrorException
   */
  public function getTotalFileCopyrights($request, $response, $args)
  {
    $uploadPk = $args["id"];
    $uploadTreeId = $args["itemId"];
    $query = $request->getQueryParams();
    $statusVal = true;

    $this->uploadAccessible($uploadPk);
    $this->isItemExists($uploadTreeId);

    if (!array_key_exists(self::COPYRIGHT_PARAM, $query)) {
      throw new HttpBadRequestException("Bad Request. 'status' is a " .
        "required query param with expected values 'active' or 'inactive");
    }
    $status = $query[self::COPYRIGHT_PARAM];
    if ($status == "active") {
      $statusVal = true;
    } else if ($status == "inactive") {
      $statusVal = false;
    } else {
      throw new HttpBadRequestException("Bad Request. Invalid query " .
        "parameter, expected values 'active' or 'inactive");
    }
    $agentId = $this->copyrightHist->getAgentId($uploadPk, 'copyright_ars');
    $uploadTreeTableName = $this->restHelper->getUploadDao()->getUploadtreeTableName($uploadPk);
    $returnVal = $this->copyrightDao->getTotalCopyrights($uploadPk, $uploadTreeId, $uploadTreeTableName, $agentId, 'statement', $statusVal);
    return $response->withJson(array("total_copyrights" => intval($returnVal)), 200);
  }

  /**
   * Get all cx for a particular upload-tree
   *
   * @param ServerRequestInterface $request
   * @param ResponseHelper $response
   * @param array $args
   * @param int $cxType Type of data to fetch (self::TYPE_*)
   * @return ResponseHelper
   * @throws HttpErrorException
   */
  private function getFileCX($request, $response, $args, $cxType)
  {
    switch ($cxType) {
      case self::TYPE_COPYRIGHT:
        $dataType = 'statement';
        $agentArs = 'copyright_ars';
        break;
      case self::TYPE_EMAIL:
        $dataType = 'email';
        $agentArs = 'copyright_ars';
        break;
      case self::TYPE_URL:
        $dataType = 'url';
        $agentArs = 'copyright_ars';
        break;
      case self::TYPE_AUTHOR:
        $dataType = 'author';
        $agentArs = 'copyright_ars';
        break;
      case self::TYPE_ECC:
        $dataType = 'ecc';
        $agentArs = 'ecc_ars';
        break;
      case self::TYPE_KEYWORD:
        $dataType = 'keyword';
        $agentArs = 'keyword_ars';
        break;
      case self::TYPE_IPRA:
        $dataType = 'ipra';
        $agentArs = 'ipra_ars';
        break;
      default:
        $dataType = 'statement';
        $agentArs = 'copyright_ars';
    }
    $uploadPk = $args["id"];
    $uploadTreeId = $args["itemId"];
    $query = $request->getQueryParams();
    $limit = $request->getHeaderLine(self::LIMIT_PARAM);
    $finalVal = [];
    if (!empty($limit)) {
      $limit = filter_var($limit, FILTER_VALIDATE_INT);
      if ($limit < 1) {
        throw new HttpBadRequestException(
          "limit should be positive integer > 1");
      }
    } else {
      $limit = self::COPYRIGHT_FETCH_LIMIT;
    }
    if (!array_key_exists(self::COPYRIGHT_PARAM, $query)) {
      throw new HttpBadRequestException("Bad Request. 'status' is a " .
        "required query param with expected values 'active' or 'inactive");
    }
    $status = $query[self::COPYRIGHT_PARAM];
    if ($status == "active") {
      $statusVal = true;
    } else if ($status == "inactive") {
      $statusVal = false;
    } else {
      throw new HttpBadRequestException("Bad Request. Invalid query " .
        "parameter, expected values 'active' or 'inactive");
    }

    $this->uploadAccessible($uploadPk);
    $this->isItemExists($uploadTreeId);

    $agentId = $this->copyrightHist->getAgentId($uploadPk, $agentArs);
    $uploadTreeTableName = $this->restHelper->getUploadDao()->getuploadTreeTableName($uploadPk);
    $page = $request->getHeaderLine(self::PAGE_PARAM);
    if (empty($page) && $page != "0") {
      $page = 1;
    }
    if (!empty($page) || $page == "0") {
      $page = filter_var($page, FILTER_VALIDATE_INT);
      if ($page <= 0) {
        throw new HttpBadRequestException(
          "page should be positive integer > 0");
      }
    }
    $offset = $limit * ($page - 1);
    list($rows, $iTotalDisplayRecords, $iTotalRecords) = $this->copyrightHist
      ->getCopyrights($uploadPk, $uploadTreeId, $uploadTreeTableName,
        $agentId, $dataType, 'active', $statusVal, $offset, $limit);
    foreach ($rows as $row) {
      $row['count'] = intval($row['copyright_count']);
      unset($row['copyright_count']);
      $finalVal[] = $row;
    }
    $totalPages = intval(ceil($iTotalRecords / $limit));
    if ($page > $totalPages) {
      throw (new HttpBadRequestException(
        "Can not exceed total pages: $totalPages"))
        ->setHeaders(["X-Total-Pages" => $totalPages]);
    }
    return $response->withHeader("X-Total-Pages", $totalPages)->withJson($finalVal, 200);
  }

  /**
   * Delete cx for a particular file
   *
   * @param array $args
   * @param ResponseHelper $response
   * @param int $cxType Type of data to fetch (self::TYPE_*)
   * @return ResponseHelper
   * @throws HttpErrorException
   */
  private function deleteFileCX($args, $response, $cxType)
  {
    list($dataType, $delName) = $this->convertTypeToTable($cxType);

    $uploadDao = $this->restHelper->getUploadDao();
    $uploadPk = intval($args['id']);
    $uploadTreeId = intval($args['itemId']);
    $copyrightHash = $args['hash'];
    $userId = $this->restHelper->getUserId();
    $cpTable = $this->copyrightHist->getTableName($dataType);

    $this->uploadAccessible($uploadPk);
    $this->isItemExists($uploadTreeId);

    $uploadTreeTableName = $uploadDao->getUploadTreeTableName($uploadTreeId);
    $item = $uploadDao->getItemTreeBounds($uploadTreeId, $uploadTreeTableName);
    $this->copyrightDao->updateTable($item, $copyrightHash, '', $userId, $cpTable, 'delete');
    $returnVal = new Info(200, "Successfully removed $delName.", InfoType::INFO);
    return $response->withJson($returnVal->getArray(), $returnVal->getCode());
  }

  /**
   * Restore cx for a particular file
   *
   * @param array $args
   * @param ResponseHelper $response
   * @param int $cxType Type of data to fetch (self::TYPE_*)
   * @return ResponseHelper
   * @throws HttpErrorException
   */
  private function restoreFileCx($args, $response, $cxType)
  {
    list($dataType, $resName) = $this->convertTypeToTable($cxType);
    $uploadPk = intval($args['id']);
    $uploadTreeId = intval($args['itemId']);
    $copyrightHash = ($args['hash']);
    $userId = $this->restHelper->getUserId();
    $cpTable = $this->copyrightHist->getTableName($dataType);

    $this->uploadAccessible($uploadPk);
    $this->isItemExists($uploadTreeId);

    $uploadTreeTableName = $this->restHelper->getUploadDao()->getuploadTreeTableName($uploadTreeId);
    $item = $this->restHelper->getUploadDao()->getItemTreeBounds($uploadTreeId, $uploadTreeTableName);
    $this->copyrightDao->updateTable($item, $copyrightHash, '', $userId, $cpTable, 'rollback');
    $returnVal = new Info(200, "Successfully restored $resName.", InfoType::INFO);
    return $response->withJson($returnVal->getArray(), 200);
  }

  /**
   * Update cx for a particular file
   *
   * @param ServerRequestInterface $request
   * @param ResponseHelper $response
   * @param array $args
   * @param int $cxType Type of data to fetch (self::TYPE_*)
   * @return ResponseHelper
   * @throws HttpErrorException
   */
  private function updateFileCx($request, $response, $args, $cxType)
  {
    list($dataType, $resName) = $this->convertTypeToTable($cxType);
    $uploadTreeId = intval($args["itemId"]);
    $uploadPk = intval($args["id"]);
    $copyrightHash = $args["hash"];
    $userId = $this->restHelper->getUserId();
    $cpTable = $this->copyrightHist->getTableName($dataType);
    $body = $this->getParsedBody($request);
    $content = $body['content'];

    $this->uploadAccessible($uploadPk);
    $this->isItemExists($uploadTreeId);

    $uploadTreeTableName = $this->restHelper->getUploadDao()->getuploadTreeTableName($uploadTreeId);
    $item = $this->restHelper->getUploadDao()->getItemTreeBounds($uploadTreeId, $uploadTreeTableName);
    $this->copyrightDao->updateTable($item, $copyrightHash, $content, $userId, $cpTable);
    $returnVal = new Info(200, "Successfully Updated $resName.", InfoType::INFO);
    return $response->withJson($returnVal->getArray(), 200);
  }

  /**
   * Convert CX Type to table name and display name.
   *
   * @param int $cxType
   * @return string[]
   */
  private function convertTypeToTable(int $cxType): array
  {
    switch ($cxType) {
      case self::TYPE_COPYRIGHT:
        $dataType = 'statement';
        $dispName = 'copyright';
        break;
      case self::TYPE_EMAIL:
        $dispName = $dataType = 'email';
        break;
      case self::TYPE_URL:
        $dispName = $dataType = 'url';
        break;
      case self::TYPE_AUTHOR:
        $dispName = $dataType = 'author';
        break;
      case self::TYPE_ECC:
        $dispName = $dataType = 'ecc';
        break;
      case self::TYPE_KEYWORD:
        $dispName = $dataType = 'keyword';
        break;
      case self::TYPE_IPRA:
        $dispName = $dataType = 'ipra';
        break;
      default:
        $dataType = 'statement';
        $dispName = 'copyright';
    }
    return array($dataType, $dispName);
  }
}
