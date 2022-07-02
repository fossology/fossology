<?php
/*
 SPDX-FileCopyrightText: © 2014-2016 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

use Fossology\Lib\Auth\Auth;
use Fossology\Lib\Dao\FolderDao;
use Fossology\Lib\Dao\UploadDao;
use Fossology\Lib\Data\Upload\Upload;
use Fossology\Lib\Plugin\DefaultPlugin;
use Symfony\Component\HttpFoundation\Request;

class ReportImportPlugin extends DefaultPlugin
{
  const NAME = 'ui_reportImport';

  /** @var UploadDao */
  private $uploadDao;
  /** @var FolderDao */
  private $folderDao;

  function __construct()
  {
    parent::__construct(self::NAME, array(
      self::TITLE => _("Report Import"),
      self::PERMISSION => Auth::PERM_WRITE,
      self::REQUIRES_LOGIN => TRUE
    ));
    $this->uploadDao = $GLOBALS['container']->get('dao.upload');
    $this->folderDao = $GLOBALS['container']->get('dao.folder');
  }

  function preInstall()
  {
    $text = _("Import Report");
    menu_insert("Browse-Pfile::Import&nbsp;Report", 0, self::NAME, $text);
    menu_insert("Main::Upload::Import&nbsp;Report", 0, self::NAME, $text);
  }

  protected function handle(Request $request)
  {
    $uploadId = intval(GetArrayVal("uploadselect", $_POST));
    if (empty($uploadId) ||
        !array_key_exists('report',$_FILES) ||
        sizeof($_FILES['report']['name']) != 1)
    {
      return $this->showUiToChoose();
    }
    else
    {
      $jobMetaData = $this->runImport($uploadId, $_FILES['report'], $request);
      $showJobsPlugin = \plugin_find('showjobs');
      $showJobsPlugin->OutputOpen();
      return $showJobsPlugin->getResponse();
    }
  }

  protected function showUiToChoose()
  {
    $vars=array();
    $groupId = Auth::getGroupId();
    $vars['userIsAdmin'] = Auth::isAdmin();

    $rootFolder = $this->folderDao->getRootFolder(Auth::getUserId());
    $folder_pk = GetParm('folder', PARM_INTEGER);
    if (empty($folder_pk)) {
      $folder_pk = $rootFolder->getId();
    }
    $vars['folderId'] = $folder_pk;

    $folderUploads = $this->folderDao->getFolderUploads($folder_pk, $groupId);
    $uploadsById = array();
    /* @var $uploadProgress UploadProgress */
    foreach ($folderUploads as $uploadProgress)
    {
      if ($uploadProgress->getGroupId() != $groupId) {
        continue;
      }
      if (!$this->uploadDao->isEditable($uploadProgress->getId(), $groupId)) {
        continue;
      }
      $display = $uploadProgress->getFilename() . _(" from ") . Convert2BrowserTime(date("Y-m-d H:i:s",$uploadProgress->getTimestamp()));
      $uploadsById[$uploadProgress->getId()] = $display;
    }
    $vars['uploadList'] = $uploadsById;

    $uploadId = GetParm('upload', PARM_INTEGER);
    if (empty($uploadId))
    {
      reset($uploadsById);
      $uploadId = key($uploadsById);
    }
    $vars['uploadId'] = $uploadId;

    $folderStructure = $this->folderDao->getFolderStructure($rootFolder->getId());
    $vars['folderStructure'] = $folderStructure;
    $vars['baseUri'] = $Uri = Traceback_uri() . "?mod=" . self::NAME . "&folder=";

    return $this->render('ReportImportPlugin.html.twig', $this->mergeWithDefault($vars));
  }

  protected function runImport($uploadId, $report, $request)
  {
    $reportImportAgent = plugin_find('agent_reportImport');

    $jqCmdArgs = $reportImportAgent->addReport($report);
    $jqCmdArgs .= $reportImportAgent->setAdditionalJqCmdArgs($request);

    $userId = Auth::getUserId();
    $groupId = Auth::getGroupId();
    $dbManager = $this->getObject('db.manager');
    $sql = 'SELECT jq_pk,job_pk FROM jobqueue, job '
         . 'WHERE jq_job_fk=job_pk AND jq_type=$1 AND job_group_fk=$4 AND job_user_fk=$3 AND jq_args=$2 AND jq_endtime IS NULL';
    $params = array($reportImportAgent->AgentName,$uploadId,$userId,$groupId);
    $statementName = __METHOD__;
    if ($jqCmdArgs) {
      $sql .= ' AND jq_cmd_args=$5';
      $params[] = $jqCmdArgs;
      $statementName .= '.args';
    }
    else {
      $sql .= ' AND jq_cmd_args IS NULL';
    }

    $scheduled = $dbManager->getSingleRow($sql,$params,$statementName);
    if (!empty($scheduled)) {
      return array($scheduled['job_pk'],$scheduled['jq_pk']);
    }

    $upload = $this->getUpload($uploadId, $groupId);
    $jobId = JobAddJob($userId, $groupId, $upload->getFilename(), $uploadId);
    $error = "";
    $jobQueueId = $reportImportAgent->AgentAdd($jobId, $uploadId, $error, array(), $jqCmdArgs);
    if ($jobQueueId<0)
    {
      throw new Exception(_("Cannot schedule").": ".$error);
    }
    return array($jobId,$jobQueueId);
  }

  protected function getUpload($uploadId, $groupId)
  {
    if ($uploadId <=0)
    {
      throw new Exception(_("parameter error: $uploadId"));
    }
    if (!$this->uploadDao->isAccessible($uploadId, $groupId))
    {
      throw new Exception(_("permission denied"));
    }
    /** @var Upload */
    $upload = $this->uploadDao->getUpload($uploadId);
    if ($upload === null)
    {
      throw new Exception(_('cannot find uploadId'));
    }
    return $upload;
  }
}

register_plugin(new ReportImportPlugin());
