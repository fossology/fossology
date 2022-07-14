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

use Psr\Container\ContainerInterface;
use Fossology\UI\Api\Helper\RestHelper;
use Fossology\UI\Api\Helper\DbHelper;
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
    if (strcasecmp($request->getHeaderLine('Content-Type'),
        "application/json") === 0) {
      $content = $request->getBody()->getContents();
      return json_decode($content, true);
    } else {
      // application/x-www-form-urlencoded or multipart/form-data
      return $request->getParsedBody();
    }
  }
}
