<?php
/***********************************************************
 * Copyright (C) 2014 Siemens AG
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

namespace Fossology\UI\Ajax;

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
        self::PERMISSION => self::PERM_WRITE,
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

    if ($uploadId > 0)
    {
      $upload = $this->uploadDao->getUpload($uploadId);
      $uploadName = $upload->getFilename();
      $jobId = JobAddJob($userId, $groupId, $uploadName, $uploadId);

      $ourPlugin = plugin_find($agentName);
      $jobqueueId = $ourPlugin->AgentAdd($jobId, $uploadId, $errorMessage, array());
    } else
    {
      $errorMessage = "bad request";
    }

    ReportCachePurgeAll();

    $headers = array('Content-type' => 'text/json');
    if (empty($errorMessage) && ($jobqueueId > 0))
    {
      return new Response(json_encode(array("jqid" => $jobqueueId)), Response::HTTP_OK, $headers);
    } else
    {
      return new Response(json_encode(array("error" => $errorMessage)), Response::HTTP_INTERNAL_SERVER_ERROR, $headers);
    }
  }
}

register_plugin(new ScheduleAgent());


