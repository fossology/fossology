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
 * scheduleEmailNotification
 *
 * Schedule email notification for analysis results
 *
 * This routine provides the upload_pk and TO: parameter as jobqueue args for the
 * email_results agent.
 *
 * This routine should only be called if the user wants to be notified by email
 * of analysis results. See CheckEnotification().
 *
 * @param int $upload_pk the upload_pk of the upload
 * @param array $list optional list of jobs (used by agent_add).
 *
 * @return NULL on success, string on failure.
 */

function scheduleEmailNotification($upload_pk,array($list) = NULL) {

  global $DB;
  if (empty($DB)) {
    return;
  }
  if (empty($upload_pk)) {
    return ('Invalid parameter (upload_pk)');
  }
  if(!empty($list)) {
    /* process this list instead of what's in the db.*/
    // for each job in the list, get the highest jq_pk.
    // use the highest jq_pk as the dependency
  }

  /*
   * Dependencies.  Jobs are layed out in dependency order NOT execution order.
   * This makes scheduling the email notification much harder.
   * If the license agent is part of the jobs, then that will finish last due to
   * the time it takes adj2nest to run/complete.
   *
   * If there is no license agent, use the highest jq_pk for the highest job
   * number.
   */

  /* get job list for this upload */
  $Sql = "SELECT job_upload_fk, job_pk, job_name FROM job WHERE " .
  "job_upload_fk = $upload_pk order by job_pk desc;";
  $Results = $DB->Action($Sql);
  $jobs = count($Results);
  print "<pre>SEN:jobs in job table, for upload $upload_pk\n"; print_r($Results) . "\n</pre>";
  /* If there is a license job, use that job_pk to get the jobqueue item to be
   * dependent on.
   */
  $LicenseJob = FALSE;
  foreach($Results as $Row) {
    foreach($Row as $col => $value) {
      //print "<pre>SEN:col is:$col\nValue is:$value\n</pre>";
      if($value == 'license') {
        //print "<pre>SEN:found:$value\n</pre>";
        $job_pk = $Row['job_pk'];
        $LicenseJob = TRUE;
        //break 2;
      }
    }
  }
  /* No license job, just use the last job*/
  if(!$LicenseJob) {
    $Sql = "SELECT job_upload_fk, job_pk, job_name FROM job WHERE " .
           "job_upload_fk = $upload_pk order by job_pk desc limit 1;";
    $Job = $DB->Action($Sql);
    $job_pk = $Job[0]['job_pk'];
    print "<pre>SEN:job_pk with No license job is $job_pk\n";
  }

  //$Row = $Results[0];
  //$job_pk = $Row['job_pk'];
  print "<pre>SEN:job_pk is $job_pk\n";

  $Sql = "SELECT jq_pk, jq_job_fk FROM jobqueue WHERE " .
         "jq_job_fk = $job_pk order by jq_pk desc limit 1;";
  $JobQueue = $DB->Action($Sql);
  $jq_pk = $JobQueue[0]['jq_pk'];
  print "<pre>SEN:Highest jq_pk for $job_pk is:$jq_pk\n</pre>";

  $Depends[] = $jq_pk;

  /*
   * this approach does not work... the real dependency seems to be on license...
   //Find the highest jobqueue for upload/job
   // get what appears to be the hightest one
   $Sql = "SELECT jq_pk, jq_job_fk FROM jobqueue WHERE " .
   "jq_job_fk = $job_pk order by jq_pk desc ;";
   $jobQ = $DB->Action($Sql);
   $jq_pk = $jobQ[0]['jq_pk'];
   print "<pre>SEN:jobqueues for job_pk $job_pk\n"; print_r($jobQ) . "\n</pre>";
   print "<pre>SEN:Initial jobqueue is $jq_pk\n";
   // Make sure it is
   print "<pre>SEN:number of jobs:$jobs\n";
   for ($i=0; $jobs > $i; $i++) {
   $job_pk = $Results[$i]['job_pk'];
   print "<pre>SEN:in loop job_pk:$job_pk\n";
   print "<pre>SEN:JobQueue for job_pk $job_pk\n"; print_r($JobQueue) . "\n</pre>";
   print "<pre>SEN:jq_pk is:$jq_pk\n";
   print "<pre>SEN:JobQueue[jq_pk]:{$JobQueue[0]['jq_pk']}\n";
   if($jq_pk < $JobQueue[0]['jq_pk']) {
   print "<pre>SEN:setting jq_pk to:{$JobQueue[0]['jq_pk']}\n";
   $jq_pk = $JobQueue[0]['jq_pk'];
   }
   }
   print "<pre>SEN:highest jobqueue is $jq_pk\n";
   */

  /* set up input for fossjobstat */
  $To = "-t {$_SESSION['UserEmail']}";
  $upload_id = trim($upload_pk);
  $uploadId = "-u $upload_id";

  // query for job status? Which job!?  Do you have to look at all sub jobs to
  // determine if they all passed or can you look (where?!)

  /* That job is who we are dependent on. Add the email_results agent into the
   * job table and jobqueue
   */
  /* Prepare the job: job "fossjobstat" */
  $jobpk = JobAddJob($upload_pk,"fossjobstat",-1);
  if (empty($jobpk) || ($jobpk < 0)) {
    return("Failed to insert job record, job fossjobstat not created");
  }

  /* Prepare the job: job fossjobstat has jobqueue item fossjobstat */
  /** 2nd parameter is obsolete **/
  $jobqueuepk = JobQueueAdd($jobpk,"fossjobstat","$To $uploadId","no",NULL,$Depends);
  if (empty($jobqueuepk)) {
    return("Failed to insert task 'fossjobstat' into job queue");
  }
  return(NULL);
}
?>
