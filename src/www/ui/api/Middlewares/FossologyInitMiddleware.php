<?php
/***************************************************************
 Copyright (C) 2019 Siemens AG
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
   * @param  RequestHandler $response PSR-15 request handler
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
