<?php
/*
 Copyright (C) 2017, Siemens AG

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
use Fossology\Lib\Plugin\DefaultPlugin;
use Symfony\Component\HttpFoundation\Request;

class FoUnifiedReportGenerator extends DefaultPlugin
{
  const NAME = 'agent_founifiedreport';
  
  function __construct()
  {
    parent::__construct(self::NAME, array(
        self::TITLE => _("Unified Report generation"),
        self::PERMISSION => Auth::PERM_WRITE,
        self::REQUIRES_LOGIN => TRUE
    ));
  }

  protected function handle(Request $request)
  {
    $groupId = Auth::getGroupId();
    $uploadId = intval($request->get('upload'));
    try
    {
      $upload = $this->getUpload($uploadId, $groupId);
    }
    catch(Exception $e)
    {
      return $this->flushContent($e->getMessage());
    }
    
    $reportGenAgent = plugin_find('agent_unifiedreport');
    $userId = Auth::getUserId();
    $jobId = JobAddJob($userId, $groupId, $upload->getFilename(), $uploadId);
    $error = "";
    $jobQueueId = $reportGenAgent->AgentAdd($jobId, $uploadId, $error, array(), tracebackTotalUri());

    if ($jobQueueId<0)
    {
      return $this->flushContent(_('Cannot schedule').": $error");
    }

    $vars = array('jqPk' => $jobQueueId,
                  'downloadLink' => Traceback_uri(). "?mod=download&report=".$jobId,
                  'reportType' => "Unified");
    $text = sprintf(_("Generating new report for '%s'"), $upload->getFilename());
    $vars['content'] = "<h2>".$text."</h2>";
    $content = $this->renderer->loadTemplate("report.html.twig")->render($vars);
    $message = '<h3 id="jobResult"></h3>';
    $request->duplicate(array('injectedMessage'=>$message,'injectedFoot'=>$content,'mod'=>'showjobs'))->overrideGlobals();
    $showJobsPlugin = \plugin_find('showjobs');
    $showJobsPlugin->OutputOpen();
    return $showJobsPlugin->getResponse();
  }
  
  protected function getUpload($uploadId, $groupId)
  {  
    if ($uploadId <=0)
    {
      throw new Exception(_("parameter error"));
    }
    /** @var UploadDao */
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

  function preInstall()
  {
    $text = _("Generate Report");
    menu_insert("Browse-Pfile::Export&nbsp;Unified&nbsp;Report", 0, self::NAME, $text);
  }
}

register_plugin(new FoUnifiedReportGenerator());
