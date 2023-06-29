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

use Fossology\UI\Api\Helper\AuthHelper;
use Fossology\UI\Api\Helper\ResponseHelper;
use Fossology\UI\Api\Models\Info;
use Fossology\UI\Api\Models\InfoType;
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
   * @param  Request        $request  PSR7 request
   * @param  RequestHandler $response PSR-15 request handler
   *
   * @return ResponseInterface
   */
  public function __invoke(Request $request, RequestHandler $handler) : ResponseInterface
  {
    global $SysConf;
    $requestUri = $request->getUri();
    $requestPath = strtolower($requestUri->getPath());
    $authFreePaths = ["/version", "/info", "/openapi", "/health"];

    $isPassThroughPath = false;
    foreach ($authFreePaths as $authFreePath) {
      if (strpos($requestPath, $authFreePath) !== false) {
        $isPassThroughPath = true;
        break;
      }
    }

    if (stristr($request->getMethod(), "options") !== false) {
      $response = $handler->handle($request);
    } elseif ($isPassThroughPath) {
      $response = $handler->handle($request);
    } elseif (stristr($requestUri->getPath(), "/tokens") !== false &&
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
      $tokenValid = $authHelper->verifyAuthToken($jwtToken, $userId,
        $tokenScope);
      if ($tokenValid === true && (stristr($request->getMethod(), "get") === false &&
          stristr($tokenScope, "write") === false)) {
        /*
         * If the request method is not GET and token scope is not write,
         * do not allow the request to pass through.
         */
        $tokenValid = new Info(403, "Do not have required scope.", InfoType::ERROR);
      }
      if ($tokenValid === true) {
        $groupName = "";
        $groupName = strval($request->getHeaderLine('groupName'));
        if (!empty($groupName)) { // if request contains groupName
          $userHasGroupAccess = $authHelper->userHasGroupAccess($userId, $groupName);
          if ($userHasGroupAccess === true) {
            $authHelper->updateUserSession($userId, $tokenScope, $groupName);
            $response = $handler->handle($request);
          } else { // no group access or group does not exist
            $response = new ResponseHelper();
            $response = $response->withJson($userHasGroupAccess->getArray(),
              $userHasGroupAccess->getCode());
          }
        } else { // no groupName passed, use defult groupId saved in DB
          $authHelper->updateUserSession($userId, $tokenScope);
          $response = $handler->handle($request);
        }
      } else {
        $response = new ResponseHelper();
        $response = $response->withJson($tokenValid->getArray(),
          $tokenValid->getCode());
      }
    }
    return $response
      ->withHeader('Access-Control-Allow-Origin', $SysConf['SYSCONFIG']['CorsOrigins'])
      ->withHeader('Access-Control-Expose-Headers', 'Look-at, X-Total-Pages, Retry-After')
      ->withHeader('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, Accept, Origin, Authorization, action, accesslevel, active, copyright, Content-Type, description, filename, filesizemax, filesizemin, folderDescription, folderId, folderName, groupName, ignoreScm, applyGlobal, license, limit, name, page, parent, parentFolder, public, reportFormat, searchType, tag, upload, uploadDescription, uploadId, uploadType')
      ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, PATCH, OPTIONS');
  }
}
