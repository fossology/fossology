<?php

use Fossology\Lib\Auth\Auth;
use Symfony\Component\HttpFoundation\Response;
use Fossology\Lib\Dao\UploadDao;
/*
 SPDX-FileCopyrightText: © 2014 Hewlett-Packard Development Company, L.P.

 SPDX-License-Identifier: GPL-2.0-only
*/

/**
 * \file ajax_schedule_agent.php
 * \brief schedule one agent on one upload
 * This is intended as an active plugin to provide support
 * data to the UI.
 */

define("TITLE_AJAX_SCHEDULE_AGENT", _("Schedule agent"));

/**
 * \class ajax_upload_agents extends from FO_Plugin
 * \brief list all agents that can be scheduled for a given upload.
 */
class ajax_schedule_agent extends FO_Plugin
{
  private $uploadDao;
  function __construct()
  {
    $this->Name       = "schedule_agent";
    $this->Title      = TITLE_AJAX_SCHEDULE_AGENT;
    $this->DBaccess   = PLUGIN_DB_READ;
    parent::__construct();
    $this->uploadDao = $GLOBALS['container']->get('dao.upload');
  }


  /**
   * \brief Display the loaded menu and plugins.
   */
  public function Output()
  {
    global $Plugins;
    $UploadPk = GetParm("upload",PARM_INTEGER);
    $Agent = GetParm("agent",PARM_STRING);
    if (empty($UploadPk) || empty($Agent)) {
      return new Response('missing parameter', Response::HTTP_BAD_REQUEST,
        array('Content-type' => 'text/plain'));
    }
    $upload = $this->uploadDao->getUpload($UploadPk);
    if ($upload === null) {
      $errMsg = __FILE__ . ":" . __LINE__ . " " . _("Upload") . " " . $UploadPk .
        " " . _("not found");
      return new Response($errMsg, Response::HTTP_BAD_REQUEST,
        array('Content-type' => 'text/plain'));
    }
    $ShortName = $upload->getFilename();
    $user_pk = Auth::getUserId();
    $group_pk = Auth::getGroupId();
    $job_pk = JobAddJob($user_pk, $group_pk, $ShortName, $UploadPk);

    $Dependencies = array();
    $P = &$Plugins[plugin_find_id($Agent)];
    $rv = $P->AgentAdd($job_pk, $UploadPk, $ErrorMsg, $Dependencies);
    if ($rv <= 0) {
      $text = _("Scheduling of Agent(s) failed: ");
      return new Response($text . $rv . $ErrorMsg, Response::HTTP_BAD_REQUEST,
        array('Content-type' => 'text/plain'));
    }

    /** check if the scheudler is running */
    $status = GetRunnableJobList();
    $scheduler_msg = "";
    if (empty($status)) {
      $scheduler_msg .= _("Is the scheduler running? ");
    }

    $URL = Traceback_uri() . "?mod=showjobs&upload=$UploadPk";
    /* Need to refresh the screen */
    $text = _("Your jobs have been added to job queue.");
    $LinkText = _("View Jobs");
    $msg = "$scheduler_msg"."$text <a href=$URL>$LinkText</a>";
    $this->vars['message'] = $msg;
    return new Response($msg, Response::HTTP_OK, array('Content-type'=>'text/plain'));
  }
}

$NewPlugin = new ajax_schedule_agent;
$NewPlugin->Initialize();
