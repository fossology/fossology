<?php
/*
 Copyright (C) 2015 Siemens AG

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
 */

namespace Fossology\SpdxTwo;

use Fossology\Lib\Auth\Auth;
use Fossology\Lib\Dao\UploadDao;
use Fossology\Lib\Data\Upload\Upload;
use Fossology\Lib\Plugin\DefaultPlugin;
use Symfony\Component\HttpFoundation\Request;

class SpdxTwoGeneratorUi extends DefaultPlugin
{
  const NAME = 'ui_spdx2';
  const DEFAULT_OUTPUT_FORMAT = "spdx2";
  /** @var string */
  protected $outputFormat = self::DEFAULT_OUTPUT_FORMAT;

  function __construct()
  {
    $possibleOutputFormat = trim(GetParm("outputFormat",PARM_STRING));
    if (strcmp($possibleOutputFormat,"") !== 0 &&
        strcmp($possibleOutputFormat,self::DEFAULT_OUTPUT_FORMAT) !== 0 &&
        ctype_alnum($possibleOutputFormat))
    {
      $this->outputFormat = $possibleOutputFormat;
    }
    parent::__construct(self::NAME, array(
        self::TITLE => _(strtoupper($this->outputFormat) . " generation"),
        self::PERMISSION => Auth::PERM_WRITE,
        self::REQUIRES_LOGIN => true
    ));
  }

  function preInstall()
  {
    $text = _("Generate SPDX report");
    menu_insert("Browse-Pfile::Export&nbsp;SPDX&nbsp;RDF", 0, self::NAME, $text);
    menu_insert("UploadMulti::Generate&nbsp;SPDX", 0, self::NAME, $text);

    $text = _("Generate SPDX report in tag:value format");
    menu_insert("Browse-Pfile::Export&nbsp;SPDX&nbsp;tag:value", 0, self::NAME . '&outputFormat=spdx2tv', $text);

    $text = _("Generate Debian Copyright file");
    menu_insert("Browse-Pfile::Export&nbsp;DEP5", 0, self::NAME . '&outputFormat=dep5', $text);
  }

  protected function handle(Request $request)
  {

    $groupId = Auth::getGroupId();
    $uploadIds = $request->get('uploads') ?: array();
    $uploadIds[] = intval($request->get('upload'));
    $addUploads = array();
    foreach($uploadIds as $uploadId)
    {
      if (empty($uploadId)) {
        continue;
      }
      try
      {
        $addUploads[$uploadId] = $this->getUpload($uploadId, $groupId);
      }
      catch(Exception $e)
      {
        return $this->flushContent($e->getMessage());
      }
    }
    $folderId = $request->get('folder');
    if(!empty($folderId))
    {
      /* @var $folderDao FolderDao */
      $folderDao = $this->getObject('dao.folder');
      $folderUploads = $folderDao->getFolderUploads($folderId, $groupId);
      foreach($folderUploads as $uploadProgress)
      {
        $addUploads[$uploadProgress->getId()] = $uploadProgress;
      }
    }
    if (empty($addUploads)) {
      return $this->flushContent(_('No upload selected'));
    }
    $upload = array_pop($addUploads);
    try
    {
      list($jobId,$jobQueueId) = $this->getJobAndJobqueue($groupId, $upload, $addUploads);
    }
    catch (Exception $ex) {
      return $this->flushContent($ex->getMessage());
    }

    $vars = array('jqPk' => $jobQueueId,
                  'downloadLink' => Traceback_uri(). "?mod=download&report=".$jobId,
                  'reportType' => $this->outputFormat);
    $text = sprintf(_("Generating ". $this->outputFormat . " report for '%s'"), $upload->getFilename());
    $vars['content'] = "<h2>".$text."</h2>";
    $content = $this->renderer->loadTemplate("report.html.twig")->render($vars);
    $message = '<h3 id="jobResult"></h3>';
    $request->duplicate(array('injectedMessage'=>$message,'injectedFoot'=>$content,'mod'=>'showjobs'))->overrideGlobals();
    $showJobsPlugin = \plugin_find('showjobs');
    $showJobsPlugin->OutputOpen();
    return $showJobsPlugin->getResponse();
  }

  protected function uploadsAdd($uploads)
  {
    if (count($uploads) == 0) {
      return '';
    }
    return '--uploadsAdd='. implode(',', array_keys($uploads));
  }

  protected function getJobAndJobqueue($groupId, $upload, $addUploads)
  {
    $uploadId = $upload->getId();
    $spdxTwoAgent = plugin_find('agent_'.$this->outputFormat);
    $userId = Auth::getUserId();
    $jqCmdArgs = $this->uploadsAdd($addUploads);

    $dbManager = $this->getObject('db.manager');
    $sql = 'SELECT jq_pk,job_pk FROM jobqueue, job '
         . 'WHERE jq_job_fk=job_pk AND jq_type=$1 AND job_group_fk=$4 AND job_user_fk=$3 AND jq_args=$2 AND jq_endtime IS NULL';
    $params = array($spdxTwoAgent->AgentName,$uploadId,$userId,$groupId);
    $log = __METHOD__;
    if ($jqCmdArgs) {
      $sql .= ' AND jq_cmd_args=$5';
      $params[] = $jqCmdArgs;
      $log .= '.args';
    }
    else {
      $sql .= ' AND jq_cmd_args IS NULL';
    }
    $scheduled = $dbManager->getSingleRow($sql,$params,$log);
    if (!empty($scheduled)) {
      return array($scheduled['job_pk'],$scheduled['jq_pk']);
    }
    $jobId = JobAddJob($userId, $groupId, $upload->getFilename(), $uploadId);
    $error = "";
    $jobQueueId = $spdxTwoAgent->AgentAdd($jobId, $uploadId, $error, array(), $jqCmdArgs);
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
    /* @var $uploadDao UploadDao */
    $uploadDao = $this->getObject('dao.upload');
    if (!$uploadDao->isAccessible($uploadId, $groupId))
    {
      throw new Exception(_("permission denied"));
    }
    /** @var Upload */
    $upload = $uploadDao->getUpload($uploadId);
    if ($upload === null)
    {
      throw new Exception(_('cannot find uploadId'));
    }
    return $upload;
  }
}

register_plugin(new SpdxTwoGeneratorUi());
