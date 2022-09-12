<?php
/*
 SPDX-FileCopyrightText: © 2014-2018 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

/**
 * @file
 * @brief UI plugin for ReadMeOSS agent
 */

use Fossology\Lib\Auth\Auth;
use Fossology\Lib\Dao\FolderDao;
use Fossology\Lib\Dao\UploadDao;
use Fossology\Lib\Data\Upload\Upload;
use Fossology\Lib\Plugin\DefaultPlugin;
use Symfony\Component\HttpFoundation\Request;

use Fossology\Lib\Dao\ProjectDao;

/**
 * @class ReadMeOssPlugin
 * @brief Agent plugin for Readme_OSS agent
 */
class ReadMeOssPlugin extends DefaultPlugin
{
  const NAME = 'ui_readmeoss';        ///< Mod name for the plugin

  function __construct()
  {
    parent::__construct(self::NAME, array(
        self::TITLE => _("ReadME_OSS generation"),
        self::PERMISSION => Auth::PERM_WRITE,
        self::REQUIRES_LOGIN => TRUE
    ));
  }

  /**
   * @copydoc Fossology::Lib::Plugin::DefaultPlugin::preInstall()
   * @see Fossology::Lib::Plugin::DefaultPlugin::preInstall()
   */
  function preInstall()
  {
    $text = _("Generate ReadMe_OSS");
    menu_insert("Browse-Pfile::Export&nbsp;ReadMe_OSS", 0, self::NAME, $text);

    menu_insert("UploadMulti::Generate&nbsp;ReadMe_OSS", 0, self::NAME, $text);
  }

  /**
   * @copydoc Fossology::Lib::Plugin::DefaultPlugin::handle()
   * @see Fossology::Lib::Plugin::DefaultPlugin::handle()
   */
  protected function handle(Request $request)
  {
    $groupId = Auth::getGroupId();
    $uploadIds = $request->get('uploads') ?: array();
    $uploadIds[] = intval($request->get('upload'));
    $addUploads = array();
    foreach ($uploadIds as $uploadId) {
      if (empty($uploadId)) {
        continue;
      }
      try {
        $addUploads[$uploadId] = $this->getUpload($uploadId, $groupId);
      } catch(Exception $e) {
        return $this->flushContent($e->getMessage());
      }
    }

    $folderId = $request->get('folder');

    echo("<script>console.log('folderId');</script>");
    echo("<script>console.log('".json_encode($folderId)."');</script>");

    if (!empty($folderId)) {
      /* @var $folderDao FolderDao */
      $folderDao = $this->getObject('dao.folder');
      $folderUploads = $folderDao->getFolderUploads($folderId, $groupId);
      foreach ($folderUploads as $uploadProgress) {
        $addUploads[$uploadProgress->getId()] = $uploadProgress;
      }
    }

    $pojectId = $request->get('poject');

    echo("<script>console.log('pojectId');</script>");
    echo("<script>console.log('".json_encode($pojectId)."');</script>");

    if (!empty($pojectId)) {
      /* @var $pojectDao PojectDao */
      $pojectDao = $this->getObject('dao.poject');
      $pojectUploads = $pojectDao->getPojectUploads($pojectId, $groupId);
      foreach ($pojectUploads as $uploadProgress) {
        $addUploads[$uploadProgress->getId()] = $uploadProgress;
      }
    }

    

    if (empty($addUploads)) {
      return $this->flushContent(_('No upload selected'));
    }
    $upload = array_pop($addUploads);
    try {
      // list($jobId,$jobQueueId) = $this->getJobAndJobqueue($groupId, $upload, $addUploads);
      if (!empty($folderId)) {
        list($jobId,$jobQueueId) = $this->getJobAndJobqueueWithType($groupId, $upload, $addUploads, "folder");
      } else if (!empty($pojectId)) {
        list($jobId,$jobQueueId) = $this->getJobAndJobqueueWithType($groupId, $upload, $addUploads, "project");
      } else {
        list($jobId,$jobQueueId) = $this->getJobAndJobqueue($groupId, $upload, $addUploads);
      }
      
    } catch (Exception $ex) {
      return $this->flushContent($ex->getMessage());
    }

    $vars = array('jqPk' => $jobQueueId,
                  'downloadLink' => Traceback_uri(). "?mod=download&report=".$jobId,
                  'reportType' => "ReadMe_OSS");
    $text = sprintf(_("Generating ReadMe_OSS for '%s'"), $upload->getFilename());
    $vars['content'] = "<h2>".$text."</h2>";
    $content = $this->renderer->load("report.html.twig")->render($vars);
    $message = '<h3 id="jobResult"></h3>';
    $request->duplicate(array('injectedMessage'=>$message,'injectedFoot'=>$content,'mod'=>'showjobs'))->overrideGlobals();
    $showJobsPlugin = \plugin_find('showjobs');
    $showJobsPlugin->OutputOpen();
    return $showJobsPlugin->getResponse();
  }

