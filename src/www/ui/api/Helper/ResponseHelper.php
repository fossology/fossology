<?php
/*
 SPDX-FileCopyrightText: © 2021 Siemens AG
 Author: Gaurav Mishra <mishra.gaurav@siemens.com>

 SPDX-License-Identifier: GPL-2.0-only
*/

/**
 * @file
 * @brief Helper for simpler Slim responses
 */
namespace Fossology\UI\Api\Helper;

use Slim\Psr7\Response;

/**
 * @class ResponseHelper
 * @brief Override Slim response for withJson function
 */
class ResponseHelper extends Response
{
  /**
   * Create a JSON response from Info objects
   *
   * @param array $arr  Array to return
   * @param int   $stat Return status
   */
  public function withJson($arr, int $stat=200)
  {
    $this->getBody()->write(json_encode($arr));
    return $this->withHeader("Content-Type", "application/json")
      ->withStatus($stat);
  }
}
