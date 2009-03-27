<?php
/***********************************************************
 Copyright (C) 2008 Hewlett-Packard Development Company, L.P.

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
 ***********************************************************/

/*************************************************
 Restrict usage: Every PHP file should have this
 at the very beginning.
 This prevents hacking attempts.
 *************************************************/
global $GlobalReady;
if (!isset($GlobalReady)) { exit; }

/************************************************************
 These are common functions used by analysis agents.
 Analysis Agents should register themselves in the menu structure under the
 top-level "Agents" menu.

 Every analysis agent should have a function called "AgentAdd()" that takes
 an Upload_pk and an optional array of dependent agents ids.

 Every analysis agent should also have a function called "AgentCheck($uploadpk)"
 that determines if the agent has already been scheduled.
 This function should return:
 0 = not scheduled
 1 = scheduled
 2 = completed
 ************************************************************/
/*
 * NOTE: neal says the name is wrong...should be Analysis...
 * TODo: change the name and all users of it.
 */
/************************************************************
 AgentCheckBoxMake(): Generate a checkbox list of available agents.
 Only agents that are not already scheduled are added.
 If $upload_pk == -1, then list all.
 Returns string containing HTML-formatted checkbox list.
 ************************************************************/
function AgentCheckBoxMake($upload_pk,$SkipAgent=NULL) {
  global $Plugins;
  $AgentList = menu_find("Agents",$Depth);
  $V = "";
  if (!empty($AgentList)) {
    foreach($AgentList as $AgentItem) {
      $Agent = &$Plugins[plugin_find_id($AgentItem->URI)];
      if (empty($Agent)) {
        continue;
      }
      if ($Agent->Name == $SkipAgent) {
        continue;
      }
      if ($upload_pk != -1) {
        $rc = $Agent->AgentCheck($upload_pk);
      }
      else {
        $rc = 0;
      }
      if ($rc == 0) {
        $Name = htmlentities($Agent->Name);
        $Desc = htmlentities($AgentItem->Name);
        $V .= "<input type='checkbox' name='Check_$Name' value='1' />$Desc<br />\n";
      }
    }
  }
  return($V);
} // AgentCheckBoxMake()

/************************************************************
 AgentCheckBoxDo(): Assume someone called AgentCheckBoxMake() and
 submitted the HTML form.  Run AgentAdd() for each of the checked agents.
 Because input comes from the user, validate that everything is
 legitimate.
 ************************************************************/
function AgentCheckBoxDo($upload_pk)
{
  global $Plugins;
  $AgentList = menu_find("Agents",$Depth);
  $V = "";
  if (!empty($AgentList)) {
    foreach($AgentList as $AgentItem) {
      $Agent = &$Plugins[plugin_find_id($AgentItem->URI)];
      if (empty($Agent)) {
        continue;
      }
      $rc = $Agent->AgentCheck($upload_pk);
      $Name = htmlentities($Agent->Name);
      $Parm = GetParm("Check_" . $Name,PARM_INTEGER);
      if (($rc == 0) && ($Parm == 1)) {
        $Agent->AgentAdd($upload_pk);
      }
    }
  }
  return($V);
} // AgentCheckBoxDo()

/**
 * CheckEnotification
 *
 * Check if email notification is on for this user
 *
 * @return boolean, true or false.
 */

function CheckEnotification() {
  if ($_SESSION['UserEnote'] == 'y') {
    return(TRUE);
  }
  else {
    return(FALSE);
  }
}

/**
 * FindDependent
 *
 * find the job in the job and jobqueue table to be dependent on
 *
 * @param int $UploadPk the upload PK
 * @param array $list an optional array of jobs to use instead of all jobs
 *        associated with the upload
 * @return array $depends, array of dependencies
 */
