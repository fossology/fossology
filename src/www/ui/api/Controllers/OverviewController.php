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

use Fossology\Lib\Auth\Auth;
use Fossology\UI\Api\Models\Info;
use Fossology\UI\Api\Models\InfoType;

/**
 * @class OverviewController
 * @brief Controller for OverviewController model
 */
class OverviewController extends RestController
{
  public function getDatabaseContents($request, $response, $args)
  {
    $dashboardPlugin = $this->restHelper->getPlugin('dashboard');
    if (!Auth::isAdmin()) {
      $error = new Info(403, "Only admin can view database contents.", InfoType::ERROR);
      return $response->withJson($error->getArray(), $error->getCode());
    }
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
}
