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