function FindDependent($UploadPk, $list=NULL) {
  /*
   * Dependencies.  Jobs are layed out in dependency order NOT execution order.
   * This makes scheduling the email notification much harder.
   * If the license agent is part of the jobs, then that will finish last due to
   * the time it takes adj2nest to run/complete.  So be dependent on license if
   * it's there.
   *
   * If there is no license agent, use the highest jq_pk for the highest job
   * number as your dependency.
   *
   * * is the above still true given sublists?
   *
   */
  global $DB;

  /* get job list for this upload */
  $Sql = "SELECT job_upload_fk, job_pk, job_name FROM job WHERE " .
  "job_upload_fk = $UploadPk order by job_pk desc;";
  $Jobs = $DB->Action($Sql);
  $Agent2Job =
  array('agent_license'  => 'license','agent_mimetype' => 'Default Meta Agents',
  'agent_pkgmetagetta' => 'Meta Analysis','agent_specagent' => 'Default Meta Agents');

  if(!empty($list)) {
    /* process this list instead of what's in the db.*/
    /* find the job_pk of each job added */
    foreach($list as $NewJob) {
      foreach($Agent2Job as $agent => $JobName) {
        if($NewJob == $agent) {
          $Sql = "SELECT job_upload_fk, job_pk, job_name FROM job WHERE " .
           "job_upload_fk = $UploadPk AND job_name = '$JobName' order by job_pk desc;";
          // get the job_pk for this job
          $job_pks = $DB->Action($Sql);
          $JobPks[] = $job_pks[0]['job_pk'];
        }
      }
    }
    //print "FD: list of job_pk's is:\n<br>"; print_r($JobPks) . "/n<br>";
    $foo = array(MostRows($JobPks));
    //print "FD: job_pk with MostRows is:\n<br>"; print_r($foo) . "/n<br>";
    return(array(MostRows($JobPks)));
  }
  else {
    /*
     * If there is a license job, use that job_pk to get the jobqueue item to be
     * dependent on.
     */
    $LicenseJob = FALSE;
    foreach($Jobs as $Row) {
      foreach($Row as $col => $JobType) {
        if($JobType == 'license') {
          $job_pk = $Row['job_pk'];
          $LicenseJob = TRUE;
        }
      }
    }

    /* No license job, just use the last job*/
    if(!$LicenseJob) {
      $job_pk = $Jobs[0]['job_pk'];
    }
  }
  /* Find the highest jq_pk for the job */
  $Depends[] = Largestjq_pk($job_pk);
  return($Depends);
} // FindDependent

/**
 * Largestjq_pk
 *
 * Find the largest jq_pk for the job or jobs.
 *
 * For a single job, returns the largest jobqueue_pk (jq_pk).  For multiple
 * jobs, returns the largest jq_pk of the set.
 *
 * @param $Jobs, either an int or an array of int's.
 * @return int $largest the largest jq_pk
 *
 */
function Largestjq_pk($Jobs) {

  global $DB;

  if (is_array($Jobs)) {
    $largest = 0;
    foreach ($Jobs as $job) {
      $Sql = "SELECT jq_pk, jq_job_fk FROM jobqueue WHERE " .
             "jq_job_fk = $job order by jq_pk desc limit 1;";
      $JobQueue = $DB->Action($Sql);
      if ($largest < $JobQueue[0]['jq_pk']) {
        $largest = $JobQueue[0]['jq_pk'];
      }
    }
    return($largest);
  }
  else {
    $Sql = "SELECT jq_pk, jq_job_fk FROM jobqueue WHERE " .
           "jq_job_fk = $Jobs order by jq_pk desc limit 1;";
    $JobQueue = $DB->Action($Sql);
    return($JobQueue[0]['jq_pk']);
  }
}
/**
 * MostRows
 *
 * Find the largest jq_pk for the job that has the most rows
 *
 * This routine is used to determine who the caller should be dependent on based
 * on the number of jobqueue items for the list of jobs supplied.  The job with
 * the largest number of jobqueue items, largest jq_pk is returned.
 *
 * @param $Jobs, an array of int's representing job_pk items
 * @return int $largest the largest jq_pk with the most rows
 *
 */
