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
 * @brief Controller for auth queries
 */

namespace Fossology\UI\Api\Controllers;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Fossology\UI\Api\Models\Info;
use Fossology\UI\Api\Models\InfoType;

/**
 * @class AuthController
 * @brief Controller for Auth requests
 */
class AuthController extends RestController
{

  /**
   * Get app the uploads for current user
   *
   * @param ServerRequestInterface $request
   * @param ResponseInterface $response
   * @param array $args
   * @return ResponseInterface
   */
  public function getAuthHeaders($request, $response, $args)
  {
    $username = $request->getQueryParam("username");
    $password = $request->getQueryParam("password");

    // Checks if user is valid
    if ($this->restHelper->getAuthHelper()->checkUsernameAndPassword($username,
      $password)) {
      $base64String = base64_encode("$username:$password");
      $newHeader = "authorization: Basic $base64String";
      // Create the response header
      return $response->withJson([
        "header" => $newHeader
      ], 200);
    } else {
      $returnVal = new Info(404, "Username or password is incorrect",
        InfoType::ERROR);
      return $response->withJson($returnVal->getArray(), $returnVal->getCode());
    }
  }
}
