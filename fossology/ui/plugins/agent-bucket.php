<?php
/***********************************************************
Copyright (C) 2010 Hewlett-Packard Development Company, L.P.

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

/**
 * agent-bucket
 * \brief schedule the bucket agent
 * 
 * @version "$Id: agent-bucket.php  $"
 */
global $GlobalReady;
if (!isset($GlobalReady)) {
  exit;
}
class agent_bucket extends FO_Plugin {

  public $Name = "agent_bucket";
  public $Title = "Schedule Bucket Analysis";
  // public $MenuList   = "Jobs::Agents::Bucket Analysis";
  public $Version = "1.0";
  public $Dependency = array("db");
  public $DBaccess = PLUGIN_DB_ANALYZE;

  /***********************************************************
  RegisterMenus(): Register additional menus.
  ***********************************************************/
  function RegisterMenus() {
    if ($this->State != PLUGIN_STATE_READY) {
      return (0);
    } // don't run
    menu_insert("Agents::" . $this->Title, 0, $this->Name);
  }
  /*********************************************
  AgentCheck(): Check if the job is already in the
  queue.  Returns:
  0 = not scheduled
  1 = scheduled but not completed
  2 = scheduled and completed
  *********************************************/
  function AgentCheck($uploadpk) {
    global $DB;
    $SQL = "SELECT jq_pk,jq_starttime,jq_endtime FROM jobqueue INNER JOIN job" .
            " ON job_upload_fk = '$uploadpk'" .
"Is this every used?".
            " AND job_pk = jq_job_fk AND jq_type = 'buckets';";
    $Results = $DB->Action($SQL);
    if (empty($Results[0]['jq_pk'])) {
      return (0);
    }
    if (empty($Results[0]['jq_endtime'])) {
      return (1);
    }
    return (2);
  } // AgentCheck()

  /*********************************************
  AgentAdd(): Queue the bucket agent.
  $Depends is for specifying other dependencies.
  $Depends can be a jq_pk, or an array of jq_pks, or NULL.

  AgentAdd will queue a nomos agent if there are no
  license_file results for this upload.  If there are 
  results, then the agent will run on the most current.
  Note that the most current may not represent the latest
  nomos agent.

  The pkgagent is also queued.  At this time there is no 
  reliable way to see if the pkgagent has been run on an upload.

  Note that if the pkg and nomos agents are already in the queue
  for this upload, they will not be requeued.

  Returns NULL on success, string on failure.
  *********************************************/
  function AgentAdd($uploadpk, $Depends = NULL, $Priority = 0) 
  {
    global $PG_CONN, $DB;
    global $Plugins;
    $Dep = array();
    $NomosDep = array();

    /* Does the user have a default bucketpool_pk?
       If not, then fail because we don't know which to use.
     */
    if (!array_key_exists('UserId', $_SESSION))
      return("Session is unauthenticated, bucket agent cannot run without knowing who the user is.");

    if (is_array($Depends)) 
      $Dep = array_merge($Dep, $Depends);
    else 
    if (!empty($Depends)) $Dep[0] = $Depends;

    /* If an unpack for this upload is already in the job queue, 
       then get its jq_pk so we can set a dependency on it
     */
    $Unpack = & $Plugins[plugin_find_id("agent_unpack") ];
    if ($Unpack->AgentCheck($uploadpk) != 0) 
    {
      /* unpack is in queue, get it's jq_pk so we can set dependencies */
      $SQL = "SELECT jq_pk FROM jobqueue INNER JOIN job ON
              job.job_upload_fk = $uploadpk AND job.job_pk = jobqueue.jq_job_fk
              WHERE jobqueue.jq_type = 'adj2nest';";
      $Results = $DB->Action($SQL);
      if (!isset($Results[0])) return ("Unable to find dependent job: unpack");
      $Dep[] = $Results[0]['jq_pk'];
    }

    /* queue nomos.  If it's been previously run on this upload, it will exit
       successfully and quickly */
    $nomos = & $Plugins[plugin_find_id("agent_nomos") ];
    $rc = $nomos->AgentAdd($uploadpk);
    if (!empty($rc)) return $rc;

    /* To make the bucket agent dependent on nomos, we need it's jq_pk */
    $sql = "SELECT jq_pk FROM jobqueue INNER JOIN job ON
            job.job_upload_fk = $uploadpk AND job.job_pk = jobqueue.jq_job_fk
            WHERE jobqueue.jq_type = 'nomos';";
    $result = pg_query($PG_CONN, $sql);
    DBCheckResult($result, $sql, __FILE__, __LINE__);
    $row = pg_fetch_assoc($result);
    if (pg_num_rows($result) < 1) return ("Unable to find dependent job: unpack");
    $NomosDep[] = $row['jq_pk'];
    pg_free_result($result);

    /* queue pkgagent.  If it's been previously run on this upload, it will 
       run again but not insert duplicate pkgagent records.  */
    $pkgagent = & $Plugins[plugin_find_id("agent_pkgagent") ];
    $rc = $pkgagent->AgentAdd($uploadpk);
    if (!empty($rc)) return $rc;

    /* To make the bucket agent dependent on pkgagent, we need it's jq_pk */
    $sql = "SELECT jq_pk FROM jobqueue INNER JOIN job ON
            job.job_upload_fk = $uploadpk AND job.job_pk = jobqueue.jq_job_fk
            WHERE jobqueue.jq_type = 'pkgagent';";
    $result = pg_query($PG_CONN, $sql);
    DBCheckResult($result, $sql, __FILE__, __LINE__);
    $row = pg_fetch_assoc($result);
    if (pg_num_rows($result) < 1) return ("Unable to find dependent job: pkgagent");
    $NomosDep[] = $row['jq_pk'];
    pg_free_result($result);

    /* create the bucket job  */
    $jobpk = JobAddJob($uploadpk, "Bucket Analysis", $Priority);
    if (empty($jobpk) || ($jobpk < 0)) return ("Failed to queue bucket agent.");

    /* get the default_bucketpool_fk from the users record */
    $sql = "select default_bucketpool_fk from users where user_pk='$_SESSION[UserId]' limit 1";
    $result = pg_query($PG_CONN, $sql);
    DBCheckResult($result, $sql, __FILE__, __LINE__);
    $row = pg_fetch_assoc($result);
    $bucketpool_pk = $row['default_bucketpool_fk'];
    pg_free_result($result);

    if (!$bucketpool_pk) return ("User does not have a default bucketpool.  Bucket agent cannot be scheduled without this.");

    $jqargs = "bppk=$bucketpool_pk, upk=$uploadpk";
    $jobqueuepk = JobQueueAdd($jobpk, "buckets", $jqargs, "no", "", $NomosDep);
    if (empty($jobqueuepk)) return ("Failed to insert agent nomos into job queue");

    return (NULL);
  } // AgentAdd()

