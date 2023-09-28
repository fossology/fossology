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

use Fossology\Lib\Auth\Auth;
use Fossology\UI\Api\Helper\ResponseHelper;
use Fossology\UI\Api\Models\Info;
use Fossology\UI\Api\Models\InfoType;
use Psr\Http\Message\ServerRequestInterface;


class CustomiseController extends RestController
{
  /**
   * @var ContainerInterface $container
   * Slim container
   */
  protected $container;

  /**
   * @var SysConfigDao $sysconfigDao
   * SysConfig Dao object
   */
  private $sysconfigDao;


  public function __construct($container)
  {
    parent::__construct($container);
    $this->restHelper = $container->get('helper.restHelper');
    $this->sysconfigDao = $container->get('dao.sys_config');
  }

  /**
   * Get all config data for the admin
   *
   * @param  ServerRequestInterface $request
   * @param  ResponseHelper         $response
   * @param  array                  $args
   * @return ResponseHelper
   */

  public function getCustomiseData($request, $response, $args)
  {
    if (Auth::isAdmin()) {
      $returnVal = $this->sysconfigDao->getConfigData();
      $finalVal = $this->sysconfigDao->getCustomiseData($returnVal);
      return $response->withJson($finalVal, 200);
    } else {
      $returnVal = new Info(403, 'Access Denied', InfoType::ERROR);
      return $response->withJson($returnVal->getArray(), $returnVal->getCode());
    }
  }

  /**
   * Update config data for the admin
   *
   * @param  ServerRequestInterface $request
   * @param  ResponseHelper         $response
   * @param  array                  $args
   * @return ResponseHelper
   */

  public function updateCustomiseData($request, $response, $args)
  {
    if (!Auth::isAdmin()) {
      $error = new Info(403, "Only admin can access this endpoint.", InfoType::ERROR);
      return $response->withJson($error->getArray(), $error->getCode());
    }
    $body = $this->getParsedBody($request);
    if (empty($body) || !array_key_exists("key", $body) || !array_key_exists("value", $body)) {
      $error = new Info(400, "Invalid request body.", InfoType::ERROR);
      return $response->withJson($error->getArray(), $error->getCode());
    }
    list($success, $msg) = $this->sysconfigDao->UpdateConfigData($body);
    if ($success) {
      $info = new Info(200, "Successfully updated $msg.",
        InfoType::INFO);
    } else {
      $info = new Info(400, $msg, InfoType::INFO);
    }
    return $response->withJson($info->getArray(), $info->getCode());
  }
}
