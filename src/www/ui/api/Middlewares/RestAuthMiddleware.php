<?php
/***************************************************************
 Copyright (C) 2018-2019 Siemens AG
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
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * @class RestAuthMiddleware
 * @brief Authentication middleware for Slim framework
 */
class RestAuthMiddleware
{
  /**
   * Check authentication for all calls, except for /auth, /tokens
   *
   * @param  ServerRequestInterface $request  PSR7 request
   * @param  ResponseInterface      $response PSR7 response
   * @param  callable               $next     Next middleware
   *
   * @return ResponseInterface
   */
  public function __invoke($request, $response, $next)
  {
    $requestUri = $request->getUri();
    if (stristr($requestUri->getPath(), "/auth") !== false) {
      $response = $next($request, $response);
    } elseif (stristr($requestUri->getPath(), "/tokens") !== false &&
      stristr($request->getMethod(), "post") !== false) {
      $response = $next($request, $response);
    } else {
      $authHelper = $GLOBALS['container']->get('helper.authHelper');
      $jwtToken = $request->getHeader('Authorization')[0];
      $tokenValid = $authHelper->verifyAuthToken($jwtToken,
        $requestUri->getHost(), $userId, $tokenScope);
      if ($tokenValid === true && (stristr($request->getMethod(), "get") === false &&
          stristr($tokenScope, "write") === false)) {
        /*
         * If the request method is not GET and token scope is not write,
         * do not allow the request to pass through.
         */
        $tokenValid = new Info(403, "Do not have required scope.", InfoType::ERROR);
      }
      if ($tokenValid === true) {
        $authHelper->updateUserSession($userId, $tokenScope);
        $response = $next($request, $response);
      } else {
        $response = $response->withJson($tokenValid->getArray(),
          $tokenValid->getCode());
      }
    }
    return $response;
  }
}
