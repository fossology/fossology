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
 * find the job in the job table to be dependent on
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
   * the time it takes adj2nest to run/complete.  Be Dependent on license agent.
   *
   * If there is no license agent, use the highest jq_pk for the highest job
   * number as your dependency.
   */
  global $DB;

  if(!empty($list)) {
    /* process this list instead of what's in the db.*/
    // for each job in the list, get the highest jq_pk.
    // use the highest jq_pk as the dependency
    //return;
  }
  print "  <pre>FD:uploadpk is:$UploadPk\n";
  /* get job list for this upload */
  $Sql = "SELECT job_upload_fk, job_pk, job_name FROM job WHERE " .
  "job_upload_fk = $UploadPk order by job_pk desc;";
  $Jobs = $DB->Action($Sql);
  /*
   * If there is a license job, use that job_pk to get the jobqueue item to be
   * dependent on.
   */
  $LicenseJob = FALSE;
  foreach($Jobs as $Row) {
    foreach($Row as $col => $value) {
      if($value == 'license') {
        $job_pk = $Row['job_pk'];
        $LicenseJob = TRUE;
      }
    }
  }
  /* No license job, just use the last job*/
  if(!$LicenseJob) {
    $job_pk = $Jobs[0]['job_pk'];
  }

  /* Find the highest jq_pk for the job */
  $Sql = "SELECT jq_pk, jq_job_fk FROM jobqueue WHERE " .
         "jq_job_fk = $job_pk order by jq_pk desc limit 1;";
  $JobQueue = $DB->Action($Sql);
  $jq_pk = $JobQueue[0]['jq_pk'];

  $Depends[] = $jq_pk;
  return($Depends);
} // FindDependent

/**
 * ScheduleEmailNotification
 *
 * Schedule email notification for analysis results
 *
 * ScheduleEmailNotification determines the proper job dependency and schedules
 * the email agent fossjobstat to send the message.
 *
 * This routine is called from a number of UI plugins and cp2foss.  The optional
 * parameters are to accomdate the UI upload_srv_files and the agent_ add
 * plugins.
 *
 * This routine should only be called if the user wants to be notified by email
 * of analysis results. See CheckEnotification().
 *
 * @param int $upload_pk the upload_pk of the upload
 * @param string $Email, an optional email address to pass on to fossjobstat.
 * @param string $UserName, an optional User name to pass on to fossjobstat.
 * @param string $JobName, an optional Job Name to pass on to fossjobstat.
 * @param array $list optional list of jobs (supplied by agent add) agent_add).
 *
 * @return NULL on success, string on failure.
 */

function scheduleEmailNotification($upload_pk,$Email=NULL,$UserName=NULL,
$JobName=NULL,$list=NULL) {

  global $DB;
  if (empty($DB)) {
    return;
  }
  /*if(!is_array($list)) {
   * or make it one?
   return ('Invalid parameter type \$list must be an array');
   }
   */
  if (empty($upload_pk)) {
    return ('Invalid parameter (upload_pk)');
  }
  /* We got called by agent_add, use this list of jobs to determine dependencies*/
  if(!empty($list)) {
    $Depends = FindDependent($upload_pk, $list);
  }
  else {
    print "  SEN:calling FindDependent\n";
    $Depends = FindDependent($upload_pk);
  }

  /* set up input for fossjobstat */
  $FJSparams = '';
  $To = '';
  if(empty($_SESSION['UserEmail'])) {
    print "  SEN:setting To to fossy\n";
    $To = ' -e fossy';
  }
  else {
    print "  SEN:setting To to:{$_SESSION['UserEmail']}\n";
    $To = " -e {$_SESSION['UserEmail']}";
  }

  /* Upload Pk */
  $upload_id = trim($upload_pk);
  $UploadId = "-u $upload_id";
  print "  SEN: To is:$To\nUploadID is:$UploadId\n";
  $FJSparams .= "$UploadId";
  /* look at this, should you favor email over Username passed in? (vavor email) */
  if (!empty($UserName)) {
    print "  SEN:adding -n UserName\n";
    $FJSparams .= " -n $UserName";
  }
  if (!empty($JobName)) {
    print "  SEN:adding -j JobName\n";
    $FJSparams .= " -j $JobName";
  }
  if(!empty($Email)) { // email optional parameter (used by cp2foss)
    /* if we got email, append it to the list (cli invocation)*/
    $FJSparams = "$To" . " $Email";
    print "  SEN:after append of passed in email\nTo is:$FJSparams\n";
  }
  $FJSparams .= "$To";

  print "<pre>SEN:FJSparams are:$FJSparams\n</pre>";

  /* Prepare the job: job "fossjobstat" */
  $jobpk = JobAddJob($upload_pk,"fossjobstat",-1);
  if (empty($jobpk) || ($jobpk < 0)) {
    return("Failed to insert job record, job fossjobstat not created");
  }

  /* Prepare the job: job fossjobstat has jobqueue item fossjobstat */
  /** 2nd parameter is obsolete **/
  $jobqueuepk = JobQueueAdd($jobpk,"fossjobstat","$FJSparams","no",NULL,$Depends);
  if (empty($jobqueuepk)) {
    return("Failed to insert task 'fossjobstat' into job queue");
  }
  return(NULL);
}
?>
