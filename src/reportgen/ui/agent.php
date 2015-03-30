<?php
/*
 Copyright (C) 2014-2015, Siemens AG
 Author: Daniele Fognini, Steffen Weber

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

class ReportGenerator extends DefaultPlugin
{
  const NAME = 'ui_reportgen';
  
  function __construct()
  {
    parent::__construct(self::NAME, array(
        self::TITLE => _("Report Generator"),
        self::PERMISSION => Auth::PERM_WRITE,
        self::REQUIRES_LOGIN => TRUE
    ));
  }

  protected function handle(Request $request)
  {
    $userId = Auth::getUserId();
    $groupId = Auth::getGroupId();

    $uploadId = intval($request->get('upload'));
    if ($uploadId <=0)
    {
      return $this->flushContent(_("parameter error"));
    }
    /** @var UploadDao */
    $uploadDao = $GLOBALS['container']->get('dao.upload');
    if (!$uploadDao->isAccessible($uploadId, $groupId))
    {
      return $this->flushContent(_("permission denied"));
    }

    /** @var Upload */
    $upload = $uploadDao->getUpload($uploadId);
    if ($upload === null)
    {
      return $this->flushContent(_('cannot find uploadId'));
    }
    
    $reportGenAgent = plugin_find('agent_reportgen');
    $jobId = JobAddJob($userId, $groupId, $upload->getFilename(), $uploadId);
    $error = "";
    $jobQueueId = $reportGenAgent->AgentAdd($jobId, $uploadId, $error, array());

    if ($jobQueueId<0)
    {
      return $this->flushContent(_('Cannot schedule').": $error");
    }

    $vars = array('jqPk' => $jobQueueId,
                  'downloadLink' => Traceback_uri(). "?mod=download&report=".$jobId,
                  'reportType' => "report");
    $text = sprintf(_("Generating new report for '%s'"), $upload->getFilename());
    $vars['content'] = "<h2>".$text."</h2>";
    return $this->render("report.html.twig", $this->mergeWithDefault($vars));
  }

  function preInstall()
  {
    $text = _("Generate Report");
    menu_insert("Browse-Pfile::Generate&nbsp;Word&nbsp;Report", 0, self::NAME, $text);
  }
}

register_plugin(new ReportGenerator());
