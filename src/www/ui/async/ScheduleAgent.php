<?php
/*
 SPDX-FileCopyrightText: © 2014 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\UI\Ajax;

use Fossology\Lib\Auth\Auth;
use Fossology\Lib\Dao\UploadDao;
use Fossology\Lib\Plugin\DefaultPlugin;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class ScheduleAgent extends DefaultPlugin
{
  const NAME = "scheduleAgentAjax";

  /** @var UploadDao */
  private $uploadDao;

  function __construct()
  {
    parent::__construct(self::NAME, array(
        self::TITLE => _("Private: schedule a agent scan from post"),
        self::PERMISSION => Auth::PERM_WRITE,
    ));

    $this->uploadDao = $this->getObject('dao.upload');
  }

  /**
   * @param Request $request
   * @return Response
   */
  protected function handle(Request $request)
  {
    $errorMessage = "";
    $jobqueueId = -1;

    $userId = $_SESSION['UserId'];
    $groupId = $_SESSION['GroupId'];
    $uploadId = intval($_POST['uploadId']);
    $agentName = $_POST['agentName'];

    if ($uploadId > 0) {
      $upload = $this->uploadDao->getUpload($uploadId);
      $uploadName = $upload->getFilename();
      $ourPlugin = plugin_find($agentName);

      $jobqueueId = isAlreadyRunning($ourPlugin->AgentName, $uploadId);
      if ($jobqueueId == 0) {
        $jobId = JobAddJob($userId, $groupId, $uploadName, $uploadId);
        $jobqueueId = $ourPlugin->AgentAdd($jobId, $uploadId, $errorMessage, array());
      }
    } else {
      $errorMessage = "bad request";
    }

    ReportCachePurgeAll();

    $headers = array('Content-type' => 'text/json');
    if (empty($errorMessage) && ($jobqueueId > 0)) {
      return new Response(json_encode(array("jqid" => $jobqueueId)), Response::HTTP_OK, $headers);
    } else {
      return new Response(json_encode(array("error" => $errorMessage)), Response::HTTP_INTERNAL_SERVER_ERROR, $headers);
    }
  }
}

register_plugin(new ScheduleAgent());
