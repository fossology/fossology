<?php
/**
 * *************************************************************
 * Copyright (C) 2021 Siemens AG
 * Author: Gaurav Mishra <mishra.gaurav@siemens.com>
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * version 2 as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 * *************************************************************
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
