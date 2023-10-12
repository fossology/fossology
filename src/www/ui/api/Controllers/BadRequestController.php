<?php
/*
 SPDX-FileCopyrightText: © 2018 Siemens AG
 Author: Gaurav Mishra <mishra.gaurav@siemens.com>

 SPDX-License-Identifier: GPL-2.0-only
*/
/**
 * @file
 * @brief Controller for bad request queries
 */

namespace Fossology\UI\Api\Controllers;

use Fossology\UI\Api\Helper\ResponseHelper;
use Fossology\UI\Api\Models\Info;
use Fossology\UI\Api\Models\InfoType;
use Psr\Http\Message\ServerRequestInterface;

/**
 * @class AuthController
 * @brief Controller for bad requests
 */
class BadRequestController extends RestController
{

  /**
   * Get app the uploads for current user
   *
   * @param ServerRequestInterface $request
   * @param ResponseHelper $response
   * @param array $args
   * @return ResponseHelper
   */
  public function __invoke($request, $response, $args)
  {
    $id = $args['params'];
    $returnVal = new Info(404, "Unable to find the path '$id'.",
      InfoType::ERROR);
    return $response->withJson($returnVal->getArray(), $returnVal->getCode());
  }
}
