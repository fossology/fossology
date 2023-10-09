<?php
/*
 SPDX-FileCopyrightText: © 2023 Samuel Dushimimana <dushsam100@gmail.com>

 SPDX-License-Identifier: GPL-2.0-only
*/

/**
 * @file
 * @brief Controller for dashboard overview queries
 */

namespace Fossology\UI\Api\Controllers;

use Fossology\UI\Api\Exceptions\HttpForbiddenException;
use Fossology\UI\Api\Helper\ResponseHelper;
use Psr\Http\Message\ServerRequestInterface;

/**
 * @class OverviewController
 * @brief Controller for OverviewController model
 */
class OverviewController extends RestController
{
  /**
   * @throws HttpForbiddenException
   */
  public function getDatabaseContents($request, $response, $args)
  {
    $this->throwNotAdminException();
    /** @var \dashboard $dashboardPlugin */
    $dashboardPlugin = $this->restHelper->getPlugin('dashboard');
    $res = [
      /**** Users ****/
      $dashboardPlugin->DatabaseContentsRow("users", _("Users"), true),
      /**** Uploads  ****/
      $dashboardPlugin->DatabaseContentsRow("upload", _("Uploads"), true),
      /**** Unique pfiles  ****/
      $dashboardPlugin->DatabaseContentsRow("pfile", _("Unique files referenced in repository"), true),
      /**** uploadtree recs  ****/
      $dashboardPlugin->DatabaseContentsRow("uploadtree_%", _("Individual Files"), true),
      /**** License recs  ****/
      $dashboardPlugin->DatabaseContentsRow("license_file", _("Discovered Licenses"), true),
      /**** Copyright recs  ****/
      $dashboardPlugin->DatabaseContentsRow("copyright", _("Copyrights/URLs/Emails"), true)
    ];
    return $response->withJson($res, 200);
  }

  /**
   * Get disk space usage overview
   *
   * @param ServerRequestInterface $request
   * @param ResponseHelper $response
   * @param array $args
   * @return ResponseHelper
   * @throws HttpForbiddenException
   */
  public function getDiskSpaceUsage($request, $response, $args)
  {
    $this->throwNotAdminException();
    /** @var \dashboard $dashboardPlugin */
    $dashboardPlugin = $this->restHelper->getPlugin('dashboard');
    $res = $dashboardPlugin->DiskFree(true);
    return $response->withJson($res, 200);
  }

  /**
   * Get PHP info overview
   *
   * @param ServerRequestInterface $request
   * @param ResponseHelper $response
   * @param array $args
   * @return ResponseHelper
   * @throws HttpForbiddenException
   */
  public function getPhpInfo($request, $response, $args)
  {
    $this->throwNotAdminException();
    /** @var \dashboard $dashboardPlugin */
    $dashboardPlugin = $this->restHelper->getPlugin('dashboard');
    $res = $dashboardPlugin->GetPHPInfoTable(true);
    return $response->withJson($res, 200);
  }

  /**
   * Get the database for the dashboard overview
   *
   * @param ServerRequestInterface $request
   * @param ResponseHelper $response
   * @param array $args
   * @return ResponseHelper
   * @throws HttpForbiddenException
   */
  public function getDatabaseMetrics($request, $response, $args)
  {
    $this->throwNotAdminException();
    /** @var \dashboard $dashboardPlugin */
    $dashboardPlugin = $this->restHelper->getPlugin('dashboard');
    $res = $dashboardPlugin->DatabaseMetrics(true);
    return $response->withJson($res, 200);
  }

  /**
   * Get active queries
   *
   * @param ServerRequestInterface $request
   * @param ResponseHelper $response
   * @param array $args
   * @return ResponseHelper
   * @throws HttpForbiddenException
   */
  public function getActiveQueries($request, $response, $args)
  {
    $this->throwNotAdminException();
    /** @var \dashboard $dashboardPlugin */
    $dashboardPlugin = $this->restHelper->getPlugin('dashboard');
    global $PG_CONN;
    $dashboardPlugin->pgVersion = pg_version($PG_CONN);
    $res = $dashboardPlugin->DatabaseQueries(true);

    foreach ($res as &$value) {
      $value['pid'] = intval($value['pid']);
    }
    return $response->withJson($res, 200);
  }
}
