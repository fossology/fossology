<?php
/*
 SPDX-FileCopyrightText: © 2015-2018 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\Spdx\UI;

use Fossology\Lib\Auth\Auth;
use Fossology\Lib\Dao\UploadDao;
use Fossology\Lib\Data\Upload\Upload;
use Fossology\Lib\Plugin\DefaultPlugin;
use Symfony\Component\HttpFoundation\Request;

/**
 * @class SpdxThreeGeneratorUi
 * @brief Call SPDX3 agent to generate report from UI
 */
class SpdxThreeGeneratorUi extends DefaultPlugin
{
  const NAME = 'ui_spdx3';                ///< Mod name of the plugin
  const DEFAULT_OUTPUT_FORMAT = "spdx3jsonld";  ///< Default report format
  /** @var string $outputFormat
   * Report format in use
   */
  protected $outputFormat = self::DEFAULT_OUTPUT_FORMAT;

  function __construct()
  {
    $possibleOutputFormat = trim(GetParm("outputFormat",PARM_STRING));
    if (strcmp($possibleOutputFormat,"") !== 0 &&
        strcmp($possibleOutputFormat,self::DEFAULT_OUTPUT_FORMAT) !== 0 &&
        ctype_alnum($possibleOutputFormat)) {
      $this->outputFormat = $possibleOutputFormat;
    }
    parent::__construct(self::NAME, array(
        self::TITLE => _("SPDX3 generation"),
        self::PERMISSION => Auth::PERM_WRITE,
        self::REQUIRES_LOGIN => true
    ));
  }

  /**
   * @copydoc Fossology::Lib::Plugin::DefaultPlugin::preInstall()
   * @see Fossology::Lib::Plugin::DefaultPlugin::preInstall()
   */
  function preInstall()
  {
    $text = _("Generate SPDX3.0 report in JSON-LD format");
    menu_insert("Browse-Pfile::Export&nbsp;SPDX3.0&nbsp;JSON-LD&nbsp;report", 0, self::NAME . '&outputFormat=spdx3jsonld', $text);
    menu_insert("UploadMulti::Generate&nbsp;SPDX3.0", 0, self::NAME, $text);

    $text = _("Generate SPDX3.0 report in JSON format");
    menu_insert("Browse-Pfile::Export&nbsp;SPDX3.0&nbsp;JSON&nbsp;report", 0, self::NAME . '&outputFormat=spdx3json', $text);

    $text = _("Generate SPDX3.0 report in RDF format");
    menu_insert("Browse-Pfile::Export&nbsp;SPDX3.0&nbsp;RDF&nbsp;report", 0, self::NAME . '&outputFormat=spdx3rdf', $text);

    $text = _("Generate SPDX3.0 report in tag/value format");
    menu_insert("Browse-Pfile::Export&nbsp;SPDX3.0&nbsp;tag/value&nbsp;report", 0, self::NAME . '&outputFormat=spdx3tv', $text);
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
    if (!empty($folderId)) {
      /* @var $folderDao FolderDao */
      $folderDao = $this->getObject('dao.folder');
      $folderUploads = $folderDao->getFolderUploads($folderId, $groupId);
      foreach ($folderUploads as $uploadProgress) {
        $addUploads[$uploadProgress->getId()] = $uploadProgress;
      }
    }
    if (empty($addUploads)) {
      return $this->flushContent(_('No upload selected'));
    }
    $upload = array_pop($addUploads);
    try {
      list($jobId,$jobQueueId) = $this->getJobAndJobqueue($groupId, $upload, $addUploads);
    } catch (\Exception $ex) {
      return $this->flushContent($ex->getMessage());
    }

    $vars = array('jqPk' => $jobQueueId,
                  'downloadLink' => Traceback_uri(). "?mod=download&report=".$jobId,
                  'reportType' => $this->outputFormat);
    $text = sprintf(_("Generating ". $this->outputFormat . " report for '%s'"), $upload->getFilename());
    $vars['content'] = "<h2>".$text."</h2>";
    $content = $this->renderer->load("report.html.twig")->render($vars);
    $message = '<h3 id="jobResult"></h3>';
    $request->duplicate(array('injectedMessage'=>$message,'injectedFoot'=>$content,'mod'=>'showjobs'))->overrideGlobals();
    $showJobsPlugin = \plugin_find('showjobs');
    $showJobsPlugin->OutputOpen();
    return $showJobsPlugin->getResponse();
  }