  /**
   * @brief Get parameters from job queue and schedule them
   * @param int $groupId
   * @param int $upload
   * @param int $addUploads
   * @throws Exception
   * @return int Array of job id and job queue id
   */
  protected function getJobAndJobqueue($groupId, $upload, $addUploads)
  {

    echo("<script>console.log('getJobAndJobqueue begin');</script>");
    echo("<script>console.log('addUploads');</script>");
    echo("<script>console.log('".json_encode($addUploads)."');</script>");


    $uploadId = $upload->getId();
    $readMeOssAgent = plugin_find('agent_readmeoss');
    $userId = Auth::getUserId();
    $jqCmdArgs = $readMeOssAgent->uploadsAdd($addUploads);

    echo("<script>console.log('jqCmdArgs');</script>");
    echo("<script>console.log('".json_encode($jqCmdArgs)."');</script>");

    $dbManager = $this->getObject('db.manager');
    $sql = 'SELECT jq_pk,job_pk FROM jobqueue, job '
         . 'WHERE jq_job_fk=job_pk AND jq_type=$1 AND job_group_fk=$4 AND job_user_fk=$3 AND jq_args=$2 AND jq_endtime IS NULL';
    $params = array($readMeOssAgent->AgentName,$uploadId,$userId,$groupId);
    $log = __METHOD__;
    if ($jqCmdArgs) {
      $sql .= ' AND jq_cmd_args=$5';
      $params[] = $jqCmdArgs;
      $log .= '.args';
    } else {
      $sql .= ' AND jq_cmd_args IS NULL';
    }

    echo("<script>console.log('params');</script>");
    echo("<script>console.log('".json_encode($params)."');</script>");

    $scheduled = $dbManager->getSingleRow($sql,$params,$log);
    if (!empty($scheduled)) {
      return array($scheduled['job_pk'],$scheduled['jq_pk']);
    }
    if (empty($jqCmdArgs)) {
      $jobName = $upload->getFilename();
    } else {
      $jobName = "Multi File ReadmeOSS";
    }
    $jobId = JobAddJob($userId, $groupId, $jobName, $uploadId);
    $error = "";
    $jobQueueId = $readMeOssAgent->AgentAdd($jobId, $uploadId, $error, array(), $jqCmdArgs);
    if ($jobQueueId < 0) {
      throw new Exception(_("Cannot schedule").": ".$error);
    }
    return array($jobId, $jobQueueId, $error);
  }

    /**
   * @brief Get parameters from job queue and schedule them
   * @param int $groupId
   * @param int $upload
   * @param int $addUploads
   * @param String type
   * @throws Exception
   * @return int Array of job id and job queue id
   */
  protected function getJobAndJobqueueWithType($groupId, $upload, $addUploads, $type)
  {

    echo("<script>console.log('getJobAndJobqueue begin');</script>");
    echo("<script>console.log('addUploads');</script>");
    echo("<script>console.log('".json_encode($addUploads)."');</script>");


    $uploadId = $upload->getId();
    $readMeOssAgent = plugin_find('agent_readmeoss');
    $userId = Auth::getUserId();
    $jqCmdArgs = $readMeOssAgent->uploadsAddWithType($addUploads, $type);

    echo("<script>console.log('jqCmdArgs');</script>");
    echo("<script>console.log('".json_encode($jqCmdArgs)."');</script>");

    $dbManager = $this->getObject('db.manager');
    $sql = 'SELECT jq_pk,job_pk FROM jobqueue, job '
         . 'WHERE jq_job_fk=job_pk AND jq_type=$1 AND job_group_fk=$4 AND job_user_fk=$3 AND jq_args=$2 AND jq_endtime IS NULL';
    $params = array($readMeOssAgent->AgentName,$uploadId,$userId,$groupId);
    $log = __METHOD__;
    if ($jqCmdArgs) {
      $sql .= ' AND jq_cmd_args=$5';
      $params[] = $jqCmdArgs;
      $log .= '.args';
    } else {
      $sql .= ' AND jq_cmd_args IS NULL';
    }

    echo("<script>console.log('params');</script>");
    echo("<script>console.log('".json_encode($params)."');</script>");

    $scheduled = $dbManager->getSingleRow($sql,$params,$log);
    if (!empty($scheduled)) {
      return array($scheduled['job_pk'],$scheduled['jq_pk']);
    }
    if (empty($jqCmdArgs)) {
      $jobName = $upload->getFilename();
    } else {
      $jobName = "Multi File ReadmeOSS";
    }
    $jobId = JobAddJob($userId, $groupId, $jobName, $uploadId);
    $error = "";
    $jobQueueId = $readMeOssAgent->AgentAdd($jobId, $uploadId, $error, array(), $jqCmdArgs);
    if ($jobQueueId < 0) {
      throw new Exception(_("Cannot schedule").": ".$error);
    }
    return array($jobId, $jobQueueId, $error);
  }

  /**
   * @brief Get upload object for a given id
   * @param int $uploadId
   * @param int $groupId
   * @throws Exception
   * @return Fossology::Lib::Data::Upload::Upload Upload object or null
   * on failure
   */
  protected function getUpload($uploadId, $groupId)
  {
    if ($uploadId <= 0) {
      throw new Exception(_("parameter error: $uploadId"));
    }
    /* @var $uploadDao UploadDao */
    $uploadDao = $this->getObject('dao.upload');
    if (!$uploadDao->isAccessible($uploadId, $groupId)) {
      throw new Exception(_("permission denied"));
    }
    /** @var Upload */
    $upload = $uploadDao->getUpload($uploadId);
    if ($upload === null) {
      throw new Exception(_('cannot find uploadId'));
    }
    return $upload;
  }

  /**
   * Schedules readme OSS agent to generate report
   *
   * @param int $groupId
   * @param Upload $upload
   * @param array $addUploads
   * @return array|number[] Job id and job queue id
   * @throws Exception
   */
  public function scheduleAgent($groupId, $upload, $addUploads = array())
  {
    return $this->getJobAndJobqueue($groupId, $upload, $addUploads);
  }
}

register_plugin(new ReadMeOssPlugin());
