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
 * @dir
 * @brief Middlewares for the Slim framework
 * @file
 * @brief Auth middleware for Slim
 */

namespace Fossology\UI\Api\Middlewares;

use Fossology\UI\Api\Models\Info;
use Fossology\UI\Api\Models\InfoType;
use Fossology\UI\Api\Helper\AuthHelper;

/**
 * @class RestAuthHelper
 * @brief Authentication middleware for Slim framework
 */
class RestAuthHelper
{
  /**
   * Check authentication for all calls, except for /auth/
   *
   * @param  \Psr\Http\Message\ServerRequestInterface $request  PSR7 request
   * @param  \Psr\Http\Message\ResponseInterface      $response PSR7 response
   * @param  callable                                 $next     Next middleware
   *
   * @return \Psr\Http\Message\ResponseInterface
   */
  public function __invoke($request, $response, $next)
  {
    if(stristr($request->getUri()->getPath(), "/auth") !== false) {
      $response = $next($request, $response);
    } else {
      $authHelper = new AuthHelper();
      $username = $request->getHeaderLine("php-auth-user");
      $password = $request->getHeaderLine("php-auth-pw");
      if(!$authHelper->checkUsernameAndPassword($username, $password)) {
        $error = new Info(403, "Not authorized", InfoType::ERROR);
        $response = $response->withJson($error->getArray(), $error->getCode());
      } else {
        $response = $next($request, $response);
      }
    }
    return $response;
  }
}
