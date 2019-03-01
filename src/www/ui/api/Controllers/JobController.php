<?php
/***************************************************************
 Copyright (C) 2018 Siemens AG
 Author: Gaurav Mishra <mishra.gaurav@siemens.com>

 This program is free software; you can redistribute it and/or
 modify it under the terms of the GNU General Public License
 version 2 as published by the Free Software Foundation.

 This program is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 GNU General Public License for more details.

 You should have received a copy of the GNU General Public License along
 with this program; if not, write to the Free Software Foundation, Inc.,
 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 ***************************************************************/
/**
 * @file
 * @brief Controller for job queries
 */

namespace Fossology\UI\Api\Controllers;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Fossology\UI\Api\Models\Info;
use Fossology\UI\Api\Models\InfoType;
use Fossology\UI\Api\Models\Analysis;
use Fossology\UI\Api\Models\Decider;
use Fossology\UI\Api\Models\Reuser;
use Fossology\UI\Api\Models\ScanOptions;

/**
 * @class JobController
 * @brief Controller for Job model
 */
class JobController extends RestController
{
  /**
   * Get all jobs by a user
   *
   * @param ServerRequestInterface $request
   * @param ResponseInterface $response
   * @param array $args
   * @return ResponseInterface
   */
  public function getJobs($request, $response, $args)
  {
    $limit = 0;
    if ($request->hasHeader('limit')) {
      $limit = $request->getHeaderLine('limit');
      if (isset($limit) && (! is_numeric($limit) || $limit < 0)) {
        $returnVal = new Info(400,
          "Limit cannot be smaller than 1 and has to be numeric!",
          InfoType::ERROR);
        return $response->withJson($returnVal->getArray(), $returnVal->getCode());
      }
    }
    $id = null;
    if (isset($args['id'])) {
      $id = intval($args['id']);
      if (! $this->dbHelper->doesIdExist("job", "job_pk", $id)) {
        $returnVal = new Info(404, "Job id " . $id . " doesn't exist",
          InfoType::ERROR);
        return $response->withJson($returnVal->getArray(), $returnVal->getCode());
      }
    }
    $jobs = $this->dbHelper->getJobs($limit, $id);
    if ($id !== null) {
      $jobs = $jobs[0];
    }
    return $response->withJson($jobs, 200);
  }

  /**
   * Create a new job
   *
   * @param ServerRequestInterface $request
   * @param ResponseInterface $response
   * @param array $args
   * @return ResponseInterface
   */
  public function createJob($request, $response, $args)
  {
    $folder = $request->getHeaderLine("folderId");
    $upload = $request->getHeaderLine("uploadId");
    if (is_numeric($folder) && is_numeric($upload) && $folder > 0 && $upload > 0) {
      $scanOptionsJSON = $request->getParsedBody();

      $analysis = new Analysis();
      if (array_key_exists("analysis", $scanOptionsJSON)) {
        $analysis->setUsingArray($scanOptionsJSON["analysis"]);
      }
      $decider = new Decider();
      if (array_key_exists("decider", $scanOptionsJSON)) {
        $decider->setUsingArray($scanOptionsJSON["decider"]);
      }
      $reuser = new Reuser(0, 0, false, false);
      try {
        if (array_key_exists("reuse", $scanOptionsJSON)) {
          $reuser->setUsingArray($scanOptionsJSON["reuse"]);
        }
      } catch (\UnexpectedValueException $e) {
        $error = new Info($e->getCode(), $e->getMessage(), InfoType::ERROR);
        return $response->withJson($error->getArray(), $error->getCode());
      }

      $scanOptions = new ScanOptions($analysis, $reuser, $decider);
      $info = $scanOptions->scheduleAgents($folder, $upload);
      return $response->withJson($info->getArray(), $info->getCode());
    } else {
      $error = new Info(400, "Folder id and upload id should be integers!", InfoType::ERROR);
      return $response->withJson($error->getArray(), $error->getCode());
    }
  }
}
