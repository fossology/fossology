<?php
/*
 SPDX-FileCopyrightText: © 2018-2019 Siemens AG
 Author: Gaurav Mishra <mishra.gaurav@siemens.com>

 SPDX-License-Identifier: GPL-2.0-only
*/
/**
 * @dir
 * @brief Middlewares for the Slim framework
 * @file
 * @brief Auth middleware for Slim
 */

namespace Fossology\UI\Api\Middlewares;

use Fossology\UI\Api\Exceptions\HttpBadRequestException;
use Fossology\UI\Api\Exceptions\HttpForbiddenException;
use Fossology\UI\Api\Helper\AuthHelper;
use Fossology\UI\Api\Helper\CorsHelper;
use Fossology\UI\Api\Models\ApiVersion;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;

/**
 * @class RestAuthMiddleware
 * @brief Authentication middleware for Slim framework
 */
class RestAuthMiddleware
{
  /**
   * Check authentication for all calls, except for /auth, /tokens
   *
   * @param Request $request PSR7 request
   * @param RequestHandler $handler PSR-15 request handler
   *
   * @return ResponseInterface
   * @throws HttpForbiddenException If the token does not have required scope
   * @throws HttpBadRequestException If the token is not valid
   */
  public function __invoke(Request $request, RequestHandler $handler) : ResponseInterface
  {
    $requestUri = $request->getUri();
    $requestPath = strtolower($requestUri->getPath());
    $authFreePaths = ["/info", "/openapi", "/health" , "/oauth/login", "/oauth/callback"];

    $isPassThroughPath = false;
    // path is /repo/api/v2/<endpoint>, we need to get only the endpoint part
    $parts = explode("/", $requestPath, 5);
    $endpoint = "/".end($parts);
    foreach ($authFreePaths as $authFreePath) {
      if ( $endpoint === $authFreePath ) {
        $isPassThroughPath = true;
        break;
      }
    }

    if (stristr($request->getMethod(), "options") !== false) {
      $response = $handler->handle($request);
    } elseif ($isPassThroughPath) {
      $response = $handler->handle($request);
    } elseif (stristr($requestUri->getPath(), "/tokens") !== false &&
        stristr($requestUri->getPath(), "/users/tokens") === false &&
        stristr($request->getMethod(), "post") !== false) {
      $response = $handler->handle($request);
    } else {
      /** @var AuthHelper $authHelper */
      $authHelper = $GLOBALS['container']->get('helper.authHelper');
      $authHeaders = $request->getHeader('Authorization');
      if (!empty($authHeaders)) {
        $jwtToken = $authHeaders[0];
      } else {
        $jwtToken = "";
      }
      $userId = -1;
      $tokenScope = false;
      $authHelper->verifyAuthToken($jwtToken, $userId, $tokenScope);
      if (stristr($request->getMethod(), "get") === false &&
          stristr($tokenScope, "write") === false) {
        /*
         * If the request method is not GET and token scope is not write,
         * do not allow the request to pass through.
         */
        throw new HttpForbiddenException("Do not have required scope.");
      }
      if (ApiVersion::getVersion($request) == ApiVersion::V2) {
        $queryParameters = $request->getQueryParams();
        $groupName = $queryParameters['groupName'] ?? "";
      } else {
        $groupName = $request->getHeaderLine('groupName');
      }
      if (!empty($groupName)) { // if request contains groupName
        $authHelper->userHasGroupAccess($userId, $groupName);
        $authHelper->updateUserSession($userId, $tokenScope, $groupName);
      } else { // no groupName passed, use default groupId saved in DB
        $authHelper->updateUserSession($userId, $tokenScope);
      }
      $response = $handler->handle($request);
    }
    return CorsHelper::addCorsHeaders($response);
  }
}
