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
use Fossology\Lib\Db\DbManager;

define("TITLE_jobinfo", _("Private: reply to job status information"));

class ajaxJobInfo extends FO_Plugin
{
  /**
   * @var DbManager
   */
  private $dbManager;

  function __construct()
  {
    $this->Name = "jobinfo";
    $this->Title = TITLE_jobinfo;
    $this->Version = "1.0";
    $this->Dependency = array();
    $this->DBaccess = PLUGIN_DB_READ;
    $this->NoHTML = 1;
    $this->LoginFlag = 0;
    $this->NoMenu = 0;

    parent::__construct();

    global $container;
    $this->dbManager = $container->get('db.manager');
  }

  /**
   * \brief Display the loaded menu and plugins.
   */
  function Output()
  {
    if ($this->State != PLUGIN_STATE_READY) {
      return;
    }

    $userId = $_SESSION['UserId'];

 //   $groupId = $_SESSION['GroupId'];

    $jqIds = (array)$_POST['jqIds'];

    $result = array();
    foreach($jqIds as $jq_pk) {
      $jobInfo = $this->dbManager->getSingleRow(
        "SELECT jobqueue.jq_end_bits as end_bits FROM
        jobqueue INNER JOIN job ON jobqueue.jq_job_fk = job.job_pk
        WHERE jobqueue.jq_pk = $1 AND job_user_fk = $2",
        array($jq_pk, $userId)
       );
       if ($jobInfo !== false) {
         $result[$jq_pk] = array('end_bits' => $jobInfo['end_bits']);
       }
    }

    ReportCachePurgeAll();

    if (!empty($result)) {
      header('Content-type: text/json');
      print json_encode($result);
    } else {
      header('Content-type: text/json', true, 500);
      print json_encode(array("error" => "no info"));
    }
  } // Output()

}

$NewPlugin = new ajaxJobInfo;
$NewPlugin->Initialize();


