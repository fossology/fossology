<?php
/***********************************************************
 * Copyright (C) 2020 Siemens AG
 * Author: Gaurav Mishra <mishra.gaurav@siemens.com>
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

use Fossology\Lib\Auth\Auth;
use Fossology\Lib\Db\DbManager;
use Fossology\Lib\Plugin\DefaultPlugin;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Fossology\Lib\Dao\ShowJobsDao;

/**
 * @class AjaxAllJobStatus
 * Provides data for AllJobStatus page
 */
class AjaxAllJobStatus extends DefaultPlugin
{

  /** @var string NAME
   * Mod name */
  const NAME = "ajax_all_job_status";

  /** @var DbManager $dbManager
   * DB manager in use */
  private $dbManager;

  /** @var ShowJobsDao $showJobDao
   * Dao to fetch job info */
  private $showJobDao;

  function __construct()
  {
    parent::__construct(self::NAME,
      array(
        self::PERMISSION => Auth::PERM_READ,
        self::REQUIRES_LOGIN => false
      ));

    $this->dbManager = $this->getObject('db.manager');
    $this->showJobDao = $this->getObject('dao.show_jobs');
  }

  /**
   * @param Request $request
   * @return Response
   */
  protected function handle(Request $request)
  {
    $results = $this->showJobDao->getJobsForAll();
    $uniqueTypes = array_unique(array_column($results, 'job'));
    $data = array();

    foreach ($uniqueTypes as $type) {
      $data[$type] = [];
      $data[$type]['running'] = 0;
      $data[$type]['pending'] = 0;
      $data[$type]['eta'] = 0;
      foreach ($results as $row) {
        if ($row['job'] != $type || empty($row['status'])) {
          continue;
        }
        $data[$type][$row['status']] ++;
        $newEta = $this->showJobDao->getEstimatedTime($row['jq_job_fk'],
          $row['job'], 0, $row['upload_fk'], 1);
        if (! empty($newEta)) {
          $data[$type]['eta'] = ($newEta > $data[$type]['eta']) ? $newEta:$data[$type]['eta'];
        }
      }
    }

    $returnData = array();
    foreach ($data as $agent => $row) {
      $dataRow = [
        "name" => $agent,
        "running" => $row["running"],
        "pending" => $row["pending"]
      ];
      if ($row['eta'] == 0) {
        $dataRow['eta'] = "N/A";
      } else {
        $dataRow['eta'] = intval($row["eta"] / 3600) .
          gmdate(":i:s", $row["eta"]);
      }
      $returnData[] = $dataRow;
    }
    $output = "";
    $error_msg = "";
    $schedStatus = "Running";
    if (! fo_communicate_with_scheduler("status", $output, $error_msg)
      && strstr($error_msg, "Connection refused") !== false) {
      $schedStatus = "Stopped";
    }
    return new JsonResponse(["data" => $returnData, "scheduler" => $schedStatus]);
  }
}

register_plugin(new AjaxAllJobStatus());
