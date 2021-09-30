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

use Psr\Http\Message\ResponseInterface;
use Slim\Psr7\Factory\ResponseFactory;

/**
 * @class ResponseFactoryHelper
 * @brief Override Slim response factory for custom response
 */
class ResponseFactoryHelper extends ResponseFactory
{
  /**
   * {@inheritdoc}
   */
  public function createResponse(
    int $code = 200,
    string $reasonPhrase = ''
  ): ResponseInterface
  {
    $res = new ResponseHelper($code);

    if ($reasonPhrase !== '') {
      $res = $res->withStatus($code, $reasonPhrase);
    }

    return $res;
  }
}