  /*********************************************
  Output(): Generate the text for this plugin.
  *********************************************/
  function Output() {
      if ($this->State != PLUGIN_STATE_READY) {
      return;
    }

    global $DB;

    $Page = "";
    switch ($this->OutputType) {
      case "XML":
      break;
      case "HTML":
        /* If this is a POST, then process the request. */
        $uploadpk = GetParm('upload', PARM_INTEGER);
        if (!empty($uploadpk)) {
          $rc = $this->AgentAdd($uploadpk);
          if (empty($rc)) {
            /* Need to refresh the screen */
            $Page.= displayMessage('Bucket analysis added to the job queue');
          }
          else {
            $Page.= displayMessage("Scheduling Bucket agent failed: $rc");
          }
        }
          /* Display the form */
          $Page.= "<form method='post'>\n"; // no url = this url
          $Page.= "<H1>NOTE: this code was borrowed from nomos.  It needs to be updated for buckets.  If you see this message please tell bobg.</H1>";
          $Page.= "Select an uploaded file for bucket analysis.\n";
          $Page.= "<p />\nAnalyze: <select name='upload'>\n";
          foreach($Results as $Row) {
            if (empty($Row['upload_pk'])) {
              continue;
            }
            if (empty($Row['upload_desc'])) {
              $Name = $Row['upload_filename'];
            }
            else {
              $Name = $Row['upload_desc'] . " (" . $Row['upload_filename'] . ")";
            }
            $Page.= "<option value='" . $Row['upload_pk'] . "'>$Name</option>\n";
          }
          $Page.= "</select><P />\n";
          $Page.= "<input type='submit' value='Analyze!'>\n";
          $Page.= "</form>\n";
      break;
      case "Text":
      break;
      default:
      break;
    }
    if (!$this->OutputToStdout) {
      return ($Page);
    }
    print ("$Page");
    return;
  }
};
$NewPlugin = new agent_bucket;
?>
