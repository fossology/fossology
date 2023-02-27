<?php
/*
 SPDX-FileCopyrightText: © 2022 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

/**
 * @dir
 * @brief Contains UI plugin for Decision Exporter agent
 * @file
 * @brief Contains UI plugin for Decision Exporter agent
 */

namespace Fossology\DecisionExporter\UI;

use Exception;
use Fossology\Lib\Auth\Auth;
use Fossology\Lib\Dao\UploadDao;
use Fossology\Lib\Plugin\DefaultPlugin;
use Symfony\Component\HttpFoundation\Request;
use Fossology\Lib\Data\Upload\Upload;

/**
 * @class FoDecisionExporter
 * @brief FOSSology Decision Exporter UI plugin
 */
class FoDecisionExporter extends DefaultPlugin
{
  const NAME = 'agent_fodecisionexporter';       ///< Plugin mod name

  function __construct()
  {
    parent::__construct(self::NAME, array(
        self::TITLE => _("FOSSology Dump Exporter"),
        self::PERMISSION => Auth::PERM_WRITE,
        self::REQUIRES_LOGIN => TRUE
    ));
  }

  /**
   * @copydoc Fossology::Lib::Plugin::DefaultPlugin::handle()
   * @see Fossology::Lib::Plugin::DefaultPlugin::handle()
   */
  protected function handle(Request $request)
  {
    $groupId = Auth::getGroupId();
    $uploadId = intval($request->get('upload'));
    try {
      $upload = $this->getUpload($uploadId, $groupId);
    } catch(Exception $e) {
      return $this->flushContent($e->getMessage());
    }

    list($jobId, $jobQueueId, $error) = $this->scheduleAgent($groupId, $upload);

    if ($jobQueueId < 0) {
      return $this->flushContent(_('Cannot schedule').": $error");
    }

    $vars = array('jqPk' => $jobQueueId,
                  'downloadLink' => Traceback_uri(). "?mod=download&report=".$jobId,
                  'reportType' => "dumpexporter");
    $text = sprintf(_("Generating FOSSology Decisions for '%s'"), $upload->getFilename());
    $vars['content'] = "<h2>".$text."</h2>";
    $content = $this->renderer->load("report.html.twig")->render($vars);
    $message = '<h3 id="jobResult"></h3>';
    $request->duplicate(array('injectedMessage'=>$message,'injectedFoot'=>$content,'mod'=>'showjobs'))->overrideGlobals();
    $showJobsPlugin = \plugin_find('showjobs');
    $showJobsPlugin->OutputOpen();
    return $showJobsPlugin->getResponse();
  }

  /**
   * @brief Get Upload object for an upload id
   * @param int $uploadId
   * @param int $groupId
   * @throws Exception
   * @return Upload Upload object for $uploadId
   */
  protected function getUpload($uploadId, $groupId)
  {
    if ($uploadId <= 0) {
      throw new Exception(_("parameter error"));
    }
    /** @var UploadDao $uploadDao*/
    $uploadDao = $this->getObject('dao.upload');
    if (!$uploadDao->isAccessible($uploadId, $groupId)) {
      throw new Exception(_("permission denied"));
    }
    /** @var Upload $upload*/
    $upload = $uploadDao->getUpload($uploadId);
    if ($upload === null) {
      throw new Exception(_('cannot find uploadId'));
    }
    return $upload;
  }

  /**
   * @copydoc Fossology::Lib::Plugin::DefaultPlugin::preInstall()
   * @see Fossology::Lib::Plugin::DefaultPlugin::preInstall()
   */
  function preInstall()
  {
    $text = _("Decision Dump Exporter");
    menu_insert("Browse-Pfile::Export&nbsp;FOSSology&nbsp;Dump", 0, self::NAME, $text);
  }

  /**
   * Schedules Decision Exporter agent to Decision Exporter Decisions
   *
   * @param int $groupId
   * @param Upload $upload
   * @return array Job id, Job queue id and error
   */
  public function scheduleAgent($groupId, $upload)
  {
    $decisionExpoAgent = plugin_find('agent_decisionexporter');
    $userId = Auth::getUserId();
    $uploadId = $upload->getId();
    $jobId = JobAddJob($userId, $groupId, $upload->getFilename(), $uploadId);
    $error = "";
    $url = tracebackTotalUri();
    $url = preg_replace("/api\/.*/i", "", $url); // Remove api/v1/report
    $jobQueueId = $decisionExpoAgent->AgentAdd($jobId, $uploadId, $error, array(), $url);
    return array($jobId, $jobQueueId, $error);
  }
}

register_plugin(new FoDecisionExporter());
