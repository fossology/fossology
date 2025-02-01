<?php
/*
 SPDX-FileCopyrightText: © 2023 Samuel Dushimimana <dushsam100@gmail.com>

 SPDX-License-Identifier: GPL-2.0-only
*/
/**
 * @file
 * @brief ApiVersion enum
 */
namespace Fossology\UI\Api\Models;

use Psr\Http\Message\ServerRequestInterface;

/**
 * @class ApiVersion
 * @brief ApiVersion enum
 */
class ApiVersion
{
  const V1 = 1;
  const V2 = 2;
  const ATTRIBUTE_NAME = 'apiVersion';

  /**
   * @param ServerRequestInterface $request
   * @return int
   */
  public static function getVersion(ServerRequestInterface $request): int
  {
    return $request->getAttribute(self::ATTRIBUTE_NAME, self::V1);
  }

  public static function getVersionFromUri(): int
  {
    $uri = $_SERVER['REQUEST_URI'];
    $apiVersion = ApiVersion::V1;

    if (strpos($uri, '/api/v2/') !== false) {
      $apiVersion = ApiVersion::V2;
    }

    return $apiVersion;
  }
}
