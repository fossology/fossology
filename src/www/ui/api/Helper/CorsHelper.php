<?php
/*
 SPDX-FileCopyrightText: © 2023 Siemens AG
 SPDX-FileContributor: Gaurav Mishra <mishra.gaurav@siemens.com>

 SPDX-License-Identifier: GPL-2.0-only
 */

namespace Fossology\UI\Api\Helper;

use Psr\Http\Message\ResponseInterface;

class CorsHelper
{
  /**
   * Add CORS headers to the response
   *
   * @param ResponseInterface $response
   * @return ResponseInterface
   */
  public static function addCorsHeaders(ResponseInterface $response): ResponseInterface
  {
    global $SysConf;
    return $response
      ->withHeader('Access-Control-Allow-Origin', $SysConf['SYSCONFIG']['CorsOrigins'])
      ->withHeader('Access-Control-Expose-Headers', 'Look-at, X-Total-Pages, Retry-After')
      ->withHeader('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, Accept, Origin, Authorization, action, accesslevel, active, copyright, Content-Type, description, filename, filesizemax, filesizemin, folderDescription, folderId, folderName, groupName, ignoreScm, applyGlobal, license, limit, name, page, parent, parentFolder, public, reportFormat, searchType, tag, upload, uploadDescription, uploadId, uploadType, excludefolder')
      ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, PATCH, OPTIONS')
      ->withHeader('Access-Control-Allow-Credentials', 'true');
  }
}
