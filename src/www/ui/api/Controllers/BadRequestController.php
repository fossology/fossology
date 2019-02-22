<?php
/***************************************************************
 Copyright (C) 2018 Siemens AG
 Author: Gaurav Mishra <mishra.gaurav@siemens.com>

 This program is free software; you can redistribute it and/or
 modify it under the terms of the GNU General Public License
 version 2 as published by the Free Software Foundation.

 This program is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 GNU General Public License for more details.

 You should have received a copy of the GNU General Public License along
 with this program; if not, write to the Free Software Foundation, Inc.,
 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 ***************************************************************/
/**
 * @file
 * @brief Controller for bad request queries
 */

namespace Fossology\UI\Api\Controllers;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Fossology\UI\Api\Models\Info;
use Fossology\UI\Api\Models\InfoType;

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
   * @param ResponseInterface $response
   * @param array $args
   * @return ResponseInterface
   */
  public function __invoke($request, $response, $args)
  {
    $id = $args['params'];
    $returnVal = new Info(400, "ID must be a positive integer, $id passed!",
      InfoType::ERROR);
    return $response->withJson($returnVal->getArray(), $returnVal->getCode());
  }
}
