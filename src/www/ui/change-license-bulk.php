<?php
/***********************************************************
 * Copyright (C) 2014-2015,2018 Siemens AG
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

use Fossology\DeciderJob\UI\DeciderJobAgentPlugin;
use Fossology\Lib\Auth\Auth;
use Fossology\Lib\Dao\LicenseDao;
use Fossology\Lib\Dao\UploadDao;
use Fossology\Lib\Db\DbManager;
use Fossology\Lib\Plugin\DefaultPlugin;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

class ChangeLicenseBulk extends DefaultPlugin
{
  const NAME = "change-license-bulk";
  /** @var LicenseDao */
  private $licenseDao;
  /** @var DbManager */
  private $dbManager;
  /** @var UploadDao */
  private $uploadDao;

  function __construct()
  {
    parent::__construct(self::NAME, array(
        self::TITLE => _("Private: schedule a bulk scan from post"),
        self::PERMISSION => Auth::PERM_WRITE
    ));

    $this->dbManager = $this->getObject('db.manager');
    $this->licenseDao = $this->getObject('dao.license');
    $this->uploadDao = $this->getObject('dao.upload');
  }

  /**
   * @param Request $request
   * @return Response
   */
  protected function handle(Request $request)
  {
    $uploadTreeId = intval($request->get('uploadTreeId'));
    if ($uploadTreeId <= 0)
    {
      return new JsonResponse(array("error" => 'bad request'), JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
    }

    try
    {
      $jobQueueId = $this->getJobQueueId($uploadTreeId, $request);
    } catch (Exception $ex)
    {
      $errorMsg = $ex->getMessage();
      return new JsonResponse(array("error" => $errorMsg), JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
    }
    ReportCachePurgeAll();
    
    return new JsonResponse(array("jqid" => $jobQueueId));
  }

  /**
   * 
   * @param int $uploadTreeId
   * @param Request $request
   * @return int $jobQueueId
   */
  private function getJobQueueId($uploadTreeId, Request $request)
  {
    $uploadEntry = $this->uploadDao->getUploadEntry($uploadTreeId);
    $uploadId = intval($uploadEntry['upload_fk']);
    $userId = Auth::getUserId();
    $groupId = Auth::getGroupId();
    
    if ($uploadId <= 0 || !$this->uploadDao->isAccessible($uploadId, $groupId))
    {
      throw new Exception('permission denied');
    }

    $bulkScope = $request->get('bulkScope');
    switch ($bulkScope)
    {
      case 'u':
        $uploadTreeTable = $this->uploadDao->getUploadtreeTableName($uploadId);
        $topBounds = $this->uploadDao->getParentItemBounds($uploadId, $uploadTreeTable);
        $uploadTreeId = $topBounds->getItemId();
        break;

      case 'f':
        if (!Isdir($uploadEntry['ufile_mode']) && !Iscontainer($uploadEntry['ufile_mode']) && !Isartifact($uploadEntry['ufile_mode']))
        {
          $uploadTreeId = $uploadEntry['parent'] ?: $uploadTreeId;
        }
        break;

      default:
        throw new InvalidArgumentException('bad scope request');
    }

    $refText = $request->get('refText');
    $actions = $request->get('bulkAction');

    $licenseRemovals = array();
    foreach($actions as $licenseAction)
    {
      $licenseRemovals[$licenseAction['licenseId']] = array(($licenseAction['action']=='Remove'), $licenseAction['comment'], $licenseAction['reportinfo'], $licenseAction['acknowledgement']);
    }
    $bulkId = $this->licenseDao->insertBulkLicense($userId, $groupId, $uploadTreeId, $licenseRemovals, $refText);

    if ($bulkId <= 0)
    {
      throw new Exception('cannot insert bulk reference');
    }
    $upload = $this->uploadDao->getUpload($uploadId);
    $uploadName = $upload->getFilename();
    $job_pk = JobAddJob($userId, $groupId, $uploadName, $uploadId);
    /** @var DeciderJobAgentPlugin $deciderPlugin */
    $deciderPlugin = plugin_find("agent_deciderjob");
    $dependecies = array(array('name' => 'agent_monk_bulk', 'args' => $bulkId));
    $conflictStrategyId = intval($request->get('forceDecision'));
    $errorMsg = '';
    $jqId = $deciderPlugin->AgentAdd($job_pk, $uploadId, $errorMsg, $dependecies, $conflictStrategyId);

    if (!empty($errorMsg))
    {
      throw new Exception(str_replace('<br>', "\n", $errorMsg));
    }
    return $jqId;
  }
}

register_plugin(new ChangeLicenseBulk());
