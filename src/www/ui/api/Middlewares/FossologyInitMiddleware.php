<?php
/*
 SPDX-FileCopyrightText: © 2019 Siemens AG
 Author: Gaurav Mishra <mishra.gaurav@siemens.com>

 SPDX-License-Identifier: GPL-2.0-only
*/
/**
 * @file
 * @brief FOSSology initializer for Slim
 */

namespace Fossology\UI\Api\Middlewares;

require_once dirname(dirname(dirname(dirname(dirname(__FILE__))))) .
  "/lib/php/bootstrap.php";

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\Psr7\Response;

/**
 * @class FossologyInitMiddleware
 * @brief Middleware to initialize FOSSology for Slim framework
 */
class FossologyInitMiddleware
{
  /**
   * Clean all FOSSology plugins and load them again.
   *
   * @param  Request        $request  PSR7 request
   * @param  RequestHandler $handler PSR-15 request handler
   *
   * @return Response
   */
  public function __invoke(Request $request, RequestHandler $handler) : Response
  {
    global $container;
    $timingLogger = $container->get("log.timing");
    plugin_preinstall();
    plugin_postinstall();
    $timingLogger->toc("setup plugins");

    $response = $handler->handle($request);

    plugin_unload();
    return $response;
  }
}
