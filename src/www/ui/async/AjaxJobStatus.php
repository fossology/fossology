<?php
/*
 SPDX-FileCopyrightText: © 2015 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

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
