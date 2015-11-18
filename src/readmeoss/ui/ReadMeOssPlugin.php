<?php
/*
 Copyright (C) 2014-2015 Siemens AG

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

use Fossology\Lib\Auth\Auth;
use Fossology\Lib\Dao\FolderDao;
use Fossology\Lib\Dao\UploadDao;
use Fossology\Lib\Data\Upload\Upload;
use Fossology\Lib\Plugin\DefaultPlugin;
use Symfony\Component\HttpFoundation\Request;

class ReadMeOssPlugin extends DefaultPlugin
{
  const NAME = 'ui_readmeoss';
  
  function __construct()
  {
    parent::__construct(self::NAME, array(
        self::TITLE => _("ReadME_OSS generation"),
        self::PERMISSION => Auth::PERM_WRITE,
        self::REQUIRES_LOGIN => TRUE
    ));
  }

  function preInstall()
  {
    $text = _("Generate ReadMe_OSS");
    menu_insert("Browse-Pfile::Export&nbsp;ReadMe_OSS", 0, self::NAME, $text);
    
    menu_insert("UploadMulti::Generate&nbsp;ReadMe_OSS", 0, self::NAME, $text);
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
                  'reportType' => "ReadMe_OSS");
    $text = sprintf(_("Generating ReadMe_OSS for '%s'"), $upload->getFilename());
    $vars['content'] = "<h2>".$text."</h2>";
    $content = $this->renderer->loadTemplate("report.html.twig")->render($vars);
    $message = '<h3 id="jobResult"></h3>';
    $request->duplicate(array('injectedMessage'=>$message,'injectedFoot'=>$content,'mod'=>'showjobs'))->overrideGlobals();
    $showJobsPlugin = \plugin_find('showjobs');
    $showJobsPlugin->OutputOpen();
    return $showJobsPlugin->getResponse();
  }
  
  protected function getJobAndJobqueue($groupId, $upload, $addUploads)
  {
    $uploadId = $upload->getId();
    $readMeOssAgent = plugin_find('agent_readmeoss');
    $userId = Auth::getUserId();
    $jqCmdArgs = $readMeOssAgent->uploadsAdd($addUploads);
    $dbManager = $this->getObject('db.manager');
    $sql = 'SELECT jq_pk,job_pk FROM jobqueue, job '
         . 'WHERE jq_job_fk=job_pk AND jq_type=$1 AND job_group_fk=$4 AND job_user_fk=$3 AND jq_args=$2 AND jq_endtime IS NULL';
    $params = array($readMeOssAgent->AgentName,$uploadId,$userId,$groupId);
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
    $jobQueueId = $readMeOssAgent->AgentAdd($jobId, $uploadId, $error, array(), $jqCmdArgs);
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

register_plugin(new ReadMeOssPlugin());
