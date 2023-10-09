<?php
/*
 Author: Soham Banerjee <sohambanerjee4abc@hotmail.com>
 SPDX-FileCopyrightText: © 2023 Soham Banerjee <sohambanerjee4abc@hotmail.com>

 SPDX-License-Identifier: GPL-2.0-only
*/

/**
 * @file
 * @brief Controller for Admin Customisation queries
 */

namespace Fossology\UI\Api\Controllers;

use Fossology\Lib\Dao\SysConfigDao;
use Fossology\UI\Api\Exceptions\HttpBadRequestException;
use Fossology\UI\Api\Exceptions\HttpErrorException;
use Fossology\UI\Api\Exceptions\HttpForbiddenException;
use Fossology\UI\Api\Helper\ResponseHelper;
use Fossology\UI\Api\Models\Info;
use Fossology\UI\Api\Models\InfoType;
use Psr\Http\Message\ServerRequestInterface;


class CustomiseController extends RestController
{
  /**
   * @var SysConfigDao $sysconfigDao
   * SysConfig Dao object
   */
  private $sysconfigDao;


  public function __construct($container)
  {
    parent::__construct($container);
    $this->sysconfigDao = $this->container->get('dao.sys_config');
  }

  /**
   * Get all config data for the admin
   *
   * @param ServerRequestInterface $request
   * @param ResponseHelper $response
   * @param array $args
   * @return ResponseHelper
   * @throws HttpForbiddenException
   */
  public function getCustomiseData($request, $response, $args)
  {
    $this->throwNotAdminException();
    $returnVal = $this->sysconfigDao->getConfigData();
    $finalVal = $this->sysconfigDao->getCustomiseData($returnVal);
    return $response->withJson($finalVal, 200);
  }

  /**
   * Update config data for the admin
   *
   * @param ServerRequestInterface $request
   * @param ResponseHelper $response
   * @param array $args
   * @return ResponseHelper
   * @throws HttpErrorException
   */
  public function updateCustomiseData($request, $response, $args)
  {
    $this->throwNotAdminException();
    $body = $this->getParsedBody($request);
    if (empty($body) || !array_key_exists("key", $body) || !array_key_exists("value", $body)) {
      throw new HttpBadRequestException("Invalid request body.");
    }
    list($success, $msg) = $this->sysconfigDao->UpdateConfigData($body);
    if (!$success) {
      throw new HttpBadRequestException($msg);
    }
    $info = new Info(200, "Successfully updated $msg.",
      InfoType::INFO);
    return $response->withJson($info->getArray(), $info->getCode());
  }

  /**
   * Get Banner Message
   *
   * @param  ServerRequestInterface $request
   * @param  ResponseHelper         $response
   * @param  array                  $args
   * @return ResponseHelper
   */
  public function getBannerMessage($request, $response, $args)
  {
    $returnVal = $this->sysconfigDao->getBannerData();
    return $response->withJson($returnVal, 200);
  }
}
