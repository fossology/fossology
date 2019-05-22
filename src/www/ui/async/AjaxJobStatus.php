<?php
/***********************************************************
 * Copyright (C) 2015 Siemens AG
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * version 2 as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 ***********************************************************/

namespace Fossology\UI\Ajax;

use Fossology\Lib\Db\DbManager;
use Fossology\Lib\Plugin\DefaultPlugin;
use Fossology\Lib\Auth\Auth;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;

class AjaxJobStatus extends DefaultPlugin
{
  const NAME = "jobstatus";
  /** @var DbManager */
  private $dbManager;

  function __construct()
  {
    parent::__construct(self::NAME, array(
        self::PERMISSION => Auth::PERM_READ
    ));
    $this->dbManager = $this->getObject('db.manager');
  }

  /**
   * @brief : returns 1 when jobs are running else 0
   * @param Request $request
   * @return Response
   */
  protected function handle(Request $request)
  {
    $response = '1';
    $jobInfo = $this->dbManager->getSingleRow(
      "SELECT jq_end_bits FROM jobqueue WHERE jq_end_bits = '0' LIMIT 1;");
    if (empty($jobInfo)) {
      $response = '0';
    }
    $status = 1;
    ReportCachePurgeAll();
    $status = empty($status) ? JsonResponse::HTTP_INTERNAL_SERVER_ERROR : JsonResponse::HTTP_OK;
    return new JsonResponse(array("status" => $response), $status);
  }
}

register_plugin(new AjaxJobStatus());
