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

require_once dirname(__FILE__, 5) . "/lib/php/bootstrap.php";

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;

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
   * @return ResponseInterface
   */
  public function __invoke(Request $request, RequestHandler $handler) : ResponseInterface
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
