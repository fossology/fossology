<?php
/*
 SPDX-FileCopyrightText: © 2018 Siemens AG
 Author: Gaurav Mishra <mishra.gaurav@siemens.com>

 SPDX-License-Identifier: GPL-2.0-only
*/
/**
 * @dir
 * @brief Controllers for REST requests
 * @file
 * @brief Base controller for REST calls
 */

namespace Fossology\UI\Api\Controllers;

use Fossology\Lib\Auth\Auth;
use Fossology\UI\Api\Exceptions\HttpForbiddenException;
use Fossology\UI\Api\Exceptions\HttpNotFoundException;
use Fossology\UI\Api\Helper\DbHelper;
use Fossology\UI\Api\Helper\RestHelper;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * @class RestController
 * @brief Base controller for REST calls
 */
class RestController
{
  /**
   * @var ContainerInterface $container
   * Slim container
   */
  protected $container;

  /**
   * @var RestHelper $restHelper
   * Rest helper object in use
   */
  protected $restHelper;

  /**
   * @var DbHelper $dbHelper
   * DB helper object in use
   */
  protected $dbHelper;

  /**
   * Constructor for base controller
   * @param ContainerInterface $container
   */
  public function __construct($container)
  {
    $this->container = $container;
    $this->restHelper = $this->container->get('helper.restHelper');
    $this->dbHelper = $this->restHelper->getDbHelper();
  }

  /**
   * @brief Parse request body as JSON and return associative PHP array.
   *
   * If request is of type application/json, read the body content and parse
   * with json_decode(). Otherwise, use slim's getParsedBody().
   *
   * @param ServerRequestInterface $request Request to parse
   * @return array|null Parsed JSON, or null on error
   */
  protected function getParsedBody(ServerRequestInterface $request)
  {
    if ($this->isJsonRequest($request)) {
      $content = $request->getBody()->getContents();
      return json_decode($content, true);
    } else {
      // application/x-www-form-urlencoded or multipart/form-data
      return $request->getParsedBody();
    }
  }

  /**
   * Throw an HttpForbiddenException if the user is not admin.
   *
   * @throws HttpForbiddenException
   */
  protected function throwNotAdminException(): void
  {
    if (!Auth::isAdmin()) {
      throw new HttpForbiddenException("Only admin can access this endpoint.");
    }
  }

  /**
   * Check if upload is accessible
   *
   * @param integer $id Upload ID
   * @throws HttpNotFoundException Upload not found
   * @throws HttpForbiddenException Upload not accessible
   */
  protected function uploadAccessible($id): void
  {
    if (! $this->dbHelper->doesIdExist("upload", "upload_pk", $id)) {
      throw new HttpNotFoundException("Upload does not exist");
    }
    if (! $this->restHelper->getUploadDao()->isAccessible($id,
        $this->restHelper->getGroupId())) {
      throw new HttpForbiddenException("Upload is not accessible");
    }
  }

  /**
   * Check if upload tree is accessible
   *
   * @param int $uploadId
   * @param int $itemId
   * @return void
   * @throws HttpNotFoundException
   */
  protected function isItemExists(int $uploadId, int $itemId): void
  {
    if (!$this->dbHelper->doesIdExist(
      $this->restHelper->getUploadDao()->getUploadtreeTableName($uploadId),
      "uploadtree_pk", $itemId)) {
      throw new HttpNotFoundException("Item does not exist");
    }
  }

  /**
   * Check if request contains header "Content-Type: application/json
   * @param ServerRequestInterface $request
   * @return bool
   */
  public function isJsonRequest($request)
  {
    return strcasecmp($request->getHeaderLine('Content-Type'),
        "application/json") === 0;
  }
}