  /**
   * @brief Add multiple uploads to the report
   * @param array $uploads List of upload IDs
   * @return string
   */
  protected function uploadsAdd($uploads)
  {
    if (count($uploads) == 0) {
      return '';
    }
    return '--uploadsAdd='. implode(',', array_keys($uploads));
  }

  /**
   * @brief Get the Job ID and Job queue ID
   * @param int $groupId
   * @param Upload $upload
   * @param array $addUploads
   * @throws Exception
   * @return array JobID, JobQuqueID
   */
  protected function getJobAndJobqueue($groupId, $upload, $addUploads)
  {
    $uploadId = $upload->getId();
    $SpdxThreeAgent = plugin_find('agent_'.$this->outputFormat);
    $userId = Auth::getUserId();
    $jqCmdArgs = $this->uploadsAdd($addUploads);

    $dbManager = $this->getObject('db.manager');
    $sql = 'SELECT jq_pk,job_pk FROM jobqueue, job '
         . 'WHERE jq_job_fk=job_pk AND jq_type=$1 AND job_group_fk=$4 AND job_user_fk=$3 AND jq_args=$2 AND jq_endtime IS NULL';
    $params = array($SpdxThreeAgent->AgentName,$uploadId,$userId,$groupId);
    $log = __METHOD__;
    if ($jqCmdArgs) {
      $sql .= ' AND jq_cmd_args=$5';
      $params[] = $jqCmdArgs;
      $log .= '.args';
    } else {
      $sql .= ' AND jq_cmd_args IS NULL';
    }
    $scheduled = $dbManager->getSingleRow($sql,$params,$log);
    if (!empty($scheduled)) {
      return array($scheduled['job_pk'],$scheduled['jq_pk']);
    }
    if (empty($jqCmdArgs)) {
      $jobName = $upload->getFilename();
    } else {
      $jobName = "Multi File SPDX3";
    }
    $jobId = JobAddJob($userId, $groupId, $jobName, $uploadId);
    $error = "";
    $jobQueueId = $SpdxThreeAgent->AgentAdd($jobId, $uploadId, $error, array(), $jqCmdArgs);
    if ($jobQueueId < 0) {
      throw new \Exception(_("Cannot schedule").": ".$error);
    }
    return array($jobId,$jobQueueId, $error);
  }

  /**
   * @brief Get Upload object for a given upload id
   * @param int $uploadId
   * @param int $groupId
   * @throws Exception
   * @return Fossology::Lib::Data::Upload::Upload
   */
  protected function getUpload($uploadId, $groupId)
  {
    if ($uploadId <= 0) {
      throw new \Exception(_("parameter error: $uploadId"));
    }
    /* @var $uploadDao UploadDao */
    $uploadDao = $this->getObject('dao.upload');
    if (!$uploadDao->isAccessible($uploadId, $groupId)) {
      throw new \Exception(_("permission denied"));
    }
    /** @var Upload */
    $upload = $uploadDao->getUpload($uploadId);
    if ($upload === null) {
      throw new \Exception(_('cannot find uploadId'));
    }
    return $upload;
  }

  /**
   * Schedules spdx agent to generate report based of outputFormat
   *
   * @param int $groupId
   * @param Upload $upload
   * @param string $outputFormat
   * @param array $addUploads
   * @return array|number[] Job id and job queue id
   * @throws Exception
   */
  public function scheduleAgent($groupId, $upload,
    $outputFormat = self::DEFAULT_OUTPUT_FORMAT, $addUploads = array())
  {
    $this->outputFormat = $outputFormat;
    return $this->getJobAndJobqueue($groupId, $upload, $addUploads);
  }
}

register_plugin(new SpdxThreeGeneratorUi());
