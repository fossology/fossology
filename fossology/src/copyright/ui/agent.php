<?php
/***********************************************************
 Copyright (C) 2010-2012 Hewlett-Packard Development Company, L.P.

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

/**
 * \file agent.php
 * \brief Interface copyright agent to job queue
 */

define("TITLE_agent_copyright", _("Copyright/Email/URL Analysis"));

class agent_copyright extends FO_Plugin
{
  public $Name = "agent_copyright";
  public $Title = TITLE_agent_copyright;
  public $Version = "1.0";
  public $Dependency = array();
  public $DBaccess = PLUGIN_DB_ANALYZE;

  /**
   * \brief Register copyright agent in "Agents" menu
   */
  function RegisterMenus()
  {
    if ($this->State != PLUGIN_STATE_READY)  return (0);
    menu_insert("Agents::" . $this->Title, 0, $this->Name);
  }

  /**
   * \brief Check if the job can be scheduled.
   *
   * \param $upload_pk
   *
   * \return
   * - 0 = not scheduled
   * - 1 = scheduled but not completed
   * - 2 = scheduled and completed
   */
  function AgentCheck($upload_pk)
  {
    return CommonAgentCheck($upload_pk, "copyright", "copyright scanner", "copyright_ars");
  } // AgentCheck()

  /**
   * \brief  Given an uploadpk, add a job.
   *
   * \param $uploadpk - the uploadpk, add agent on this uploadpk
   * \param $Depends - is for specifying other dependencies.
   * $Depends can be a jq_pk, or an array of jq_pks, or NULL.
   * \param $Priority - job priority for this upload, default 0
   *
   * \return NULL on success, string on failure.
   */
  function AgentAdd($uploadpk, $Depends = NULL, $Priority = 0) {

    global $PG_CONN;
    global $SVN_REV;

    /* Get dependency: "nomos" require "adj2nest".
     * clean this comment up, what is being checked?
    * */
    $sql = "SELECT jq_pk FROM jobqueue INNER JOIN job ON
      job.job_upload_fk = $uploadpk AND job.job_pk = jobqueue.jq_job_fk
      WHERE jobqueue.jq_type = 'adj2nest';";
    $result = pg_query($PG_CONN, $sql);
    DBCheckResult($result, $sql, __FILE__, __LINE__);
    $row = pg_fetch_assoc($result);
    pg_free_result($result);
    $Dep = $row['jq_pk'];
    if (empty($Dep)) {

      global $Plugins;

      /* schedule unpack agent, it will also schedule adj2nest */
      $Unpack = & $Plugins[plugin_find_id("agent_unpack") ];
      $rc = $Unpack->AgentAdd($uploadpk);
      if (!empty($rc)) {
        return ($rc);
      }
      $result = pg_query($PG_CONN, $sql);
      DBCheckResult($result, $sql, __FILE__, __LINE__);
      $row = pg_fetch_assoc($result);
      pg_free_result($result);
      $Dep = $row['jq_pk'];
      if (empty($Dep)) {
        $text = _("Unable to find dependent job: unpack");
        return ($text);
      }
    }

    $Dep = array($Dep);

    if (is_array($Depends)) {
      $Dep = array_merge($Dep, $Depends);
    }
    else if (!empty($Depends)) {
      $Dep[1] = $Depends;
    }
    /* Prepare the job: job "nomos" */
    $jobpk = JobAddJob($uploadpk, "Copyright Analysis", $Priority);
    if (empty($jobpk) || ($jobpk < 0)) {
      $text = _("Failed to insert job record for copyright agent.");
      return ($text);
    }

    /*
     Using the latest agent revision, find all the records still needing processing
    this requires knowing the agents fk.
    */
    $agent_pk = GetAgentKey("copyright", "copyright agent");
    $jqargs = $uploadpk;
    /*
     Hand of the SQL  to the scheduler
    */

    /* Add job: job "Copyright Analysis" has jobqueue item "copyright" */
    $jobqueuepk = JobQueueAdd($jobpk, "copyright", $jqargs, "no", "", $Dep);
    if (empty($jobqueuepk)) {
      $text = _("Failed to insert agent copyright into job queue.");
      return ($text);
    }

    /* Tell the scheduler to check the queue. */
    $success  = fo_communicate_with_scheduler("database", $output, $error_msg);
    if (!$success) return $error_msg . "\n" . $output;

    return (NULL);
  } // AgentAdd()

  /**
   * \brief Generate the text for this plugin.
   */
  function Output() {
    if ($this->State != PLUGIN_STATE_READY) {
      return;
    }

    global $PG_CONN;

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
            $text = _("copyright/email/url analysis added to the job queue");
            $Page.= displayMessage($text);
          }
          else {
            $text = _("Scheduling of copyright/email/url analysis failed");
            $Page.= displayMessage("$text: $rc");
          }
        }
        /* Get list of projects that are not scheduled for uploads */
        $sql = "SELECT upload_pk,upload_desc,upload_filename
                FROM upload
                 WHERE upload_pk NOT IN
                (SELECT upload_pk FROM upload
                INNER JOIN job ON job.job_upload_fk = upload.upload_pk
                INNER JOIN jobqueue ON jobqueue.jq_job_fk = job.job_pk
                AND job.job_name = 'Copyright Analysis'
                AND jobqueue.jq_type = 'copyright'
                ORDER BY upload_pk)
                ORDER BY upload_desc,upload_filename;";
        $result = pg_query($PG_CONN, $sql);
        DBCheckResult($result, $sql, __FILE__, __LINE__);
        $row = pg_fetch_assoc($result, 0);
        if (empty($row['upload_pk'])) {
          $Page.= _("All uploaded files are already analyzed, or scheduled to be analyzed.");
        }
        else {
          /* Display the form */
          $Page.= "<form method='post'>\n"; // no url = this url
          $Page.= _("Select an uploaded file for copyright analysis.\n");
          $Page.= _("Only uploads that are not already scheduled can be scheduled.\n");
          $text = _("Analyze");
          $Page.= "<p />\n$text: <select name='upload'>\n";
          while ($Row = pg_fetch_assoc($result)) {
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
          $text = _("Analyze");
          $Page.= "<input type='submit' value='$text!'>\n";
          $Page.= "</form>\n";
        }
        pg_free_result($result);
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
$NewPlugin = new agent_copyright;
?>