function MostRows($Jobs) {

  global $DB;

  if (is_array($Jobs)) {
    $rows = 0;
    $MostRows = 0;
    $largest = 0;
    foreach ($Jobs as $job) {
      $Sql = "SELECT jq_pk, jq_job_fk FROM jobqueue WHERE " .
             "jq_job_fk = $job order by jq_pk desc;";
      $JobQueue = $DB->Action($Sql);
      $rows = count($JobQueue);
      if ($MostRows < $rows) {
        $MostRows = $rows;
        $largest = $JobQueue[0]['jq_pk'];
      }
    }
    //print "  MR: MostRows is:$MostRows\n<br>Largest Jq:$largest\n<br>";
    return($largest);
  }
}
/**
 * ScheduleEmailNotification
 *
 * Schedule email notification for analysis results
 *
 * ScheduleEmailNotification determines the proper job dependency and schedules
 * the email agent notify to send the message.
 *
 * This routine is called from a number of UI plugins and cp2foss.  The optional
 * parameters are to accomdate the UI upload_srv_files and the agent_ add
 * plugins.
 *
 * This routine should only be called if the user wants to be notified by email
 * of analysis results. See CheckEnotification().
 *
 * @param int $upload_pk the upload_pk of the upload
 * @param string $Email, an optional email address to pass on to notify.
 * @param string $UserName, an optional User name to pass on to notify.
 * @param string $JobName, an optional Job Name to pass on to notify.
 * @param array $list optional list of jobs (supplied by agent add) agent_add).
 *
 * @return NULL on success, string on failure.
 */

function scheduleEmailNotification($upload_pk,$Email=NULL,$UserName=NULL,
$JobName=NULL,$list=NULL,$Reschedule=FALSE) {

  global $DB;
  $Depends = array();

  if (empty($DB)) {
    return;
  }

  if (empty($upload_pk)) {
    return ('Invalid parameter (upload_pk)');
  }
  /*
   * A valid $list means we got called by agent_add, use this list of jobs to
   * determine dependencies
   */
  if(!empty($list)) {
    $Depends = FindDependent($upload_pk, $list);
  }
  else {
    $Depends = FindDependent($upload_pk);
  }
  /* set up input for notify */
  $Nparams = '';
  $To = NULL;
  /* If email is passed in, favor that over the session */
  if(!empty($Email)) {
    $To = " -e $Email";
  }
  elseif (!empty($_SESSION['UserEmail'])) {
    $To = " -e {$_SESSION['UserEmail']}";
    //print "  SEN: Setting To to Email in session To:$To\n";
  }
  if(empty($To)) {
    return('FATAL: Email Notification: no email address supplied, cannot send mail,' .
             ' common-agents::scheduleEmailNotification' .
             ' Your job should be scheduled, you will not get email notifying you it is done');
  }
  $Nparams .= "$To";
  /* Upload Pk */
  $upload_id = trim($upload_pk);
  $UploadId = "-u $upload_id";
  $Nparams .= " $UploadId";
  /*
   * UserName is NOT the email address it's the description for the user field
   * in the Add A User screen..... need to fix that screen....
   */
  if (!empty($UserName)) {
    $Nparams .= " -n $UserName";
  }
  if (!empty($JobName)) {
    $Nparams .= " -j $JobName";
  }

  /* Prepare the job: job "notify" */
  $jobpk = JobAddJob($upload_pk,"notify",-1);
  if (empty($jobpk) || ($jobpk < 0)) {
    return("Failed to insert job record, job notify not created");
  }

  /* Prepare the job: job notify has jobqueue item notify */
  if ($Reschedule) {
    $jobqueuepk = JobQueueAdd($jobpk,"notify","$Nparams","no",NULL,$Depends,TRUE);
    if (empty($jobqueuepk)) {
      return("Failed to insert task 'notify' into job queue");
    }
  }
  else {
    $jobqueuepk = JobQueueAdd($jobpk,"notify","$Nparams","no",NULL,$Depends,FALSE);
  }
  return(NULL);
}
?>
